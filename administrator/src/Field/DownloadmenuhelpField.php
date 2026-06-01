<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\SpacerField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Language\Text;

class DownloadmenuhelpField extends SpacerField
{
	protected $type = 'Downloadmenuhelp';

	protected function getInput()
	{
		$steps = '';

		for ($i = 1; $i <= 7; $i++) {
			$steps .= '<li>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_STEP_' . $i), ENT_QUOTES, 'UTF-8') . '</li>';
		}

		return '<details class="downloadtracker-menu-help">'
			. '<summary class="btn btn-sm btn-outline-info mb-2">' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_BUTTON'), ENT_QUOTES, 'UTF-8') . '</summary>'
			. '<div class="alert alert-info mb-0">'
			. '<h3 class="h5">' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_HELP_HEADING'), ENT_QUOTES, 'UTF-8') . '</h3>'
			. '<p>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_HELP_INTRO'), ENT_QUOTES, 'UTF-8') . '</p>'
			. '<p><code>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_EXAMPLE_URL'), ENT_QUOTES, 'UTF-8') . '</code></p>'
			. '<p>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_HELP_BEFORE_CONFIGURING'), ENT_QUOTES, 'UTF-8') . '</p>'
			. '<ol>'
			. '<li>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_HELP_STEP_1'), ENT_QUOTES, 'UTF-8') . '</li>'
			. '<li>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_HELP_STEP_2'), ENT_QUOTES, 'UTF-8') . '</li>'
			. '<li>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_HELP_STEP_3'), ENT_QUOTES, 'UTF-8') . '</li>'
			. '<li>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_HELP_STEP_4'), ENT_QUOTES, 'UTF-8') . '</li>'
			. '</ol>'
			. '<p>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_SEF_HELP_NON_BLOCKING'), ENT_QUOTES, 'UTF-8') . '</p>'
			. '<hr>'
			. '<p>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_INTRO'), ENT_QUOTES, 'UTF-8') . '</p>'
			. '<p><strong>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_RECOMMENDED_SETUP'), ENT_QUOTES, 'UTF-8') . '</strong></p>'
			. '<ol>' . $steps . '</ol>'
			. '<p>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_EXAMPLES_INTRO'), ENT_QUOTES, 'UTF-8') . '</p>'
			. '<p><code>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_EXAMPLE_DOWNLOAD'), ENT_QUOTES, 'UTF-8') . '</code><br>'
			. htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_OR'), ENT_QUOTES, 'UTF-8') . ':<br>'
			. '<code>' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_EXAMPLE_DOWNLOADS'), ENT_QUOTES, 'UTF-8') . '</code></p>'
			. '<p class="mb-0">' . htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_MENU_HELP_ADVANCED'), ENT_QUOTES, 'UTF-8') . '</p>'
			. '</div>'
			. '</details>';
	}

	public function renderField($options = [])
	{
		$rel = '';

		if ($this->showon) {
			Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('showon');
			$rel = ' data-showon=\'' . json_encode(FormHelper::parseShowOnConditions($this->showon, $this->formControl, $this->group)) . '\'';
		}

		return '<div class="control-group field-spacer"' . $rel . '>'
			. '<div class="controls">' . $this->getInput() . '</div>'
			. '</div>';
	}
}
