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

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

class ItemModel extends AdminModel
{
	public $typeAlias = 'com_downloadtracker.item';

	protected $text_prefix = 'COM_DOWNLOADTRACKER';

	public function getTable($name = 'Item', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}

	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm('com_downloadtracker.item', 'item', ['control' => 'jform', 'load_data' => $loadData]);

		return empty($form) ? false : $form;
	}

	protected function loadFormData(): array
	{
		$data = Factory::getApplication()->getUserState('com_downloadtracker.edit.item.data', []);

		if (empty($data)) {
			$data = $this->getItem();
		}

		return ArrayHelper::fromObject($data);
	}

	public function save($data): bool
	{
		$alias = (string) ($data['alias'] ?? '');
		$data['alias'] = $this->prepareAlias($alias !== '' ? $alias : (string) ($data['title'] ?? ''), (int) ($data['id'] ?? 0));
		$data['is_latest'] = empty($data['is_latest']) ? 0 : 1;
		$data['requires_token'] = empty($data['requires_token']) ? 0 : 1;
		$data['source_type'] = in_array((string) ($data['source_type'] ?? 'external'), ['external', 'private_file'], true)
			? (string) $data['source_type']
			: 'external';
		$data['target_url'] = (string) ($data['target_url'] ?? '');
		$data['private_file'] = trim(str_replace('\\', '/', (string) ($data['private_file'] ?? '')));

		return parent::save($data);
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

	private function prepareAlias(string $alias, int $id): string
	{
		$alias = Factory::getApplication()->getLanguage()->transliterate($alias);
		$alias = OutputFilter::stringURLSafe($alias);
		$alias = $alias !== '' ? $alias : OutputFilter::stringURLSafe((string) Factory::getDate()->toUnix());
		$baseAlias = $alias;
		$suffix = 2;

		while ($this->aliasExists($alias, $id)) {
			$alias = $baseAlias . '-' . $suffix;
			$suffix++;
		}

		return $alias;
	}

	private function aliasExists(string $alias, int $id): bool
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__downloadtracker_items'))
			->where($db->quoteName('alias') . ' = :alias')
			->bind(':alias', $alias);

		if ($id > 0) {
			$query->where($db->quoteName('id') . ' != :id')->bind(':id', $id, ParameterType::INTEGER);
		}

		$db->setQuery($query);

		return (int) $db->loadResult() > 0;
	}
}
