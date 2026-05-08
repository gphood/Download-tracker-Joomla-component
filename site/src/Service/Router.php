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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Exception\RouteNotFoundException;

class Router extends RouterBase
{
	public function preprocess($query): array
	{
		if (
			($query['option'] ?? '') === 'com_downloadtracker'
			&& ($query['task'] ?? '') === 'download.redirect'
			&& !empty($query['alias'])
			&& empty($query['Itemid'])
		) {
			$menuItems = $this->menu->getItems('component', 'com_downloadtracker');

			foreach ($menuItems as $menuItem) {
				if (($menuItem->query['view'] ?? '') === 'download' && $menuItem->alias === 'download') {
					$query['Itemid'] = (int) $menuItem->id;
					break;
				}
			}
		}

		return $query;
	}

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

		if (count($segments) === 0) {
			return $vars;
		}

		if (count($segments) !== 1) {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		$alias = trim((string) array_shift($segments));

		if ($alias === '') {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		return [
			'view' => 'download',
			'task' => 'download.redirect',
			'alias' => $alias,
		];
	}
}
