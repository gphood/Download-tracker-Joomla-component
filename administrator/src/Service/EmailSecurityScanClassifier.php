<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Service;

\defined('_JEXEC') or die;

final class EmailSecurityScanClassifier
{
	public const MICROSOFT_EMAIL_SECURITY_SCAN = 'microsoft_email_security_scan';

	private const CORRELATION_WINDOW_SECONDS = 300;
	private const MICROSOFT_ASNS = ['AS8075'];

	/**
	 * Return log IDs that are strongly correlated with a Microsoft email-link scan.
	 *
	 * A Microsoft-hosted request is only classified when the same valid token was
	 * also used successfully from a different, non-Microsoft public network within
	 * five minutes. This deliberately favours false negatives over hiding a genuine
	 * customer download.
	 *
	 * @param   array<int, object|array<string, mixed>>  $logs
	 *
	 * @return  array<int, string>  Log ID keyed to classification reason.
	 */
	public function classify(array $logs): array
	{
		$groups = [];

		foreach ($logs as $log) {
			$tokenId = (int) $this->value($log, 'token_id');

			if ($tokenId > 0) {
				$groups[$tokenId][] = $log;
			}
		}

		$classifications = [];

		foreach ($groups as $tokenLogs) {
			foreach ($tokenLogs as $candidate) {
				if (!$this->isCandidate($candidate)) {
					continue;
				}

				foreach ($tokenLogs as $peer) {
					if ($this->correlatesWithCustomerRequest($candidate, $peer)) {
						$classifications[(int) $this->value($candidate, 'id')] = self::MICROSOFT_EMAIL_SECURITY_SCAN;

						break;
					}
				}
			}
		}

		return $classifications;
	}

	private function isCandidate(object|array $log): bool
	{
		return (int) $this->value($log, 'id') > 0
			&& (int) $this->value($log, 'is_bot') === 0
			&& trim((string) $this->value($log, 'bot_reason')) === ''
			&& $this->isSuccessfulTokenRequest($log)
			&& $this->hasSuccessfulLocation($log)
			&& $this->isMicrosoftNetwork($log);
	}

	private function correlatesWithCustomerRequest(object|array $candidate, object|array $peer): bool
	{
		if ((int) $this->value($candidate, 'id') === (int) $this->value($peer, 'id')) {
			return false;
		}

		if ((int) $this->value($peer, 'is_bot') !== 0 || !$this->isSuccessfulTokenRequest($peer)) {
			return false;
		}

		if (!$this->hasSuccessfulLocation($peer) || $this->isMicrosoftNetwork($peer)) {
			return false;
		}

		$candidateItemId = (int) $this->value($candidate, 'item_id');
		$peerItemId = (int) $this->value($peer, 'item_id');

		if ($candidateItemId > 0 && $peerItemId > 0 && $candidateItemId !== $peerItemId) {
			return false;
		}

		$candidateIp = trim((string) $this->value($candidate, 'ip_address'));
		$peerIp = trim((string) $this->value($peer, 'ip_address'));

		if ($candidateIp === '' || $peerIp === '' || $candidateIp === $peerIp) {
			return false;
		}

		$candidateTime = strtotime((string) $this->value($candidate, 'downloaded_at'));
		$peerTime = strtotime((string) $this->value($peer, 'downloaded_at'));

		return $candidateTime !== false
			&& $peerTime !== false
			&& abs($candidateTime - $peerTime) <= self::CORRELATION_WINDOW_SECONDS;
	}

	private function isSuccessfulTokenRequest(object|array $log): bool
	{
		return trim((string) $this->value($log, 'token_status')) === 'valid'
			&& in_array(trim((string) $this->value($log, 'status')), ['downloaded', 'redirected'], true);
	}

	private function hasSuccessfulLocation(object|array $log): bool
	{
		return trim((string) $this->value($log, 'ip_location_status')) === 'success'
			&& (
				trim((string) $this->value($log, 'asn')) !== ''
				|| trim((string) $this->value($log, 'asn_domain')) !== ''
			);
	}

	private function isMicrosoftNetwork(object|array $log): bool
	{
		$asn = strtoupper(trim((string) $this->value($log, 'asn')));
		$domain = strtolower(trim((string) $this->value($log, 'asn_domain')));

		return in_array($asn, self::MICROSOFT_ASNS, true)
			|| $domain === 'microsoft.com'
			|| str_ends_with($domain, '.microsoft.com');
	}

	private function value(object|array $log, string $field): mixed
	{
		if (is_array($log)) {
			return $log[$field] ?? null;
		}

		return $log->{$field} ?? null;
	}
}
