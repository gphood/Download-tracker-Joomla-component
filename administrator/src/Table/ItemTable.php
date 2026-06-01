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

class ItemTable extends Table
{
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__downloadtracker_items', 'id', $db);

		$this->setColumnAlias('published', 'state');
	}

	public function check(): bool
	{
		if ((int) $this->product_id <= 0) {
			$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_PRODUCT_REQUIRED'));

			return false;
		}

		if (trim((string) $this->title) === '') {
			$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_TITLE_REQUIRED'));

			return false;
		}

		$sourceType = (string) ($this->source_type ?: 'external');

		if (!in_array($sourceType, ['external', 'private_file'], true)) {
			$sourceType = 'external';
		}

		$this->source_type = $sourceType;

		if ($sourceType === 'external' && trim((string) $this->target_url) === '') {
			$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_TARGET_URL_REQUIRED'));

			return false;
		}

		if ($sourceType === 'private_file') {
			$privateFile = trim(str_replace('\\', '/', (string) $this->private_file));
			$segments = array_filter(explode('/', $privateFile), static fn ($segment) => $segment !== '');

			if ($privateFile === '') {
				$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_PRIVATE_FILE_REQUIRED'));

				return false;
			}

			if (
				str_starts_with($privateFile, '/')
				|| preg_match('#^[a-zA-Z]:/#', $privateFile)
				|| str_contains($privateFile, "\0")
				|| str_contains($privateFile, '://')
				|| in_array('..', $segments, true)
			) {
				$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_PRIVATE_FILE_INVALID'));

				return false;
			}

			$this->private_file = implode('/', $segments);
		}

		return true;
	}
}
