<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\View\Logs;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	public $items;
	public $pagination;
	public $state;
	public $filterForm;
	public $activeFilters;
	public string $geolocationSetupNotice = '';
	public string $geolocationOptionsUrl = '';

	public function display($tpl = null): void
	{
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state = $this->get('State');
		$this->filterForm = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');
		$params = ComponentHelper::getParams('com_downloadtracker');
		$provider = (string) $params->get('ip_geolocation_provider', 'none');
		$token = trim((string) $params->get('ipinfo_lite_token', ''));
		$isConfigured = $provider === 'ipinfo_lite' && $token !== '';
		$showSetupNotice = (int) $params->get('show_geolocation_setup_notice', 1) === 1;
		$this->geolocationOptionsUrl = 'index.php?option=com_config&view=component&component=com_downloadtracker';

		if (!$isConfigured && $showSetupNotice) {
			$this->geolocationSetupNotice = $provider === 'ipinfo_lite'
				? Text::_('COM_DOWNLOADTRACKER_GEOLOCATION_SETUP_NOTICE_TOKEN_MISSING')
				: Text::_('COM_DOWNLOADTRACKER_GEOLOCATION_SETUP_NOTICE_PROVIDER_NONE');
		}

		ToolbarHelper::title(Text::_('COM_DOWNLOADTRACKER_MANAGER_LOGS'), 'list');
		ToolbarHelper::custom('logs.exportCsv', 'download', '', 'COM_DOWNLOADTRACKER_EXPORT_CSV', false);

		if ($isConfigured) {
			ToolbarHelper::custom('logs.enrichLocations', 'location', '', 'COM_DOWNLOADTRACKER_ENRICH_IP_LOCATIONS', false);
		}

		if (ContentHelper::getActions('com_downloadtracker')->get('core.delete')) {
			ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'logs.delete');
		}

		Factory::getApplication()->getDocument()->getWebAssetManager()
			->registerAndUseStyle('com_downloadtracker.admin', 'media/com_downloadtracker/css/admin.css');

		parent::display($tpl);
	}
}
