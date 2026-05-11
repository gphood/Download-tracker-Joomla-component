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
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

class LogsModel extends ListModel
{
	public function __construct($config = [])
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = [
				'id', 'a.id', 'downloaded_at', 'a.downloaded_at', 'item_title', 'i.title',
				'product_title', 'p.title', 'edition', 'a.edition', 'version', 'a.version',
				'requested_alias', 'a.requested_alias', 'resolved_version', 'a.resolved_version',
				'ip_address', 'a.ip_address', 'referrer', 'a.referrer', 'user_agent', 'a.user_agent',
				'requested_url', 'a.requested_url', 'target_url', 'a.target_url', 'is_bot', 'a.is_bot', 'status', 'a.status',
			];
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = 'a.downloaded_at', $direction = 'desc'): void
	{
		$app = Factory::getApplication();
		$this->setState('filter.search', $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search'));
		$this->setState('filter.product_id', $app->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_product_id', '', 'string'));
		$this->setState('filter.item_id', $app->getUserStateFromRequest($this->context . '.filter.item_id', 'filter_item_id', '', 'string'));
		$this->setState('filter.edition', $app->getUserStateFromRequest($this->context . '.filter.edition', 'filter_edition', '', 'string'));
		$this->setState('filter.version', $app->getUserStateFromRequest($this->context . '.filter.version', 'filter_version', '', 'string'));
		$this->setState('filter.status', $app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'string'));
		$this->setState('filter.date_from', $app->getUserStateFromRequest($this->context . '.filter.date_from', 'filter_date_from', '', 'string'));
		$this->setState('filter.date_to', $app->getUserStateFromRequest($this->context . '.filter.date_to', 'filter_date_to', '', 'string'));

		parent::populateState($ordering, $direction);
	}

	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['a.id', 'a.item_id', 'a.product_id', 'a.downloaded_at', 'a.requested_alias', 'a.edition', 'a.version', 'a.resolved_version', 'a.ip_address', 'a.referrer', 'a.requested_url', 'a.user_agent', 'a.target_url', 'a.is_bot', 'a.status']))
			->select($db->quoteName('i.title', 'item_title'))
			->select($db->quoteName('p.title', 'product_title'))
			->from($db->quoteName('#__downloadtracker_logs', 'a'))
			->leftJoin($db->quoteName('#__downloadtracker_items', 'i') . ' ON ' . $db->quoteName('i.id') . ' = ' . $db->quoteName('a.item_id'))
			->leftJoin($db->quoteName('#__downloadtracker_products', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('a.product_id'));

		$search = trim((string) $this->getState('filter.search'));

		if ($search !== '') {
			$search = '%' . str_replace(' ', '%', $search) . '%';
			$query->where(
				'('
				. $db->quoteName('a.requested_alias') . ' LIKE :search_alias'
				. ' OR ' . $db->quoteName('a.ip_address') . ' LIKE :search_ip'
				. ' OR ' . $db->quoteName('a.referrer') . ' LIKE :search_referrer'
				. ' OR ' . $db->quoteName('a.requested_url') . ' LIKE :search_requested_url'
				. ' OR ' . $db->quoteName('a.user_agent') . ' LIKE :search_user_agent'
				. ')'
			)
				->bind(':search_alias', $search)
				->bind(':search_ip', $search)
				->bind(':search_referrer', $search)
				->bind(':search_requested_url', $search)
				->bind(':search_user_agent', $search);
		}

		foreach (['product_id', 'item_id'] as $field) {
			$value = $this->getState('filter.' . $field);

			if (is_numeric($value) && (int) $value > 0) {
				$value = (int) $value;
				$query->where($db->quoteName('a.' . $field) . ' = :' . $field)->bind(':' . $field, $value, ParameterType::INTEGER);
			}
		}

		foreach (['edition', 'version'] as $field) {
			$value = trim((string) $this->getState('filter.' . $field));

			if ($value !== '') {
				$value = '%' . str_replace(' ', '%', $value) . '%';
				$query->where($db->quoteName('a.' . $field) . ' LIKE :' . $field)->bind(':' . $field, $value);
			}
		}

		$status = trim((string) $this->getState('filter.status'));

		if ($status !== '') {
			$query->where($db->quoteName('a.status') . ' = :status')->bind(':status', $status);
		}

		$dateFrom = trim((string) $this->getState('filter.date_from'));

		if ($dateFrom !== '') {
			$query->where($db->quoteName('a.downloaded_at') . ' >= :date_from')->bind(':date_from', $dateFrom . ' 00:00:00');
		}

		$dateTo = trim((string) $this->getState('filter.date_to'));

		if ($dateTo !== '') {
			$query->where($db->quoteName('a.downloaded_at') . ' <= :date_to')->bind(':date_to', $dateTo . ' 23:59:59');
		}

		$orderCol = $this->state->get('list.ordering', 'a.downloaded_at');
		$orderDirn = $this->state->get('list.direction', 'desc');
		$query->order($db->escape($orderCol . ' ' . $orderDirn));

		return $query;
	}

	public function getExportItems(): array
	{
		$db = $this->getDatabase();
		$query = $this->getListQuery();

		$db->setQuery($query);

		return $db->loadObjectList();
	}

	public function deleteSelected(array $ids): int
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		if ($ids === []) {
			return 0;
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__downloadtracker_logs'))
			->whereIn($db->quoteName('id'), $ids);

		$db->setQuery($query);
		$db->execute();

		return $db->getAffectedRows();
	}
}
