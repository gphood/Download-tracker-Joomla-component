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

		return empty($form) ? false : $form;
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
		$isNew = empty($data['id']);
		$data['item_id'] = (int) ($data['item_id'] ?? 0);
		$data['state'] = (int) ($data['state'] ?? 1);
		$data['label'] = trim((string) ($data['label'] ?? ''));
		$data['customer_email'] = trim((string) ($data['customer_email'] ?? ''));
		$data['note'] = (string) ($data['note'] ?? '');
		$data['expires_at'] = trim((string) ($data['expires_at'] ?? ''));
		$data['max_uses'] = trim((string) ($data['max_uses'] ?? ''));

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

		if ($saved && $isNew) {
			$tokenId = (int) $this->getState($this->getName() . '.id');
			$this->storeGeneratedTokenNotice($tokenId, $data['item_id'], $rawToken, (string) $data['token_prefix']);
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

	private function storeGeneratedTokenNotice(int $tokenId, int $itemId, string $rawToken, string $tokenPrefix): void
	{
		$item = $this->getDownloadItem($itemId);
		$downloadUrl = '';

		if ($item) {
			$downloadUrl = DownloadTrackerHelper::buildPublicDownloadUrlForAlias((string) $item->alias);
			$separator = str_contains($downloadUrl, '?') ? '&' : '?';
			$downloadUrl .= $separator . 'token=' . rawurlencode($rawToken);
		}

		Factory::getApplication()->setUserState('com_downloadtracker.generated_token', [
			'token_id' => $tokenId,
			'raw_token' => $rawToken,
			'token_prefix' => $tokenPrefix,
			'download_url' => $downloadUrl,
		]);
	}

	private function getDownloadItem(int $itemId): ?object
	{
		if ($itemId <= 0) {
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'alias']))
			->from($db->quoteName('#__downloadtracker_items'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $itemId, ParameterType::INTEGER);

		$db->setQuery($query);
		$item = $db->loadObject();

		return $item ?: null;
	}
}
