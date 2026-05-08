<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Exception\RouteNotFoundException;

class DownloadController extends BaseController
{
	public function redirect(): void
	{
		$alias = trim($this->input->getString('alias', ''));

		if ($alias === '') {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		/** @var \GrantHood\Component\DownloadTracker\Site\Model\DownloadModel $model */
		$model = $this->getModel('Download', 'Site');
		$item = $model->getDownloadByAlias($alias);

		if (!$item) {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		$model->logDownload($item);

		$this->app->redirect((string) $item->target_url, 302);
	}
}
