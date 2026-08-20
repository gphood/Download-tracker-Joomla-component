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

use GrantHood\Component\DownloadTracker\Administrator\Service\DownloadFulfilmentService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
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
		$data['purpose'] = (string) ($data['purpose'] ?? 'download') === 'update' ? 'update' : 'download';
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

		if ($data['purpose'] === 'update') {
			$data['expires_at'] = null;
			$data['max_uses'] = null;
		} elseif ($data['expires_at'] === '') {
			$data['expires_at'] = null;
		}

		if ($data['purpose'] !== 'update' && $data['max_uses'] === '') {
			$data['max_uses'] = null;
		} elseif ($data['purpose'] !== 'update') {
			$data['max_uses'] = max(1, (int) $data['max_uses']);
		}

		if ($isNew) {
			$result = (new DownloadFulfilmentService())->createProtectedTokenAndEmailForAdmin([
				'item_id' => $data['item_id'],
				'customer_email' => $data['customer_email'],
				'label' => $data['label'],
				'note' => $data['note'],
				'purpose' => $data['purpose'],
				'state' => $data['state'],
				'expires_at' => $data['expires_at'],
				'max_uses' => $data['max_uses'],
				'created_by' => (int) $app->getIdentity()->id,
				'send_email' => $sendEmail,
			]);

			if (empty($result['success'])) {
				$this->setError((string) ($result['error'] ?? Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED')));

				return false;
			}

			$this->setState($this->getName() . '.id', (int) $result['token_id']);
			$this->setState($this->getName() . '.new', true);
			$this->storeGeneratedTokenNotice($result);

			if ($sendEmail) {
				if (($result['email_status'] ?? '') === 'sent') {
					$app->enqueueMessage(Text::sprintf('COM_DOWNLOADTRACKER_DOWNLOAD_EMAIL_SENT_TO', $data['customer_email']), 'message');
				} elseif (($result['email_status'] ?? '') === 'failed') {
					$app->enqueueMessage(Text::sprintf('COM_DOWNLOADTRACKER_ERROR_DOWNLOAD_EMAIL_FAILED_WITH_MESSAGE', (string) $result['error']), 'error');
				}
			}

			return true;
		}

		return parent::save($data);
	}

	public function reissue(int $tokenId): array
	{
		return (new DownloadFulfilmentService())->reissueUpdateKeyForAdmin(
			$tokenId,
			(int) Factory::getApplication()->getIdentity()->id
		);
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

	private function storeGeneratedTokenNotice(array $generated): void
	{
		Factory::getApplication()->setUserState('com_downloadtracker.generated_token', $generated);
	}

	private function isValidEmail(string $email): bool
	{
		return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}
}
