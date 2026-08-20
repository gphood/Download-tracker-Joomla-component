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

class TokensModel extends ListModel
{
	public function __construct($config = [])
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = [
				'id', 'a.id', 'label', 'a.label', 'item_title', 'i.title', 'item_alias', 'i.alias',
				'customer_email', 'a.customer_email', 'token_prefix', 'a.token_prefix', 'purpose', 'a.purpose', 'state', 'a.state',
				'expires_at', 'a.expires_at', 'used_count', 'a.used_count', 'max_uses', 'a.max_uses',
				'last_used_at', 'a.last_used_at', 'emailed_at', 'a.emailed_at', 'email_count', 'a.email_count',
				'last_email_status', 'a.last_email_status', 'source', 'a.source', 'source_reference', 'a.source_reference',
				'created', 'a.created',
			];
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = 'a.created', $direction = 'desc'): void
	{
		$app = Factory::getApplication();
		$this->setState('filter.search', $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search'));
		$this->setState('filter.state', $app->getUserStateFromRequest($this->context . '.filter.state', 'filter_state', '', 'string'));
		$this->setState('filter.item_id', $app->getUserStateFromRequest($this->context . '.filter.item_id', 'filter_item_id', '', 'string'));

		parent::populateState($ordering, $direction);
	}

	protected function getListQuery()
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['a.id', 'a.item_id', 'a.label', 'a.customer_email', 'a.token_prefix', 'a.purpose', 'a.state', 'a.expires_at', 'a.max_uses', 'a.used_count', 'a.last_used_at', 'a.emailed_at', 'a.emailed_to', 'a.email_count', 'a.last_email_status', 'a.last_email_error', 'a.source', 'a.source_reference', 'a.created']))
			->select($db->quoteName('i.title', 'item_title'))
			->select($db->quoteName('i.alias', 'item_alias'))
			->from($db->quoteName('#__downloadtracker_tokens', 'a'))
			->leftJoin($db->quoteName('#__downloadtracker_items', 'i') . ' ON ' . $db->quoteName('i.id') . ' = ' . $db->quoteName('a.item_id'));

		$search = trim((string) $this->getState('filter.search'));

		if ($search !== '') {
			if (stripos($search, 'id:') === 0) {
				$id = (int) substr($search, 3);
				$query->where($db->quoteName('a.id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
			} else {
				$search = '%' . str_replace(' ', '%', $search) . '%';
				$query->where(
					'('
					. $db->quoteName('a.label') . ' LIKE :search'
					. ' OR ' . $db->quoteName('a.customer_email') . ' LIKE :search'
					. ' OR ' . $db->quoteName('a.token_prefix') . ' LIKE :search'
					. ' OR ' . $db->quoteName('a.purpose') . ' LIKE :search'
					. ' OR ' . $db->quoteName('a.source') . ' LIKE :search'
					. ' OR ' . $db->quoteName('a.source_reference') . ' LIKE :search'
					. ' OR ' . $db->quoteName('i.title') . ' LIKE :search'
					. ' OR ' . $db->quoteName('i.alias') . ' LIKE :search'
					. ')'
				)->bind(':search', $search);
			}
		}

		$state = $this->getState('filter.state');

		if ($state === '*') {
			// Show all states.
		} elseif (is_numeric($state)) {
			$state = (int) $state;
			$query->where($db->quoteName('a.state') . ' = :state')->bind(':state', $state, ParameterType::INTEGER);
		} elseif ($state === '') {
			$query->where($db->quoteName('a.state') . ' != -2');
		}

		$itemId = $this->getState('filter.item_id');

		if (is_numeric($itemId) && (int) $itemId > 0) {
			$itemId = (int) $itemId;
			$query->where($db->quoteName('a.item_id') . ' = :item_id')->bind(':item_id', $itemId, ParameterType::INTEGER);
		}

		$orderCol = $this->state->get('list.ordering', 'a.created');
		$orderDirn = $this->state->get('list.direction', 'desc');
		$query->order($db->escape($orderCol . ' ' . $orderDirn));

		return $query;
	}
}
