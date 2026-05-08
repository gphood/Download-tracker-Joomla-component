<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class DownloadModel extends BaseDatabaseModel
{
	public function getDownloadByAlias(string $alias): ?object
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['i.id', 'i.product_id', 'i.title', 'i.alias', 'i.edition', 'i.version', 'i.target_url']))
			->from($db->quoteName('#__downloadtracker_items', 'i'))
			->innerJoin($db->quoteName('#__downloadtracker_products', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('i.product_id'))
			->where($db->quoteName('i.alias') . ' = :alias')
			->where($db->quoteName('i.state') . ' = 1')
			->where($db->quoteName('p.state') . ' = 1')
			->bind(':alias', $alias);

		$db->setQuery($query);
		$item = $db->loadObject();

		return $item ?: null;
	}

	public function logDownload(object $item, string $requestedAlias): void
	{
		$app = Factory::getApplication();
		$server = $app->getInput()->server;
		$db = $this->getDatabase();
		$userAgent = $server->getString('HTTP_USER_AGENT', '');

		$log = (object) [
			'item_id' => (int) $item->id,
			'product_id' => (int) $item->product_id,
			'downloaded_at' => Factory::getDate()->toSql(),
			'requested_alias' => $requestedAlias,
			'edition' => $item->edition,
			'version' => $item->version,
			'resolved_version' => $item->version,
			'ip_address' => $server->getString('REMOTE_ADDR', ''),
			'user_agent' => $userAgent,
			'is_bot' => $this->isBotUserAgent($userAgent) ? 1 : 0,
			'referrer' => $server->getString('HTTP_REFERER', ''),
			'target_url' => (string) $item->target_url,
			'status' => 'redirected',
		];

		$db->insertObject('#__downloadtracker_logs', $log);
	}

	private function isBotUserAgent(string $userAgent): bool
	{
		foreach (['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'preview', 'headless', 'lighthouse'] as $term) {
			if (stripos($userAgent, $term) !== false) {
				return true;
			}
		}

		return false;
	}
}
