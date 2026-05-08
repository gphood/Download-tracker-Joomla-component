<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class ProductTable extends Table
{
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__downloadtracker_products', 'id', $db);

		$this->setColumnAlias('published', 'state');
	}

	public function check(): bool
	{
		if (trim((string) $this->title) === '') {
			$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_TITLE_REQUIRED'));

			return false;
		}

		return true;
	}
}
