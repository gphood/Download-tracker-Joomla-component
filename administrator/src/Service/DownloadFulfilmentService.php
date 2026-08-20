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

	public function reissueUpdateKeyForAdmin(int $tokenId, int $createdBy): array
	{
		$existing = $this->getTokenForReissue($tokenId);

		if (!$existing || (string) $existing->purpose !== 'update') {
			return $this->failure(Text::_('COM_DOWNLOADTRACKER_ERROR_TOKEN_REISSUE_FAILED'));
		}

		$email = trim((string) $existing->customer_email);

		if (!$this->isValidEmail($email)) {
			return $this->failure(Text::_('COM_DOWNLOADTRACKER_ERROR_TOKEN_REISSUE_REQUIRES_EMAIL'));
		}

		$result = $this->createProtectedToken([
			'item_id' => (int) $existing->item_id,
			'customer_email' => $email,
			'label' => (string) $existing->label,
			'note' => trim((string) $existing->note . "\nReplacement for token " . (string) $existing->token_prefix),
			'purpose' => 'update',
			'send_email' => true,
			'created_by' => $createdBy,
			'source' => 'reissue',
			'source_reference' => $tokenId . ':' . bin2hex(random_bytes(8)),
		], true);

		if (empty($result['success']) || ($result['email_status'] ?? '') !== 'sent') {
			if (!empty($result['token_id'])) {
				$this->setTokenState((int) $result['token_id'], 0, $createdBy);
			}

			return $result;
		}

		$this->setTokenState($tokenId, 0, $createdBy);
		$result['replaced_token_id'] = $tokenId;
		$result['customer_email'] = $email;

		return $result;
	}

	private function createProtectedToken(array $request, bool $includeRawToken): array
	{
		$itemId = (int) ($request['item_id'] ?? 0);
		$sendEmail = (bool) ($request['send_email'] ?? false);
		$email = trim((string) ($request['customer_email'] ?? ''));
		$source = trim((string) ($request['source'] ?? ''));
		$sourceReference = trim((string) ($request['source_reference'] ?? ''));
		$purpose = (string) ($request['purpose'] ?? 'download') === 'update' ? 'update' : 'download';

		if ($purpose === 'update') {
			$request['max_uses'] = null;
			$request['expires_at'] = null;
		}

		$request['purpose'] = $purpose;

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
			'purpose' => $purpose,
		];

		if ($includeRawToken) {
			$result['raw_token'] = $rawToken;
			$result['download_url'] = $downloadUrl;
		}

		if ($sendEmail) {
			$emailResult = $this->sendTokenEmail(
				$tokenId,
				$email,
				(string) $item->title,
				(string) ($item->customer_instructions ?? ''),
				$downloadUrl,
				$rawToken,
				$request
			);
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
			'purpose' => (string) ($request['purpose'] ?? 'download'),
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

	private function sendTokenEmail(int $tokenId, string $email, string $itemTitle, string $customerInstructions, string $downloadUrl, string $rawToken, array $request): array
	{
		$app = Factory::getApplication();
		$this->loadEmailLanguageStrings();

		$itemTitle = $itemTitle !== '' ? $itemTitle : $this->translateOrFallback('COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_TITLE_FALLBACK', 'your download');

		if ($downloadUrl === '') {
			$message = $this->translateOrFallback(
				'COM_DOWNLOADTRACKER_ERROR_PROTECTED_DOWNLOAD_URL_UNAVAILABLE',
				'The protected download URL could not be generated.'
			);
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

			$expiry = !empty($request['expires_at']) ? (string) $request['expires_at'] : $this->translateOrFallback('COM_DOWNLOADTRACKER_EMAIL_NO_EXPIRY', 'No expiry date');
			$maxUses = !empty($request['max_uses']) ? (string) (int) $request['max_uses'] : $this->translateOrFallback('COM_DOWNLOADTRACKER_UNLIMITED', 'Unlimited');
			$supportName = $fromName !== '' ? $fromName : (string) $app->get('sitename', '');
			$purpose = (string) ($request['purpose'] ?? 'download');
			$subject = $this->buildEmailSubject($itemTitle, $purpose);
			$customerInstructions = trim($customerInstructions);
			$body = $this->buildEmailBody($itemTitle, $downloadUrl, $rawToken, $expiry, $maxUses, $supportName, $purpose, $customerInstructions);

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

	private function loadEmailLanguageStrings(): void
	{
		$language = Factory::getApplication()->getLanguage() ?: Factory::getLanguage();
		$language->load('com_downloadtracker', JPATH_ADMINISTRATOR, null, true, true);
		$language->load('com_downloadtracker', JPATH_SITE, null, true, true);
	}

	private function buildEmailSubject(string $itemTitle, string $purpose): string
	{
		if ($purpose === 'update') {
			$subject = Text::sprintf('COM_DOWNLOADTRACKER_EMAIL_UPDATE_SUBJECT', $itemTitle);

			return $this->isUntranslatedEmailString($subject, 'COM_DOWNLOADTRACKER_EMAIL_UPDATE_SUBJECT')
				? sprintf('Your download and update key for %s', $itemTitle)
				: $subject;
		}

		$subject = Text::sprintf('COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_SUBJECT', $itemTitle);

		if ($this->isUntranslatedEmailString($subject, 'COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_SUBJECT')) {
			return sprintf('Your download link for %s', $itemTitle);
		}

		return $subject;
	}

	private function buildEmailBody(string $itemTitle, string $downloadUrl, string $rawToken, string $expiry, string $maxUses, string $supportName, string $purpose, string $customerInstructions): string
	{
		$instructionsSection = '';

		if ($customerInstructions !== '') {
			$instructionsHeading = $this->translateOrFallback(
				'COM_DOWNLOADTRACKER_EMAIL_CUSTOMER_INSTRUCTIONS_HEADING',
				'Important installation information:'
			);
			$instructionsSection = "\n\n" . $instructionsHeading . "\n" . $customerInstructions;
		}

		if ($purpose === 'update') {
			$body = Text::sprintf(
				'COM_DOWNLOADTRACKER_EMAIL_UPDATE_BODY',
				$instructionsSection,
				$itemTitle,
				$downloadUrl,
				$itemTitle,
				$rawToken,
				$supportName
			);

			if (!$this->isUntranslatedEmailString($body, 'COM_DOWNLOADTRACKER_EMAIL_UPDATE_BODY')) {
				return $body;
			}

			return sprintf(
				"Thank you for your purchase.%s\n\nYour secure download link for %s is:\n\n%s\n\nInstall the downloaded ZIP through System → Install → Extensions.\n\nTo receive future updates through Joomla, enter this update key once under System → Update → Update Sites by opening the update site for %s:\n\n%s\n\nKeep the download link and update key private.\n\n%s",
				$instructionsSection,
				$itemTitle,
				$downloadUrl,
				$itemTitle,
				$rawToken,
				$supportName
			);
		}

		$body = Text::sprintf(
			'COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_BODY',
			$instructionsSection,
			$itemTitle,
			$downloadUrl,
			$expiry,
			$maxUses,
			$supportName
		);

		if (!$this->isUntranslatedEmailString($body, 'COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_BODY')) {
			return $body;
		}

		return sprintf(
			"Thank you for your purchase.%s\n\nYour secure download link for %s is:\n\n%s\n\nPlease keep this link private. It may expire or stop working after the permitted number of downloads has been reached.\n\nExpiry: %s\nMaximum uses: %s\n\n%s",
			$instructionsSection,
			$itemTitle,
			$downloadUrl,
			$expiry,
			$maxUses,
			$supportName
		);
	}

	private function translateOrFallback(string $key, string $fallback): string
	{
		$value = Text::_($key);

		return $value === $key ? $fallback : $value;
	}

	private function isUntranslatedEmailString(string $value, string $key): bool
	{
		return $value === $key || str_starts_with($value, $key);
	}

	private function updateEmailAudit(int $tokenId, string $status, string $email, ?string $error): void
	{
		if ($status === 'sent') {
			$emailedAt = Factory::getDate()->toSql();
			$query = $this->db->getQuery(true)
				->update($this->db->quoteName('#__downloadtracker_tokens'))
				->set($this->db->quoteName('emailed_at') . ' = :emailed_at')
				->set($this->db->quoteName('emailed_to') . ' = :emailed_to')
				->set($this->db->quoteName('email_count') . ' = ' . $this->db->quoteName('email_count') . ' + 1')
				->set($this->db->quoteName('last_email_status') . ' = :last_email_status')
				->set($this->db->quoteName('last_email_error') . ' = NULL')
				->where($this->db->quoteName('id') . ' = :id')
				->bind(':emailed_at', $emailedAt)
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
			->select($this->db->quoteName(['id', 'title', 'alias', 'customer_instructions']))
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

	private function getTokenForReissue(int $tokenId): ?object
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'item_id', 'label', 'customer_email', 'note', 'purpose', 'token_prefix']))
			->from($this->db->quoteName('#__downloadtracker_tokens'))
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':id', $tokenId, ParameterType::INTEGER);

		$this->db->setQuery($query);
		$token = $this->db->loadObject();

		return $token ?: null;
	}

	private function setTokenState(int $tokenId, int $state, int $modifiedBy): void
	{
		$update = (object) [
			'id' => $tokenId,
			'state' => $state,
			'modified' => Factory::getDate()->toSql(),
			'modified_by' => $modifiedBy,
		];

		$this->db->updateObject('#__downloadtracker_tokens', $update, 'id', true);
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
