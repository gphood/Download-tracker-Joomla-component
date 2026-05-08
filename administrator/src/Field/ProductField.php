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

class ProductField extends ListField
{
	protected $type = 'Product';

	protected function getOptions(): array
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->select($db->quoteName(['id', 'title']))
			->from($db->quoteName('#__downloadtracker_products'))
			->where($db->quoteName('state') . ' != -2')
			->order($db->quoteName('title') . ' ASC');

		$db->setQuery($query);

		$options = parent::getOptions();

		foreach ($db->loadObjectList() as $product) {
			$options[] = (object) [
				'value' => (int) $product->id,
				'text' => (string) $product->title,
			];
		}

		return $options;
	}
}
