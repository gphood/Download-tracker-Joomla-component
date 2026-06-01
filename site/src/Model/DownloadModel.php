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
use Joomla\CMS\Uri\Uri;
use Joomla\Database\ParameterType;

class DownloadModel extends BaseDatabaseModel
{
	public function getDownloadByAlias(string $alias): ?object
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName(['i.id', 'i.product_id', 'i.title', 'i.alias', 'i.edition', 'i.version', 'i.source_type', 'i.target_url', 'i.private_file', 'i.requires_token']))
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

	public function validateToken(object $item, string $token): bool
	{
		if (trim($token) === '') {
			return false;
		}

		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$tokenHash = hash('sha256', $token);
		$itemId = (int) $item->id;
		$query = $db->getQuery(true)
			->update($db->quoteName('#__downloadtracker_tokens'))
			->set($db->quoteName('used_count') . ' = ' . $db->quoteName('used_count') . ' + 1')
			->set($db->quoteName('last_used_at') . ' = :last_used_at')
			->where($db->quoteName('item_id') . ' = :item_id')
			->where($db->quoteName('token_hash') . ' = :token_hash')
			->where($db->quoteName('state') . ' = 1')
			->where('(' . $db->quoteName('expires_at') . ' IS NULL OR ' . $db->quoteName('expires_at') . ' >= :expires_at)')
			->where('(' . $db->quoteName('max_uses') . ' IS NULL OR ' . $db->quoteName('used_count') . ' < ' . $db->quoteName('max_uses') . ')')
			->bind(':last_used_at', $now)
			->bind(':item_id', $itemId, ParameterType::INTEGER)
			->bind(':token_hash', $tokenHash)
			->bind(':expires_at', $now);

		$db->setQuery($query);
		$db->execute();

		return $db->getAffectedRows() === 1;
	}

	public function logDownload(object $item, string $requestedAlias, string $status = 'redirected', ?string $target = null): void
	{
		$app = Factory::getApplication();
		$server = $app->getInput()->server;
		$db = $this->getDatabase();
		$userAgent = $server->getString('HTTP_USER_AGENT', '');
		$requestedUrl = Uri::getInstance()->toString();
		$referrer = $server->getString('HTTP_REFERER', '');

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
			'referrer' => $referrer,
			'requested_url' => $requestedUrl,
			'target_url' => $target ?? (string) $item->target_url,
			'status' => $status,
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
