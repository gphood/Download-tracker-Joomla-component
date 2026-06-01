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

HTMLHelper::_('behavior.multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$hasActiveFilters = !empty($this->activeFilters);
?>
<?php if (!empty($this->generatedToken)) : ?>
	<div class="alert alert-success">
		<h2 class="h4 alert-heading"><?php echo Text::_('COM_DOWNLOADTRACKER_TOKEN_CREATED_HEADING'); ?></h2>
		<p><?php echo Text::_('COM_DOWNLOADTRACKER_TOKEN_CREATED_MESSAGE'); ?></p>
		<p><strong><?php echo Text::_('COM_DOWNLOADTRACKER_FIELD_TOKEN_LABEL'); ?></strong></p>
		<div class="com-downloadtracker-token-notice__value-row">
			<code class="com-downloadtracker-token-notice__value"><?php echo $this->escape((string) $this->generatedToken['raw_token']); ?></code>
			<button
				type="button"
				class="btn btn-sm btn-outline-secondary com-downloadtracker-token-notice__copy js-downloadtracker-copy-token"
				data-download-token="<?php echo $this->escape((string) $this->generatedToken['raw_token']); ?>"
			>
				<?php echo Text::_('COM_DOWNLOADTRACKER_COPY_TOKEN'); ?>
			</button>
		</div>
		<?php if (!empty($this->generatedToken['download_url'])) : ?>
			<p><strong><?php echo Text::_('COM_DOWNLOADTRACKER_FIELD_PROTECTED_DOWNLOAD_URL_LABEL'); ?></strong></p>
			<div class="com-downloadtracker-token-notice__value-row">
				<code class="com-downloadtracker-token-notice__value"><?php echo $this->escape((string) $this->generatedToken['download_url']); ?></code>
				<button
					type="button"
					class="btn btn-sm btn-outline-secondary com-downloadtracker-token-notice__copy js-downloadtracker-copy-url"
					data-download-url="<?php echo $this->escape((string) $this->generatedToken['download_url']); ?>"
				>
					<?php echo Text::_('COM_DOWNLOADTRACKER_COPY_URL'); ?>
				</button>
			</div>
		<?php endif; ?>
		<p class="com-downloadtracker-token-notice__note"><?php echo Text::_('COM_DOWNLOADTRACKER_TOKEN_CREATED_ONCE_NOTE'); ?></p>
	</div>
<?php endif; ?>
<form action="<?php echo Route::_('index.php?option=com_downloadtracker&view=tokens'); ?>" method="post" name="adminForm" id="adminForm">
	<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info"><?php echo Text::_($hasActiveFilters ? 'JGLOBAL_NO_MATCHING_RESULTS' : 'COM_DOWNLOADTRACKER_NO_TOKENS'); ?></div>
	<?php else : ?>
		<table class="table itemList">
			<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_TOKENS_TABLE_CAPTION'); ?></caption>
			<thead>
				<tr>
					<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_LABEL', 'a.label', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_ITEM', 'i.title', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_CUSTOMER_EMAIL', 'a.customer_email', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_TOKEN_PREFIX', 'a.token_prefix', $listDirn, $listOrder); ?></th>
					<th scope="col" class="w-10 text-center"><?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_EXPIRES_AT', 'a.expires_at', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_TOKEN_USES'); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_LAST_USED_AT', 'a.last_used_at', $listDirn, $listOrder); ?></th>
					<th scope="col" class="w-15"><?php echo HTMLHelper::_('searchtools.sort', 'JDATE', 'a.created', $listDirn, $listOrder); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $i => $item) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
						<th scope="row">
							<a href="<?php echo Route::_('index.php?option=com_downloadtracker&task=token.edit&id=' . (int) $item->id); ?>">
								<?php echo $this->escape((string) ($item->label ?: Text::_('COM_DOWNLOADTRACKER_TOKEN_UNLABELLED'))); ?>
							</a>
						</th>
						<td>
							<div><?php echo $this->escape((string) $item->item_title); ?></div>
							<small class="text-muted"><?php echo $this->escape((string) $item->item_alias); ?></small>
						</td>
						<td><?php echo $this->escape((string) $item->customer_email); ?></td>
						<td><code><?php echo $this->escape((string) $item->token_prefix); ?></code></td>
						<td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'tokens.', true, 'cb'); ?></td>
						<td><?php echo $item->expires_at ? HTMLHelper::_('date', $item->expires_at, Text::_('COM_DOWNLOADTRACKER_DATE_FORMAT_LOG'), true) : Text::_('COM_DOWNLOADTRACKER_NEVER'); ?></td>
						<td>
							<?php echo (int) $item->used_count; ?>
							/
							<?php echo $item->max_uses === null ? Text::_('COM_DOWNLOADTRACKER_UNLIMITED') : (int) $item->max_uses; ?>
						</td>
						<td><?php echo $item->last_used_at ? HTMLHelper::_('date', $item->last_used_at, Text::_('COM_DOWNLOADTRACKER_DATE_FORMAT_LOG'), true) : ''; ?></td>
						<td><?php echo $item->created ? HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC4'), true) : ''; ?></td>
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
	var button = event.target.closest('.js-downloadtracker-copy-url, .js-downloadtracker-copy-token');

	if (!button || !navigator.clipboard) {
		return;
	}

	navigator.clipboard.writeText(button.getAttribute('data-download-url') || button.getAttribute('data-download-token'));
});
</script>
