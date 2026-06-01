<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Model;

\defined('_JEXEC') or die;

use GrantHood\Component\DownloadTracker\Administrator\Helper\DownloadTrackerHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

class TokenModel extends AdminModel
{
	public $typeAlias = 'com_downloadtracker.token';

	protected $text_prefix = 'COM_DOWNLOADTRACKER';

	public function getTable($name = 'Token', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm('com_downloadtracker.token', 'token', ['control' => 'jform', 'load_data' => $loadData]);

		if (empty($form)) {
			return false;
		}

		$id = (int) ($data['id'] ?? Factory::getApplication()->getInput()->getInt('id'));

		if ($id > 0) {
			$form->removeField('send_email');
		}

		return $form;
	}

	protected function loadFormData(): array
	{
		$data = Factory::getApplication()->getUserState('com_downloadtracker.edit.token.data', []);

		if (empty($data)) {
			$data = $this->getItem();
		}

		return ArrayHelper::fromObject($data);
	}

	public function save($data): bool
	{
		$app = Factory::getApplication();
		$isNew = empty($data['id']);
		$postedJform = $app->getInput()->post->get('jform', [], 'array');
		$filteredSendEmail = (int) ($data['send_email'] ?? 0);
		$postedSendEmail = (int) ($postedJform['send_email'] ?? 0);
		$sendEmail = $isNew && ($filteredSendEmail === 1 || $postedSendEmail === 1);

		$data['item_id'] = (int) ($data['item_id'] ?? 0);
		$data['state'] = (int) ($data['state'] ?? 1);
		$data['label'] = trim((string) ($data['label'] ?? ''));
		$data['customer_email'] = trim((string) ($data['customer_email'] ?? ''));
		$data['note'] = (string) ($data['note'] ?? '');
		$data['expires_at'] = trim((string) ($data['expires_at'] ?? ''));
		$data['max_uses'] = trim((string) ($data['max_uses'] ?? ''));
		unset($data['send_email']);

		if ($sendEmail && !$this->isValidEmail($data['customer_email'])) {
			$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_CUSTOMER_EMAIL_REQUIRED_TO_SEND'));

			return false;
		}

		if ($data['expires_at'] === '') {
			$data['expires_at'] = null;
		}

		if ($data['max_uses'] === '') {
			$data['max_uses'] = null;
		} else {
			$data['max_uses'] = max(1, (int) $data['max_uses']);
		}

		if ($isNew) {
			$rawToken = bin2hex(random_bytes(24));
			$data['token_hash'] = hash('sha256', $rawToken);
			$data['token_prefix'] = substr($rawToken, 0, 12);
			$data['used_count'] = 0;
		}

		$saved = parent::save($data);
		$tokenId = (int) $this->getState($this->getName() . '.id');

		if ($saved && $isNew) {
			$generated = $this->getGeneratedTokenDetails($tokenId, $data['item_id'], $rawToken, (string) $data['token_prefix']);
			$this->storeGeneratedTokenNotice($generated);

			if ($sendEmail) {
				$this->sendGeneratedTokenEmail($tokenId, $generated, $data);
			}
		}

		return $saved;
	}

	protected function prepareTable($table): void
	{
		$date = Factory::getDate()->toSql();
		$user = Factory::getApplication()->getIdentity();

		if (empty($table->id)) {
			$table->created = $date;
			$table->created_by = (int) $user->id;
		} else {
			$table->modified = $date;
			$table->modified_by = (int) $user->id;
		}
	}

	private function getGeneratedTokenDetails(int $tokenId, int $itemId, string $rawToken, string $tokenPrefix): array
	{
		$item = $this->getDownloadItem($itemId);
		$downloadUrl = '';
		$itemTitle = '';

		if ($item) {
			$itemTitle = (string) $item->title;
			$downloadUrl = DownloadTrackerHelper::buildPublicDownloadUrlForAlias((string) $item->alias);
			$separator = str_contains($downloadUrl, '?') ? '&' : '?';
			$downloadUrl .= $separator . 'token=' . rawurlencode($rawToken);
		}

		return [
			'token_id' => $tokenId,
			'item_title' => $itemTitle,
			'raw_token' => $rawToken,
			'token_prefix' => $tokenPrefix,
			'download_url' => $downloadUrl,
		];
	}

