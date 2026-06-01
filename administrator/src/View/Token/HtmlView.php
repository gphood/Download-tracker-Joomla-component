<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\View\Token;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	protected $form;
	protected $item;
	public array $generatedToken = [];

	public function display($tpl = null): void
	{
		$this->form = $this->get('Form');
		$this->item = $this->get('Item');
		$this->generatedToken = $this->getGeneratedTokenNotice();
		$this->addToolbar();

		Factory::getApplication()->getDocument()->getWebAssetManager()
			->registerAndUseStyle('com_downloadtracker.admin', 'media/com_downloadtracker/css/admin.css');

		parent::display($tpl);
	}

	protected function addToolbar(): void
	{
		$isNew = empty($this->item->id);
		ToolbarHelper::title($isNew ? Text::_('COM_DOWNLOADTRACKER_MANAGER_TOKEN_NEW') : Text::_('COM_DOWNLOADTRACKER_MANAGER_TOKEN_EDIT'), 'key');
		ToolbarHelper::apply('token.apply');
		ToolbarHelper::save('token.save');
		ToolbarHelper::save2new('token.save2new');
		ToolbarHelper::cancel('token.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
	}

	private function getGeneratedTokenNotice(): array
	{
		$app = Factory::getApplication();
		$notice = $app->getUserState('com_downloadtracker.generated_token', []);

		if (is_array($notice) && !empty($notice)) {
			$app->setUserState('com_downloadtracker.generated_token', null);

			return $notice;
		}

		return [];
	}
}
