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

class ItemsModel extends ListModel
{
	public function __construct($config = [])
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = [
				'id', 'a.id', 'title', 'a.title', 'alias', 'a.alias', 'product_title', 'p.title',
				'edition', 'a.edition', 'version', 'a.version', 'is_latest', 'a.is_latest',
				'requires_token', 'a.requires_token', 'state', 'a.state', 'created', 'a.created', 'ordering', 'a.ordering',
			];
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = 'a.created', $direction = 'desc'): void
	{
		$app = Factory::getApplication();
		$this->setState('filter.search', $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search'));
		$this->setState('filter.state', $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string'));
		$this->setState('filter.product_id', $app->getUserStateFromRequest($this->context . '.filter.product_id', 'filter_product_id', '', 'string'));
		$this->setState('filter.edition', $app->getUserStateFromRequest($this->context . '.filter.edition', 'filter_edition', '', 'string'));
		$this->setState('filter.version', $app->getUserStateFromRequest($this->context . '.filter.version', 'filter_version', '', 'string'));

		parent::populateState($ordering, $direction);
	}

	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['a.id', 'a.product_id', 'a.title', 'a.alias', 'a.edition', 'a.version', 'a.is_latest', 'a.requires_token', 'a.state', 'a.created']))
			->select($db->quoteName('p.title', 'product_title'))
			->from($db->quoteName('#__downloadtracker_items', 'a'))
			->leftJoin($db->quoteName('#__downloadtracker_products', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('a.product_id'));

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

		$productId = $this->getState('filter.product_id');

		if (is_numeric($productId) && (int) $productId > 0) {
			$productId = (int) $productId;
			$query->where($db->quoteName('a.product_id') . ' = :product_id')->bind(':product_id', $productId, ParameterType::INTEGER);
		}

		foreach (['edition', 'version'] as $field) {
			$value = trim((string) $this->getState('filter.' . $field));

			if ($value !== '') {
				$placeholder = ':' . $field;
				$value = '%' . str_replace(' ', '%', $value) . '%';
				$query->where($db->quoteName('a.' . $field) . ' LIKE ' . $placeholder)->bind($placeholder, $value);
			}
		}

		$orderCol = $this->state->get('list.ordering', 'a.created');
		$orderDirn = $this->state->get('list.direction', 'desc');
		$query->order($db->escape($orderCol . ' ' . $orderDirn));

		return $query;
	}
}
