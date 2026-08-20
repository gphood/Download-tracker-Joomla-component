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

class TokenTable extends Table
{
	public function __construct(DatabaseDriver $db)
	{
		parent::__construct('#__downloadtracker_tokens', 'id', $db);

		$this->setColumnAlias('published', 'state');
	}

	public function check(): bool
	{
		if ((int) $this->item_id <= 0) {
			$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_ITEM_REQUIRED'));

			return false;
		}

		if (trim((string) $this->token_hash) === '') {
			$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_TOKEN_HASH_REQUIRED'));

			return false;
		}

		$this->purpose = (string) ($this->purpose ?: 'download') === 'update' ? 'update' : 'download';

		if ($this->purpose === 'update') {
			$this->expires_at = null;
			$this->max_uses = null;
		}

		if ($this->max_uses !== null && $this->max_uses !== '' && (int) $this->max_uses < 1) {
			$this->setError(Text::_('COM_DOWNLOADTRACKER_ERROR_MAX_USES_INVALID'));

			return false;
		}

		return true;
	}
}
