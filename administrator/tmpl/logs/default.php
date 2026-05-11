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
$isDownloadRequestReferrer = static function (string $referrer, string $requestedUrl, string $requestedAlias): bool {
	if ($referrer === '') {
		return true;
	}

	if ($requestedUrl !== '' && $referrer === $requestedUrl) {
		return true;
	}

	if ($requestedAlias === '') {
		return false;
	}

	$encodedAlias = rawurlencode($requestedAlias);
	$path = (string) (parse_url($referrer, PHP_URL_PATH) ?: '');
	$query = (string) (parse_url($referrer, PHP_URL_QUERY) ?: '');

	return (
		(strpos($query, 'option=com_downloadtracker') !== false && strpos($query, 'alias=' . $encodedAlias) !== false)
		|| (bool) preg_match('#/(?:download/)?' . preg_quote($requestedAlias, '#') . '/?$#', $path)
	);
};
?>
<form action="<?php echo Route::_('index.php?option=com_downloadtracker&view=logs'); ?>" method="post" name="adminForm" id="adminForm">
	<?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info"><?php echo Text::_($hasActiveFilters ? 'JGLOBAL_NO_MATCHING_RESULTS' : 'COM_DOWNLOADTRACKER_NO_LOGS'); ?></div>
	<?php else : ?>
		<div class="table-responsive">
		<table class="table itemList com-downloadtracker-logs-table">
			<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_LOGS_TABLE_CAPTION'); ?></caption>
			<thead>
				<tr>
					<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
					<th scope="col" class="com-downloadtracker-nowrap"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_DOWNLOADED_AT', 'a.downloaded_at', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_REQUESTED_ALIAS', 'a.requested_alias', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_ITEM', 'i.title', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_PRODUCT', 'p.title', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_EDITION', 'a.edition', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_VERSION', 'a.version', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_RESOLVED_VERSION', 'a.resolved_version', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_IP_ADDRESS', 'a.ip_address', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_IS_BOT', 'a.is_bot', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_REFERRER'); ?></th>
					<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_REQUESTED_URL'); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_STATUS', 'a.status', $listDirn, $listOrder); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $i => $item) : ?>
					<?php
					$referrer = trim((string) $item->referrer);
					$requestedUrl = trim((string) $item->requested_url);
					$requestedAlias = trim((string) $item->requested_alias);
					$displayReferrer = $isDownloadRequestReferrer($referrer, $requestedUrl, $requestedAlias)
						? Text::_('COM_DOWNLOADTRACKER_DIRECT_UNAVAILABLE')
						: $referrer;
					$locationTitleParts = [];

					foreach ([
						'COM_DOWNLOADTRACKER_LOCATION_COUNTRY' => trim((string) $item->country_name),
						'COM_DOWNLOADTRACKER_LOCATION_CONTINENT' => trim((string) $item->continent_name),
						'COM_DOWNLOADTRACKER_LOCATION_ASN' => trim((string) $item->asn),
						'COM_DOWNLOADTRACKER_LOCATION_ASN_NAME' => trim((string) $item->asn_name),
						'COM_DOWNLOADTRACKER_LOCATION_PROVIDER' => trim((string) $item->ip_location_provider),
						'COM_DOWNLOADTRACKER_LOCATION_CHECKED_AT' => trim((string) $item->ip_location_checked_at),
					] as $label => $value) {
						if ($value !== '') {
							$locationTitleParts[] = Text::_($label) . ': ' . $value;
						}
					}

					$locationTitle = implode(' | ', $locationTitleParts);
					?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
						<td class="com-downloadtracker-nowrap"><?php echo HTMLHelper::_('date', $item->downloaded_at, Text::_('COM_DOWNLOADTRACKER_DATE_FORMAT_LOG')); ?></td>
						<td><?php echo $this->escape((string) $item->requested_alias); ?></td>
						<td><?php echo $this->escape((string) $item->item_title); ?></td>
						<td><?php echo $this->escape((string) $item->product_title); ?></td>
						<td><?php echo $this->escape((string) $item->edition); ?></td>
						<td><?php echo $this->escape((string) $item->version); ?></td>
						<td><?php echo $this->escape((string) $item->resolved_version); ?></td>
						<td class="com-downloadtracker-nowrap">
							<span<?php echo $locationTitle !== '' ? ' title="' . $this->escape($locationTitle) . '"' : ''; ?>>
								<?php echo $this->escape((string) $item->ip_address); ?>
								<?php if (trim((string) $item->country_code) !== '') : ?>
									<span class="badge bg-info text-dark ms-1"><?php echo $this->escape((string) $item->country_code); ?></span>
								<?php endif; ?>
							</span>
						</td>
						<td><?php echo ((int) $item->is_bot === 1) ? Text::_('JYES') : Text::_('JNO'); ?></td>
						<td class="com-downloadtracker-url-cell"><?php echo $this->escape($displayReferrer); ?></td>
						<td class="com-downloadtracker-url-cell"><?php echo $this->escape($requestedUrl); ?></td>
						<td><?php echo $this->escape((string) $item->status); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<?php echo $this->pagination->getListFooter(); ?>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
