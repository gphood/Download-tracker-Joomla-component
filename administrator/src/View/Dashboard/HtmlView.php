<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	public array $summary = [];

	public array $topItems = [];

	public array $topReferrers = [];

	public array $downloadsByDay = [];

	public array $latestLogs = [];

	public function display($tpl = null): void
	{
		$this->summary = $this->get('Summary');
		$this->topItems = $this->get('TopItems');
		$this->topReferrers = $this->get('TopReferrers');
		$this->downloadsByDay = $this->get('DownloadsByDay');
		$this->latestLogs = $this->get('LatestLogs');

		ToolbarHelper::title(Text::_('COM_DOWNLOADTRACKER_MANAGER_DASHBOARD'), 'download');

		Factory::getApplication()->getDocument()->getWebAssetManager()
			->registerAndUseStyle('com_downloadtracker.admin', 'media/com_downloadtracker/css/admin.css');

		parent::display($tpl);
	}
}
