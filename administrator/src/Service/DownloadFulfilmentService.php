<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Service;

\defined('_JEXEC') or die;

use GrantHood\Component\DownloadTracker\Administrator\Helper\DownloadTrackerHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class DownloadFulfilmentService
{
	private DatabaseInterface $db;

	public function __construct(?DatabaseInterface $db = null)
	{
		$this->db = $db ?: Factory::getContainer()->get(DatabaseInterface::class);
	}

	public function createProtectedTokenAndEmail(array $request): array
	{
		return $this->createProtectedToken($request, false);
	}

	public function createProtectedTokenAndEmailForAdmin(array $request): array
	{
		return $this->createProtectedToken($request, true);
	}

	private function createProtectedToken(array $request, bool $includeRawToken): array
	{
		$itemId = (int) ($request['item_id'] ?? 0);
		$sendEmail = (bool) ($request['send_email'] ?? false);
		$email = trim((string) ($request['customer_email'] ?? ''));
		$source = trim((string) ($request['source'] ?? ''));
		$sourceReference = trim((string) ($request['source_reference'] ?? ''));

		if ($source !== '' && $sourceReference !== '') {
			$existing = $this->getExistingTokenForSource($source, $sourceReference);

			if ($existing) {
				return [
					'success' => true,
					'already_exists' => true,
					'duplicate' => true,
					'token_id' => (int) $existing->id,
					'token_prefix' => (string) $existing->token_prefix,
					'download_url_sent' => false,
					'email_status' => (string) ($existing->last_email_status ?: 'already_exists'),
					'error' => null,
				];
			}
		}

		if ($itemId <= 0) {
			return $this->failure(Text::_('COM_DOWNLOADTRACKER_ERROR_ITEM_REQUIRED'));
		}

		if ($sendEmail && !$this->isValidEmail($email)) {
			return $this->failure(Text::_('COM_DOWNLOADTRACKER_ERROR_CUSTOMER_EMAIL_REQUIRED_TO_SEND'));
		}

		$item = $this->getDownloadItem($itemId);

		if (!$item) {
			return $this->failure(Text::_('COM_DOWNLOADTRACKER_ERROR_ITEM_REQUIRED'));
		}

		$rawToken = bin2hex(random_bytes(24));
		$tokenPrefix = substr($rawToken, 0, 12);
		$downloadUrl = $this->buildProtectedDownloadUrl((string) $item->alias, $rawToken);
		$tokenId = $this->insertToken($request, $itemId, $email, $rawToken, $tokenPrefix, $source, $sourceReference);

		$result = [
			'success' => true,
			'already_exists' => false,
			'duplicate' => false,
			'token_id' => $tokenId,
			'token_prefix' => $tokenPrefix,
			'download_url_sent' => false,
			'email_status' => null,
			'error' => null,
			'item_title' => (string) $item->title,
		];

		if ($includeRawToken) {
			$result['raw_token'] = $rawToken;
			$result['download_url'] = $downloadUrl;
		}

		if ($sendEmail) {
			$emailResult = $this->sendTokenEmail($tokenId, $email, (string) $item->title, $downloadUrl, $request);
			$result['download_url_sent'] = $emailResult['sent'];
			$result['email_status'] = $emailResult['status'];
			$result['error'] = $emailResult['error'];
		}

		return $result;
	}

	private function insertToken(array $request, int $itemId, string $email, string $rawToken, string $tokenPrefix, string $source, string $sourceReference): int
	{
		$date = Factory::getDate()->toSql();
		$maxUses = $request['max_uses'] ?? null;
		$expiresAt = trim((string) ($request['expires_at'] ?? ''));

		$token = (object) [
			'item_id' => $itemId,
			'label' => trim((string) ($request['label'] ?? '')),
			'token_hash' => hash('sha256', $rawToken),
			'token_prefix' => $tokenPrefix,
			'state' => (int) ($request['state'] ?? 1),
			'expires_at' => $expiresAt !== '' ? $expiresAt : null,
			'max_uses' => $maxUses !== null && $maxUses !== '' ? max(1, (int) $maxUses) : null,
			'used_count' => 0,
			'customer_email' => $email,
			'note' => (string) ($request['note'] ?? ''),
			'source' => $source !== '' ? $source : null,
			'source_reference' => $sourceReference !== '' ? $sourceReference : null,
			'created' => $date,
			'created_by' => (int) ($request['created_by'] ?? 0),
			'modified_by' => 0,
		];

		$this->db->insertObject('#__downloadtracker_tokens', $token, 'id');

		return (int) $token->id;
	}

	private function sendTokenEmail(int $tokenId, string $email, string $itemTitle, string $downloadUrl, array $request): array
	{
		$app = Factory::getApplication();
		$itemTitle = $itemTitle !== '' ? $itemTitle : Text::_('COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_TITLE_FALLBACK');

		if ($downloadUrl === '') {
			$message = Text::_('COM_DOWNLOADTRACKER_ERROR_PROTECTED_DOWNLOAD_URL_UNAVAILABLE');
			$this->updateEmailAudit($tokenId, 'failed', $email, $message);

			return ['sent' => false, 'status' => 'failed', 'error' => $message];
		}

		try {
			$mailer = Factory::getMailer();
			$mailFrom = (string) $app->get('mailfrom', '');
			$fromName = (string) ($app->get('fromname', '') ?: $app->get('sitename', ''));

			if ($mailFrom !== '') {
				$mailer->setSender([$mailFrom, $fromName]);
			}

			$expiry = !empty($request['expires_at']) ? (string) $request['expires_at'] : Text::_('COM_DOWNLOADTRACKER_EMAIL_NO_EXPIRY');
			$maxUses = !empty($request['max_uses']) ? (string) (int) $request['max_uses'] : Text::_('COM_DOWNLOADTRACKER_UNLIMITED');
			$supportName = $fromName !== '' ? $fromName : (string) $app->get('sitename', '');
			$subject = Text::sprintf('COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_SUBJECT', $itemTitle);
			$body = Text::sprintf(
				'COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_BODY',
				$itemTitle,
				$downloadUrl,
				$expiry,
				$maxUses,
				$supportName
			);

			$mailer->addRecipient($email);
			$mailer->setSubject($subject);
			$mailer->setBody($body);
			$mailer->isHtml(false);
			$result = $mailer->Send();

			if ($result !== true) {
				throw new \RuntimeException(is_string($result) ? $result : Text::_('COM_DOWNLOADTRACKER_ERROR_DOWNLOAD_EMAIL_FAILED'));
			}

			$this->updateEmailAudit($tokenId, 'sent', $email, null);

			return ['sent' => true, 'status' => 'sent', 'error' => null];
		} catch (\Throwable $e) {
			$message = $this->trimEmailError($e->getMessage());
			$this->updateEmailAudit($tokenId, 'failed', $email, $message);

			return ['sent' => false, 'status' => 'failed', 'error' => $message];
		}
	}

	private function updateEmailAudit(int $tokenId, string $status, string $email, ?string $error): void
	{
		if ($status === 'sent') {
			$query = $this->db->getQuery(true)
				->update($this->db->quoteName('#__downloadtracker_tokens'))
				->set($this->db->quoteName('emailed_at') . ' = :emailed_at')
				->set($this->db->quoteName('emailed_to') . ' = :emailed_to')
				->set($this->db->quoteName('email_count') . ' = ' . $this->db->quoteName('email_count') . ' + 1')
				->set($this->db->quoteName('last_email_status') . ' = :last_email_status')
				->set($this->db->quoteName('last_email_error') . ' = NULL')
				->where($this->db->quoteName('id') . ' = :id')
				->bind(':emailed_at', Factory::getDate()->toSql())
				->bind(':emailed_to', $email)
				->bind(':last_email_status', $status)
				->bind(':id', $tokenId, ParameterType::INTEGER);

			$this->db->setQuery($query);
			$this->db->execute();

			return;
		}

		$update = (object) [
			'id' => $tokenId,
			'last_email_status' => $status,
			'last_email_error' => $error,
		];

		$this->db->updateObject('#__downloadtracker_tokens', $update, 'id', true);
	}

	private function buildProtectedDownloadUrl(string $alias, string $rawToken): string
	{
		$downloadUrl = DownloadTrackerHelper::buildPublicDownloadUrlForAlias($alias);
		$separator = str_contains($downloadUrl, '?') ? '&' : '?';

		return $downloadUrl . $separator . 'token=' . rawurlencode($rawToken);
	}

	private function getDownloadItem(int $itemId): ?object
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'title', 'alias']))
			->from($this->db->quoteName('#__downloadtracker_items'))
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':id', $itemId, ParameterType::INTEGER);

		$this->db->setQuery($query);
		$item = $this->db->loadObject();

		return $item ?: null;
	}

	private function getExistingTokenForSource(string $source, string $sourceReference): ?object
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'token_prefix', 'last_email_status']))
			->from($this->db->quoteName('#__downloadtracker_tokens'))
			->where($this->db->quoteName('source') . ' = :source')
			->where($this->db->quoteName('source_reference') . ' = :source_reference')
			->bind(':source', $source)
			->bind(':source_reference', $sourceReference);

		$this->db->setQuery($query);
		$token = $this->db->loadObject();

		return $token ?: null;
	}

	private function failure(string $error): array
	{
		return [
			'success' => false,
			'token_id' => 0,
			'token_prefix' => '',
			'download_url_sent' => false,
			'email_status' => 'failed',
			'error' => $error,
		];
	}

	private function isValidEmail(string $email): bool
	{
		return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	private function trimEmailError(string $message): string
	{
		$message = trim($message);

		return mb_substr($message !== '' ? $message : Text::_('COM_DOWNLOADTRACKER_ERROR_DOWNLOAD_EMAIL_FAILED'), 0, 1000);
	}
}
