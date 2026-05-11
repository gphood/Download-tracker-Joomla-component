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

class IpinfohelpField extends SpacerField
{
	protected $type = 'Ipinfohelp';

	protected function getInput()
	{
		$link = '<a href="https://ipinfo.io/lite" target="_blank" rel="noopener noreferrer">'
			. htmlspecialchars(Text::_('COM_DOWNLOADTRACKER_IPINFO_LITE_TOKEN_LINK_TEXT'), ENT_QUOTES, 'UTF-8')
			. '</a>';

		return '<div class="alert alert-info mb-0">'
			. '<span class="icon-info-circle" aria-hidden="true"></span> '
			. Text::sprintf('COM_DOWNLOADTRACKER_IPINFO_LITE_HELP_HTML', $link)
			. '</div>';
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
