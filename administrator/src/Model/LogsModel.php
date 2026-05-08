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
				'ip_address', 'a.ip_address', 'status', 'a.status',
			];
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = 'a.downloaded_at', $direction = 'desc'): void
	{
		$app = Factory::getApplication();
		$this->setState('filter.product_id', $app->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_product_id', '', 'string'));
		$this->setState('filter.item_id', $app->getUserStateFromRequest($this->context . '.filter.item_id', 'filter_item_id', '', 'string'));
		$this->setState('filter.edition', $app->getUserStateFromRequest($this->context . '.filter.edition', 'filter_edition', '', 'string'));
		$this->setState('filter.version', $app->getUserStateFromRequest($this->context . '.filter.version', 'filter_version', '', 'string'));
		$this->setState('filter.date_from', $app->getUserStateFromRequest($this->context . '.filter.date_from', 'filter_date_from', '', 'string'));
		$this->setState('filter.date_to', $app->getUserStateFromRequest($this->context . '.filter.date_to', 'filter_date_to', '', 'string'));

		parent::populateState($ordering, $direction);
	}

	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['a.id', 'a.item_id', 'a.product_id', 'a.downloaded_at', 'a.requested_alias', 'a.edition', 'a.version', 'a.resolved_version', 'a.ip_address', 'a.referrer', 'a.status']))
			->select($db->quoteName('i.title', 'item_title'))
			->select($db->quoteName('p.title', 'product_title'))
			->from($db->quoteName('#__downloadtracker_logs', 'a'))
			->leftJoin($db->quoteName('#__downloadtracker_items', 'i') . ' ON ' . $db->quoteName('i.id') . ' = ' . $db->quoteName('a.item_id'))
			->leftJoin($db->quoteName('#__downloadtracker_products', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('a.product_id'));

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
}
