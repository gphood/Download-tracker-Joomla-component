<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use GrantHood\Component\DownloadTracker\Administrator\Helper\DownloadTrackerHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

HTMLHelper::_('behavior.multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$hasActiveFilters = !empty($this->activeFilters);
$params = ComponentHelper::getParams('com_downloadtracker');
$downloadMenuItemValue = $params->get('download_menu_item', 0);
$downloadMenuItemId = 0;

if (is_numeric($downloadMenuItemValue)) {
	$downloadMenuItemId = (int) $downloadMenuItemValue;
} elseif (is_array($downloadMenuItemValue)) {
	$downloadMenuItemId = (int) ($downloadMenuItemValue['id'] ?? $downloadMenuItemValue['value'] ?? reset($downloadMenuItemValue));
} elseif (is_object($downloadMenuItemValue)) {
	$downloadMenuItemId = (int) ($downloadMenuItemValue->id ?? $downloadMenuItemValue->value ?? 0);
} elseif (preg_match('/^\d+/', (string) $downloadMenuItemValue, $matches)) {
	$downloadMenuItemId = (int) $matches[0];
}

$downloadMenuPath = '';
$optionsUrl = Route::_('index.php?option=com_config&view=component&component=com_downloadtracker');

if ($downloadMenuItemId > 0) {
	$db = Factory::getContainer()->get(DatabaseInterface::class);
	$query = $db->getQuery(true)
		->select($db->quoteName(['path', 'alias']))
		->from($db->quoteName('#__menu'))
		->where($db->quoteName('id') . ' = :id')
		->where($db->quoteName('client_id') . ' = 0')
		->bind(':id', $downloadMenuItemId, ParameterType::INTEGER);

	$db->setQuery($query);
	$downloadMenuItem = $db->loadObject();

	if ($downloadMenuItem) {
		$downloadMenuPath = trim((string) ($downloadMenuItem->path ?: $downloadMenuItem->alias), '/');
	}
}
?>
<form action="<?php echo Route::_('index.php?option=com_downloadtracker&view=items'); ?>" method="post" name="adminForm" id="adminForm">
	<?php echo DownloadTrackerHelper::renderSefWarning(); ?>

	<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info"><?php echo Text::_($hasActiveFilters ? 'JGLOBAL_NO_MATCHING_RESULTS' : 'COM_DOWNLOADTRACKER_NO_ITEMS'); ?></div>
	<?php else : ?>
		<table class="table itemList">
			<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_ITEMS_TABLE_CAPTION'); ?></caption>
			<thead>
				<tr>
					<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_PRODUCT', 'p.title', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_EDITION', 'a.edition', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_VERSION', 'a.version', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_ALIAS', 'a.alias', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_DOWNLOAD_URL'); ?></th>
					<th scope="col" class="w-10 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_IS_LATEST', 'a.is_latest', $listDirn, $listOrder); ?></th>
					<th scope="col" class="w-10 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?></th>
					<th scope="col" class="w-15"><?php echo HTMLHelper::_('searchtools.sort', 'JDATE', 'a.created', $listDirn, $listOrder); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $i => $item) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
						<th scope="row">
							<a href="<?php echo Route::_('index.php?option=com_downloadtracker&task=item.edit&id=' . (int) $item->id); ?>">
								<?php echo $this->escape($item->title); ?>
							</a>
						</th>
						<td><?php echo $this->escape((string) $item->product_title); ?></td>
						<td><?php echo $this->escape((string) $item->edition); ?></td>
						<td><?php echo $this->escape((string) $item->version); ?></td>
						<td><?php echo $this->escape($item->alias); ?></td>
						<td>
							<?php if ($downloadMenuPath !== '') : ?>
								<?php $downloadUrl = DownloadTrackerHelper::buildPublicDownloadUrl((string) $item->alias, $downloadMenuItemId, $downloadMenuPath); ?>
								<div class="d-flex gap-2 align-items-center">
									<code><?php echo $this->escape($downloadUrl); ?></code>
									<button
										type="button"
										class="btn btn-sm btn-outline-secondary js-downloadtracker-copy-url"
										data-download-url="<?php echo $this->escape($downloadUrl); ?>"
									>
										<?php echo Text::_('COM_DOWNLOADTRACKER_COPY_URL'); ?>
									</button>
								</div>
							<?php else : ?>
								<span class="text-muted">
									<?php echo Text::sprintf(
										'COM_DOWNLOADTRACKER_SELECT_DOWNLOAD_MENU_ITEM',
										'<a href="' . $this->escape($optionsUrl) . '">' . Text::_('JOPTIONS') . '</a>'
									); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="text-center"><?php echo ((int) $item->is_latest === 1) ? Text::_('JYES') : Text::_('JNO'); ?></td>
						<td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'items.', true, 'cb'); ?></td>
						<td><?php echo $item->created ? HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4')) : ''; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php echo $this->pagination->getListFooter(); ?>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
<script>
document.addEventListener('click', function (event) {
	var button = event.target.closest('.js-downloadtracker-copy-url');

	if (!button || !navigator.clipboard) {
		return;
	}

	navigator.clipboard.writeText(button.getAttribute('data-download-url'));
});
</script>