	private function storeGeneratedTokenNotice(array $generated): void
	{
		Factory::getApplication()->setUserState('com_downloadtracker.generated_token', $generated);
	}

	private function sendGeneratedTokenEmail(int $tokenId, array $generated, array $data): void
	{
		$app = Factory::getApplication();
		$email = (string) $data['customer_email'];
		$downloadUrl = (string) ($generated['download_url'] ?? '');
		$itemTitle = (string) ($generated['item_title'] ?: Text::_('COM_DOWNLOADTRACKER_EMAIL_DOWNLOAD_TITLE_FALLBACK'));

		if ($downloadUrl === '') {
			$message = Text::_('COM_DOWNLOADTRACKER_ERROR_PROTECTED_DOWNLOAD_URL_UNAVAILABLE');
			$this->updateEmailAudit($tokenId, 'failed', $email, $message);
			$app->enqueueMessage($message, 'error');

			return;
		}

		try {
			$mailer = Factory::getMailer();
			$mailFrom = (string) $app->get('mailfrom', '');
			$fromName = (string) ($app->get('fromname', '') ?: $app->get('sitename', ''));

			if ($mailFrom !== '') {
				$mailer->setSender([$mailFrom, $fromName]);
			}

			$expiry = $data['expires_at'] ? (string) $data['expires_at'] : Text::_('COM_DOWNLOADTRACKER_EMAIL_NO_EXPIRY');
			$maxUses = $data['max_uses'] ? (string) (int) $data['max_uses'] : Text::_('COM_DOWNLOADTRACKER_UNLIMITED');
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
			$app->enqueueMessage(Text::sprintf('COM_DOWNLOADTRACKER_DOWNLOAD_EMAIL_SENT_TO', $email), 'message');
		} catch (\Throwable $e) {
			$message = $this->trimEmailError($e->getMessage());
			$this->updateEmailAudit($tokenId, 'failed', $email, $message);
			$app->enqueueMessage(Text::sprintf('COM_DOWNLOADTRACKER_ERROR_DOWNLOAD_EMAIL_FAILED_WITH_MESSAGE', $message), 'error');
		}
	}

	private function updateEmailAudit(int $tokenId, string $status, string $email, ?string $error): void
	{
		$db = $this->getDatabase();

		if ($status === 'sent') {
			$query = $db->getQuery(true)
				->update($db->quoteName('#__downloadtracker_tokens'))
				->set($db->quoteName('emailed_at') . ' = :emailed_at')
				->set($db->quoteName('emailed_to') . ' = :emailed_to')
				->set($db->quoteName('email_count') . ' = ' . $db->quoteName('email_count') . ' + 1')
				->set($db->quoteName('last_email_status') . ' = :last_email_status')
				->set($db->quoteName('last_email_error') . ' = NULL')
				->where($db->quoteName('id') . ' = :id')
				->bind(':emailed_at', Factory::getDate()->toSql())
				->bind(':emailed_to', $email)
				->bind(':last_email_status', $status)
				->bind(':id', $tokenId, ParameterType::INTEGER);

			$db->setQuery($query);
			$db->execute();

			return;
		}

		$update = (object) [
			'id' => $tokenId,
			'last_email_status' => $status,
			'last_email_error' => $error,
		];

		$db->updateObject('#__downloadtracker_tokens', $update, 'id', true);
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

	private function getDownloadItem(int $itemId): ?object
	{
		if ($itemId <= 0) {
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'title', 'alias']))
			->from($db->quoteName('#__downloadtracker_items'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $itemId, ParameterType::INTEGER);

		$db->setQuery($query);
		$item = $db->loadObject();

		return $item ?: null;
	}
}
