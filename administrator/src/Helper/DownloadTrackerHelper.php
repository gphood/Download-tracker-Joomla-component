<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class DownloadTrackerHelper
{
	public static function loadAdminLanguage(): void
	{
		$lang = Factory::getApplication()->getLanguage();
		$lang->load('com_downloadtracker', JPATH_ADMINISTRATOR)
			|| $lang->load('com_downloadtracker', JPATH_ADMINISTRATOR . '/components/com_downloadtracker');
	}
}
