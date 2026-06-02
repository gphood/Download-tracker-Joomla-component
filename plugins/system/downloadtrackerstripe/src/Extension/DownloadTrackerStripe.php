<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.downloadtrackerstripe
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Plugin\System\DownloadTrackerStripe\Extension;

\defined('_JEXEC') or die;

use GrantHood\Component\DownloadTracker\Administrator\Service\DownloadFulfilmentService;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

class DownloadTrackerStripe extends CMSPlugin implements SubscriberInterface
{
	private const LOG_CATEGORY = 'plg_system_downloadtrackerstripe';

	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterInitialise' => 'handleWebhook',
		];
	}

	public function handleWebhook(): void
	{
		$app = $this->getApplication();

		if (!$this->isWebhookRequest($app)) {
			return;
		}

		if ((int) $this->params->get('enabled_webhook', 1) !== 1) {
			$this->respond(404, ['success' => false, 'error' => 'not_found']);
		}

		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
			$this->respond(405, ['success' => false, 'error' => 'method_not_allowed']);
		}

		$payload = (string) file_get_contents('php://input');
		$signature = $this->getStripeSignatureHeader();
		$secret = trim((string) $this->params->get('webhook_secret', ''));

		if ($payload === '' || $signature === '' || $secret === '') {
			$this->debug('Missing payload, signature, or webhook secret.');
			$this->respond(400, ['success' => false, 'error' => 'invalid_signature']);
		}

		if (!$this->verifySignature($payload, $signature, $secret)) {
			$this->debug('Stripe signature verification failed.');
			$this->respond(400, ['success' => false, 'error' => 'invalid_signature']);
		}

		$event = json_decode($payload, true);

		if (!is_array($event)) {
			$this->debug('Webhook payload failed JSON decoding.');
			$this->respond(400, ['success' => false, 'error' => 'invalid_payload']);
		}

		$type = (string) ($event['type'] ?? '');

		if (!$this->shouldHandleEvent($type)) {
			$this->debug('Ignored Stripe event: ' . ($type !== '' ? $type : 'unknown'));
			$this->respond(200, ['success' => true, 'ignored' => true]);
		}

		if (!$this->isAllowedMode((bool) ($event['livemode'] ?? false))) {
			$this->debug('Rejected Stripe event for disallowed mode.');
			$this->respond(400, ['success' => false, 'error' => 'invalid_mode']);
		}

		$session = $event['data']['object'] ?? null;

		if (!is_array($session)) {
			$this->debug('Stripe event did not contain a checkout session object.');
			$this->respond(400, ['success' => false, 'error' => 'invalid_payload']);
		}

		if ($type === 'checkout.session.completed'
			&& (int) $this->params->get('require_paid_status', 1) === 1
			&& (string) ($session['payment_status'] ?? '') !== 'paid'
		) {
			$this->debug('Checkout session was not paid.');
			$this->respond(400, ['success' => false, 'error' => 'payment_not_paid']);
		}

		$request = $this->buildFulfilmentRequest($session);

		if (!$request) {
			$this->respond(400, ['success' => false, 'error' => 'missing_required_data']);
		}

		if (!class_exists(DownloadFulfilmentService::class)) {
			$this->debug('DownloadFulfilmentService class was unavailable.');
			$this->respond(500, ['success' => false, 'error' => 'service_unavailable']);
		}

		try {
			$result = (new DownloadFulfilmentService())->createProtectedTokenAndEmail($request);
		} catch (\Throwable $e) {
			$this->debug('Fulfilment threw an exception: ' . $this->cleanLogMessage($e->getMessage()));
			$this->respond(500, ['success' => false, 'error' => 'fulfilment_failed']);
		}

		if (!empty($result['success'])) {
			$this->debug(
				!empty($result['duplicate'])
					? 'Duplicate Stripe fulfilment ignored for session ' . $request['source_reference'] . '.'
					: 'Stripe fulfilment succeeded for session ' . $request['source_reference'] . '.'
			);
			$this->respond(200, ['success' => true, 'duplicate' => !empty($result['duplicate'])]);
		}

		$this->debug('Fulfilment failed: ' . $this->cleanLogMessage((string) ($result['error'] ?? 'unknown')));
		$this->respond(500, ['success' => false, 'error' => 'fulfilment_failed']);
	}

	private function isWebhookRequest(CMSApplicationInterface $app): bool
	{
		if (method_exists($app, 'isClient') && !$app->isClient('site')) {
			return false;
		}

		$input = $app->getInput();

		return $input->getCmd('option') === 'com_downloadtracker'
			&& $input->getCmd('task') === 'stripe.webhook';
	}

	private function shouldHandleEvent(string $type): bool
	{
		if ($type === 'checkout.session.completed') {
			return true;
		}

		return $type === 'checkout.session.async_payment_succeeded'
			&& (int) $this->params->get('handle_async_payment_succeeded', 0) === 1;
	}

	private function isAllowedMode(bool $livemode): bool
	{
		$mode = (string) $this->params->get('mode', 'test');

		if ($mode === 'both') {
			return true;
		}

		return $mode === 'live' ? $livemode : !$livemode;
	}

	private function buildFulfilmentRequest(array $session): ?array
	{
		$sessionId = trim((string) ($session['id'] ?? ''));
		$email = $this->extractCustomerEmail($session);
		$itemId = $this->extractItemId($session);

		if ($sessionId === '' || $email === '' || $itemId <= 0) {
			$this->debug('Missing checkout session ID, customer email, or Download Tracker item ID.');

			return null;
		}

		$paymentIntent = $this->extractPaymentIntentId($session);
		$expiry = $this->calculateExpiry();
		$maxUses = max(1, (int) $this->params->get('default_max_uses', 3));

		return [
			'item_id' => $itemId,
			'customer_email' => $email,
			'label' => Text::sprintf('PLG_SYSTEM_DOWNLOADTRACKERSTRIPE_FULFILMENT_LABEL', $sessionId),
			'note' => $paymentIntent !== ''
				? Text::sprintf('PLG_SYSTEM_DOWNLOADTRACKERSTRIPE_FULFILMENT_NOTE_PAYMENT_INTENT', $paymentIntent)
				: '',
			'max_uses' => $maxUses,
			'expires_at' => $expiry,
			'created_by' => 0,
			'source' => 'stripe',
			'source_reference' => $sessionId,
			'send_email' => true,
		];
	}

	private function extractCustomerEmail(array $session): string
	{
		$customerDetails = $session['customer_details'] ?? [];

		if (is_array($customerDetails)) {
			$email = trim((string) ($customerDetails['email'] ?? ''));

			if ($email !== '') {
				return $email;
			}
		}

		return trim((string) ($session['customer_email'] ?? ''));
	}

	private function extractPaymentIntentId(array $session): string
	{
		$paymentIntent = $session['payment_intent'] ?? '';

		return is_string($paymentIntent) ? trim($paymentIntent) : '';
	}

	private function extractItemId(array $session): int
	{
		$key = trim((string) $this->params->get('metadata_item_key', 'downloadtracker_item_id'));
		$metadata = $session['metadata'] ?? [];

		if ($key === '' || !is_array($metadata)) {
			return 0;
		}

		return max(0, (int) ($metadata[$key] ?? 0));
	}

	private function calculateExpiry(): ?string
	{
		$days = (int) $this->params->get('default_expiry_days', 0);

		if ($days <= 0) {
			return null;
		}

		return Factory::getDate('+' . $days . ' days')->toSql();
	}

	private function verifySignature(string $payload, string $signatureHeader, string $secret): bool
	{
		$parts = $this->parseSignatureHeader($signatureHeader);
		$timestamp = (int) ($parts['t'][0] ?? 0);
		$signatures = $parts['v1'] ?? [];

		if ($timestamp <= 0 || empty($signatures)) {
			return false;
		}

		$tolerance = max(1, (int) $this->params->get('signature_tolerance', 300));

		if (abs(time() - $timestamp) > $tolerance) {
			return false;
		}

		$expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

		foreach ($signatures as $signature) {
			if (hash_equals($expected, $signature)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, list<string>>
	 */
	private function parseSignatureHeader(string $signatureHeader): array
	{
		$parts = [];

		foreach (explode(',', $signatureHeader) as $item) {
			$pair = explode('=', trim($item), 2);

			if (count($pair) !== 2 || $pair[0] === '') {
				continue;
			}

			$parts[$pair[0]][] = $pair[1];
		}

		return $parts;
	}

	private function getStripeSignatureHeader(): string
	{
		return trim((string) (
			$_SERVER['HTTP_STRIPE_SIGNATURE']
			?? $_SERVER['Stripe-Signature']
			?? ''
		));
	}

	private function respond(int $status, array $data): void
	{
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');

		echo json_encode($data, JSON_UNESCAPED_SLASHES);

		$this->getApplication()->close();
	}

	private function debug(string $message): void
	{
		if ((int) $this->params->get('debug_logging', 0) !== 1) {
			return;
		}

		Log::add($message, Log::DEBUG, self::LOG_CATEGORY);
	}

	private function cleanLogMessage(string $message): string
	{
		$message = trim(preg_replace('/\s+/', ' ', $message) ?: '');

		return mb_substr($message, 0, 500);
	}
}
