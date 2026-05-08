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
use Joomla\CMS\Form\Field\ListField;
use Joomla\Database\DatabaseInterface;

class ItemField extends ListField
{
	protected $type = 'Item';

	protected function getOptions(): array
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'title']))
			->from($db->quoteName('#__downloadtracker_items'))
			->where($db->quoteName('state') . ' != -2')
			->order($db->quoteName('title') . ' ASC');

		$db->setQuery($query);

		$options = parent::getOptions();

		foreach ($db->loadObjectList() as $item) {
			$options[] = (object) [
				'value' => (int) $item->id,
				'text' => (string) $item->title,
			];
		}

		return $options;
	}
}
