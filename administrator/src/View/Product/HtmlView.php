<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\View\Product;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	protected $form;
	protected $item;

	public function display($tpl = null): void
	{
		$this->form = $this->get('Form');
		$this->item = $this->get('Item');
		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		$isNew = empty($this->item->id);
		ToolbarHelper::title($isNew ? Text::_('COM_DOWNLOADTRACKER_MANAGER_PRODUCT_NEW') : Text::_('COM_DOWNLOADTRACKER_MANAGER_PRODUCT_EDIT'), 'download');
		ToolbarHelper::apply('product.apply');
		ToolbarHelper::save('product.save');
		ToolbarHelper::save2new('product.save2new');
		ToolbarHelper::cancel('product.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
	}
}
