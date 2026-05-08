<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterBase;

class Router extends RouterBase
{
	public function build(&$query): array
	{
		$segments = [];

		if (($query['task'] ?? '') === 'download.redirect' && !empty($query['alias'])) {
			$segments[] = (string) $query['alias'];

			unset($query['task'], $query['alias'], $query['view']);
		}

		return $segments;
	}

	public function parse(&$segments): array
	{
		$vars = ['view' => 'download'];

		if (count($segments) > 0) {
			$vars['task'] = 'download.redirect';
			$vars['alias'] = (string) array_shift($segments);
		}

		return $vars;
	}
}
