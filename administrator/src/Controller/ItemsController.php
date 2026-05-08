<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Controller;

\defined('_JEXEC') or die;

use GrantHood\Component\DownloadTracker\Administrator\Helper\DownloadTrackerHelper;
use Joomla\CMS\MVC\Controller\AdminController;

class ItemsController extends AdminController
{
	public function getModel($name = 'Item', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	public function delete()
	{
		DownloadTrackerHelper::loadAdminLanguage();

		return parent::delete();
	}

	public function publish()
	{
		DownloadTrackerHelper::loadAdminLanguage();

		return parent::publish();
	}
}
