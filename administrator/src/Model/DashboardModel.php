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

	public function getTopReferrers(): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['a.referrer', 'a.requested_url', 'a.requested_alias']))
			->select('COUNT(*) AS ' . $db->quoteName('download_count'))
			->from($db->quoteName('#__downloadtracker_logs', 'a'))
			->where($db->quoteName('a.is_bot') . ' = 0')
			->group($db->quoteName(['a.referrer', 'a.requested_url', 'a.requested_alias']))
			->order($db->quoteName('download_count') . ' DESC');

		$db->setQuery($query);
		$rows = $db->loadObjectList();
		$referrers = [];

		foreach ($rows as $row) {
			$referrer = trim((string) $row->referrer);
			$requestedUrl = trim((string) $row->requested_url);
			$requestedAlias = trim((string) $row->requested_alias);

			if ($this->isDownloadRequestReferrer($referrer, $requestedUrl, $requestedAlias)) {
				$referrer = '';
			}

			$key = $referrer === '' ? '' : strtolower($referrer);

			if (!isset($referrers[$key])) {
				$referrers[$key] = (object) [
					'referrer' => $referrer,
					'download_count' => 0,
				];
			}

			$referrers[$key]->download_count += (int) $row->download_count;
		}

		usort(
			$referrers,
			static fn ($a, $b): int => $b->download_count <=> $a->download_count
		);

		return array_slice($referrers, 0, 10);
	}

	public function getDownloadsByDay(): array
	{
		$db = $this->getDatabase();
		$fromDate = Factory::getDate('-29 days')->format('Y-m-d') . ' 00:00:00';
		$query = $db->getQuery(true)
			->select('DATE(' . $db->quoteName('downloaded_at') . ') AS ' . $db->quoteName('download_date'))
			->select('COUNT(*) AS ' . $db->quoteName('download_count'))
			->from($db->quoteName('#__downloadtracker_logs'))
			->where($db->quoteName('is_bot') . ' = 0')
			->where($db->quoteName('downloaded_at') . ' >= :from_date')
			->group('DATE(' . $db->quoteName('downloaded_at') . ')')
			->bind(':from_date', $fromDate);

		$db->setQuery($query);
		$counts = [];

		foreach ($db->loadObjectList() as $row) {
			$counts[(string) $row->download_date] = (int) $row->download_count;
		}

		$days = [];

		for ($offset = 0; $offset < 30; $offset++) {
			$date = Factory::getDate('-' . $offset . ' days')->format('Y-m-d');
			$days[] = (object) [
				'download_date' => $date,
				'download_count' => $counts[$date] ?? 0,
			];
		}

		return $days;
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

	private function isDownloadRequestReferrer(string $referrer, string $requestedUrl, string $requestedAlias): bool
	{
		if ($referrer === '') {
			return true;
		}

		if ($requestedUrl !== '' && $referrer === $requestedUrl) {
			return true;
		}

		if ($requestedAlias === '') {
			return false;
		}

		$encodedAlias = rawurlencode($requestedAlias);
		$path = (string) (parse_url($referrer, PHP_URL_PATH) ?: '');
		$query = (string) (parse_url($referrer, PHP_URL_QUERY) ?: '');

		return (
			(strpos($query, 'option=com_downloadtracker') !== false && strpos($query, 'alias=' . $encodedAlias) !== false)
			|| (bool) preg_match('#/(?:download/)?' . preg_quote($requestedAlias, '#') . '/?$#', $path)
		);
	}
}
