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

final class DownloadLogStatus
{
	public const TEST_HEADER = 'HTTP_X_DOWNLOADTRACKER_TEST';
	public const TEST_MARKER = 'codex';

	private const SUCCESSFUL_STATUSES = ['downloaded', 'redirected'];

	public static function markTestRequest(string $status, string $marker): string
	{
		if (strcasecmp(trim($marker), self::TEST_MARKER) !== 0 || self::isTest($status)) {
			return $status;
		}

		return 'test_' . $status;
	}

	public static function isTest(string $status): bool
	{
		return str_starts_with($status, 'test_');
	}

	public static function isSuccessful(string $status): bool
	{
		return in_array($status, self::SUCCESSFUL_STATUSES, true);
	}

	public static function getBaseStatus(string $status): string
	{
		return self::isTest($status) ? substr($status, 5) : $status;
	}
}
