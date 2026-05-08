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
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class DashboardModel extends BaseDatabaseModel
{
	public function getSummary(): array
	{
		return [
			'total' => $this->getDownloadCount(null, false),
			'today' => $this->getDownloadCount(Factory::getDate('today')->toSql(), false),
			'last7' => $this->getDownloadCount(Factory::getDate('-7 days')->toSql(), false),
			'last30' => $this->getDownloadCount(Factory::getDate('-30 days')->toSql(), false),
			'raw_total' => $this->getDownloadCount(),
		];
	}

	public function getTopItems(): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('a.item_id'))
			->select('COUNT(*) AS ' . $db->quoteName('download_count'))
			->select($db->quoteName('i.title', 'item_title'))
			->select($db->quoteName('p.title', 'product_title'))
			->from($db->quoteName('#__downloadtracker_logs', 'a'))
			->leftJoin($db->quoteName('#__downloadtracker_items', 'i') . ' ON ' . $db->quoteName('i.id') . ' = ' . $db->quoteName('a.item_id'))
			->leftJoin($db->quoteName('#__downloadtracker_products', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('a.product_id'))
			->where($db->quoteName('a.is_bot') . ' = 0')
			->group($db->quoteName(['a.item_id', 'i.title', 'p.title']))
			->order($db->quoteName('download_count') . ' DESC');

		$db->setQuery($query, 0, 5);

		return $db->loadObjectList();
	}

	public function getLatestLogs(): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['a.id', 'a.downloaded_at', 'a.requested_alias', 'a.edition', 'a.version', 'a.resolved_version', 'a.ip_address', 'a.status']))
			->select($db->quoteName('i.title', 'item_title'))
			->select($db->quoteName('p.title', 'product_title'))
			->from($db->quoteName('#__downloadtracker_logs', 'a'))
			->leftJoin($db->quoteName('#__downloadtracker_items', 'i') . ' ON ' . $db->quoteName('i.id') . ' = ' . $db->quoteName('a.item_id'))
			->leftJoin($db->quoteName('#__downloadtracker_products', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('a.product_id'))
			->order($db->quoteName('a.downloaded_at') . ' DESC');

		$db->setQuery($query, 0, 10);

		return $db->loadObjectList();
	}

	private function getDownloadCount(?string $fromDate = null, bool $includeBots = true): int
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__downloadtracker_logs'));

		if (!$includeBots) {
			$query->where($db->quoteName('is_bot') . ' = 0');
		}

		if ($fromDate !== null) {
			$query->where($db->quoteName('downloaded_at') . ' >= :from_date')
				->bind(':from_date', $fromDate);
		}

		$db->setQuery($query);

		return (int) $db->loadResult();
	}
}
