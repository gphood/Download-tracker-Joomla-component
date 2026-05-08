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

class ProductsModel extends ListModel
{
	public function __construct($config = [])
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = ['id', 'a.id', 'title', 'a.title', 'alias', 'a.alias', 'state', 'a.state', 'item_count', 'ordering', 'a.ordering'];
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = 'a.title', $direction = 'asc'): void
	{
		$app = Factory::getApplication();
		$this->setState('filter.search', $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search'));
		$this->setState('filter.state', $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string'));

		parent::populateState($ordering, $direction);
	}

	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['a.id', 'a.title', 'a.alias', 'a.state', 'a.ordering']))
			->select('COUNT(' . $db->quoteName('i.id') . ') AS ' . $db->quoteName('item_count'))
			->from($db->quoteName('#__downloadtracker_products', 'a'))
			->leftJoin($db->quoteName('#__downloadtracker_items', 'i') . ' ON ' . $db->quoteName('i.product_id') . ' = ' . $db->quoteName('a.id'))
			->group($db->quoteName(['a.id', 'a.title', 'a.alias', 'a.state', 'a.ordering']));

		$search = trim((string) $this->getState('filter.search'));

		if ($search !== '') {
			if (stripos($search, 'id:') === 0) {
				$id = (int) substr($search, 3);
				$query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
			} else {
				$search = '%' . str_replace(' ', '%', $search) . '%';
				$query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' . $db->quoteName('a.alias') . ' LIKE :search)')
					->bind(':search', $search);
			}
		}

		$published = $this->getState('filter.state');

		if ($published === '*') {
			// Show all states.
		} elseif (is_numeric($published)) {
			$published = (int) $published;
			$query->where($db->quoteName('a.state') . ' = :state')->bind(':state', $published, ParameterType::INTEGER);
		} elseif ($published === '') {
			$query->where($db->quoteName('a.state') . ' != -2');
		}

		$orderCol = $this->state->get('list.ordering', 'a.title');
		$orderDirn = $this->state->get('list.direction', 'asc');
		$query->order($db->escape($orderCol . ' ' . $orderDirn));

		return $query;
	}
}
