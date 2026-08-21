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
use GrantHood\Component\DownloadTracker\Administrator\Service\DownloadLogStatus;

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

	public function getUpdateByAlias(string $alias): ?object
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName([
				'i.id', 'i.product_id', 'i.title', 'i.alias', 'i.version', 'i.update_element', 'i.update_type',
				'i.update_folder', 'i.update_client', 'i.update_sha256', 'i.update_targetplatform', 'i.update_php_minimum',
			]))
			->select($db->quoteName('p.title', 'product_title'))
			->from($db->quoteName('#__downloadtracker_items', 'i'))
			->innerJoin($db->quoteName('#__downloadtracker_products', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('i.product_id'))
			->where($db->quoteName('i.alias') . ' = :alias')
			->where($db->quoteName('i.state') . ' = 1')
			->where($db->quoteName('i.update_enabled') . ' = 1')
			->where($db->quoteName('p.state') . ' = 1')
			->bind(':alias', $alias);

		$db->setQuery($query);
		$item = $db->loadObject();

		return $item ?: null;
	}

	public function validateToken(object $item, string $token): array
	{
		$token = trim($token);
		$prefix = substr($token, 0, 12);

		if ($token === '') {
			return $this->tokenResult(false, 'missing', null, '');
		}

		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$tokenHash = hash('sha256', $token);
		$itemId = (int) $item->id;
		$lookup = $db->getQuery(true)
			->select($db->quoteName(['id', 'token_prefix', 'state', 'expires_at', 'max_uses', 'used_count']))
			->from($db->quoteName('#__downloadtracker_tokens'))
			->where($db->quoteName('item_id') . ' = :item_id')
			->where($db->quoteName('token_hash') . ' = :token_hash')
			->bind(':item_id', $itemId, ParameterType::INTEGER)
			->bind(':token_hash', $tokenHash);

		$db->setQuery($lookup);
		$storedToken = $db->loadObject();

		if (!$storedToken) {
			return $this->tokenResult(false, 'invalid', null, $prefix);
		}

		$tokenId = (int) $storedToken->id;
		$storedPrefix = (string) ($storedToken->token_prefix ?: $prefix);

		if ((int) $storedToken->state !== 1) {
			return $this->tokenResult(false, 'revoked', $tokenId, $storedPrefix);
		}

		if ($storedToken->expires_at !== null && (string) $storedToken->expires_at < $now) {
			return $this->tokenResult(false, 'expired', $tokenId, $storedPrefix);
		}

		if ($storedToken->max_uses !== null && (int) $storedToken->used_count >= (int) $storedToken->max_uses) {
			return $this->tokenResult(false, 'exhausted', $tokenId, $storedPrefix);
		}

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

		if ($db->getAffectedRows() !== 1) {
			return $this->tokenResult(false, 'unavailable', $tokenId, $storedPrefix);
		}

		return $this->tokenResult(true, 'valid', $tokenId, $storedPrefix);
	}

	public function logDownload(object $item, string $requestedAlias, string $status = 'redirected', ?string $target = null, ?array $tokenResult = null): void
	{
		$app = Factory::getApplication();
		$server = $app->getInput()->server;
		$db = $this->getDatabase();
		$userAgent = $server->getString('HTTP_USER_AGENT', '');
		$botReason = $this->detectBotReason($userAgent);
		$status = DownloadLogStatus::markTestRequest(
			$status,
			$server->getString(DownloadLogStatus::TEST_HEADER, '')
		);
		$requestedUrl = $this->redactTokenFromUrl(Uri::getInstance()->toString());
		$referrer = $this->redactTokenFromUrl($server->getString('HTTP_REFERER', ''));

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
			'is_bot' => $botReason !== null ? 1 : 0,
			'bot_reason' => $botReason,
			'referrer' => $referrer,
			'requested_url' => $requestedUrl,
			'target_url' => $target ?? (string) $item->target_url,
			'token_id' => $tokenResult['token_id'] ?? null,
			'token_prefix' => $tokenResult['token_prefix'] ?? null,
			'token_status' => $tokenResult['status'] ?? null,
			'status' => $status,
		];

		$db->insertObject('#__downloadtracker_logs', $log);
	}

	private function tokenResult(bool $valid, string $status, ?int $tokenId, string $tokenPrefix): array
	{
		return [
			'valid' => $valid,
			'status' => $status,
			'token_id' => $tokenId,
			'token_prefix' => $tokenPrefix !== '' ? $tokenPrefix : null,
		];
	}

	private function redactTokenFromUrl(string $url): string
	{
		if ($url === '') {
			return '';
		}

		return (string) preg_replace('/([?&]token=)[^&#]*/i', '$1[redacted]', $url);
	}

	private function detectBotReason(string $userAgent): ?string
	{
		foreach (['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit', 'preview', 'headless', 'lighthouse'] as $term) {
			if (stripos($userAgent, $term) !== false) {
				return 'user_agent_match';
			}
		}

		return null;
	}
}
