<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$hasActiveFilters = !empty($this->activeFilters);
?>
<form action="<?php echo Route::_('index.php?option=com_downloadtracker&view=logs'); ?>" method="post" name="adminForm" id="adminForm">
	<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info"><?php echo Text::_($hasActiveFilters ? 'JGLOBAL_NO_MATCHING_RESULTS' : 'COM_DOWNLOADTRACKER_NO_LOGS'); ?></div>
	<?php else : ?>
		<table class="table itemList">
			<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_LOGS_TABLE_CAPTION'); ?></caption>
			<thead>
				<tr>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_DOWNLOADED_AT', 'a.downloaded_at', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_REQUESTED_ALIAS', 'a.requested_alias', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_ITEM', 'i.title', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_PRODUCT', 'p.title', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_EDITION', 'a.edition', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_VERSION', 'a.version', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_RESOLVED_VERSION', 'a.resolved_version', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_IP_ADDRESS', 'a.ip_address', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_REFERRER'); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_STATUS', 'a.status', $listDirn, $listOrder); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $item) : ?>
					<tr>
						<td><?php echo HTMLHelper::_('date', $item->downloaded_at, Text::_('DATE_FORMAT_LC4')); ?></td>
						<td><?php echo $this->escape((string) $item->requested_alias); ?></td>
						<td><?php echo $this->escape((string) $item->item_title); ?></td>
						<td><?php echo $this->escape((string) $item->product_title); ?></td>
						<td><?php echo $this->escape((string) $item->edition); ?></td>
						<td><?php echo $this->escape((string) $item->version); ?></td>
						<td><?php echo $this->escape((string) $item->resolved_version); ?></td>
						<td><?php echo $this->escape((string) $item->ip_address); ?></td>
						<td class="com-downloadtracker-target-url"><?php echo $this->escape((string) $item->referrer); ?></td>
						<td><?php echo $this->escape((string) $item->status); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php echo $this->pagination->getListFooter(); ?>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
