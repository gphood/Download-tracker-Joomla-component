<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\View\Products;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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

	public function display($tpl = null): void
	{
		$this->items = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state = $this->get('State');
		$this->filterForm = $this->get('FilterForm');
		$this->activeFilters = $this->get('ActiveFilters');

		$this->addToolbar();

		Factory::getApplication()->getDocument()->getWebAssetManager()
			->registerAndUseStyle('com_downloadtracker.admin', 'media/com_downloadtracker/css/admin.css');

		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		ToolbarHelper::title(Text::_('COM_DOWNLOADTRACKER_MANAGER_PRODUCTS'), 'download');

		if (ContentHelper::getActions('com_downloadtracker')->get('core.create')) {
			ToolbarHelper::addNew('product.add');
		}

		ToolbarHelper::editList('product.edit');
		ToolbarHelper::publish('products.publish', 'JTOOLBAR_PUBLISH', true);
		ToolbarHelper::unpublish('products.unpublish', 'JTOOLBAR_UNPUBLISH', true);

		if ((string) $this->state->get('filter.state') === '-2') {
			ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'products.delete', 'JTOOLBAR_EMPTY_TRASH');
		} else {
			ToolbarHelper::trash('products.trash');
		}
	}
}
