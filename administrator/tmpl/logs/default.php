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
HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');

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

	<?php if ($this->geolocationSetupNotice !== '') : ?>
		<div class="alert alert-info com-downloadtracker-setup-notice">
			<div class="com-downloadtracker-setup-notice__message">
				<span class="icon-info-circle" aria-hidden="true"></span>
				<span><?php echo $this->escape($this->geolocationSetupNotice); ?></span>
			</div>
			<div class="com-downloadtracker-setup-notice__actions">
				<a class="btn btn-sm btn-primary com-downloadtracker-setup-notice__button com-downloadtracker-setup-notice__button--primary" href="<?php echo Route::_($this->geolocationOptionsUrl); ?>">
					<?php echo Text::_('COM_DOWNLOADTRACKER_GEOLOCATION_SETUP_OPTIONS_BUTTON'); ?>
				</a>
				<a class="btn btn-sm btn-light border com-downloadtracker-setup-notice__button com-downloadtracker-setup-notice__button--secondary" href="https://ipinfo.io/lite" target="_blank" rel="noopener noreferrer">
					<?php echo Text::_('COM_DOWNLOADTRACKER_GEOLOCATION_SETUP_IPINFO_BUTTON'); ?>
				</a>
				<button type="button" class="btn btn-sm btn-light border com-downloadtracker-setup-notice__button com-downloadtracker-setup-notice__button--dismiss" onclick="Joomla.submitbutton('logs.dismissGeolocationSetupNotice');">
					<?php echo Text::_('COM_DOWNLOADTRACKER_GEOLOCATION_SETUP_DISMISS_BUTTON'); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>

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
					$classification = trim((string) $item->ip_classification);
					$classificationLabels = [
						'localhost' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_LOCALHOST'),
						'private_network' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_PRIVATE_NETWORK'),
						'docker_network' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_DOCKER_NETWORK'),
						'reserved' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_RESERVED'),
						'invalid' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_INVALID'),
					];
					$classificationTooltips = [
						'localhost' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_LOCALHOST_TOOLTIP'),
						'private_network' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_PRIVATE_NETWORK_TOOLTIP'),
						'docker_network' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_DOCKER_NETWORK_TOOLTIP'),
						'reserved' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_RESERVED_TOOLTIP'),
						'invalid' => Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_INVALID_TOOLTIP'),
					];
					$countryTooltipParts = array_filter([
						trim((string) $item->country_name) !== '' ? rtrim(trim((string) $item->country_name), '.') . '.' : '',
						trim((string) $item->continent_name) !== '' ? Text::sprintf('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_CONTINENT', trim((string) $item->continent_name)) : '',
						trim((string) $item->asn . ' ' . (string) $item->asn_name) !== '' ? Text::sprintf('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_ASN', trim((string) $item->asn . ' ' . (string) $item->asn_name)) : '',
						trim((string) $item->ip_location_provider) !== '' ? Text::sprintf('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_PROVIDER', Text::_('COM_DOWNLOADTRACKER_PROVIDER_IPINFO_LITE')) : '',
						trim((string) $item->ip_location_checked_at) !== '' ? Text::sprintf('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_CHECKED', trim((string) $item->ip_location_checked_at)) : '',
					], static fn (string $value): bool => $value !== '');
					$countryTooltip = implode(' ', $countryTooltipParts);

					if ($countryTooltip !== '' && substr($countryTooltip, -1) !== '.') {
						$countryTooltip .= '.';
					}

					if ($countryTooltip === '') {
						$countryTooltip = Text::_('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_UNAVAILABLE');
					}
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
							<span>
								<?php echo $this->escape((string) $item->ip_address); ?>
								<?php if (trim((string) $item->country_code) !== '') : ?>
									<span class="badge bg-info text-dark ms-1 hasTooltip com-downloadtracker-ip-badge" title="<?php echo $this->escape($countryTooltip); ?>"><?php echo $this->escape((string) $item->country_code); ?></span>
								<?php elseif (isset($classificationLabels[$classification])) : ?>
									<span class="badge bg-secondary ms-1 hasTooltip com-downloadtracker-ip-badge" title="<?php echo $this->escape($classificationTooltips[$classification] ?? Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_NOT_PUBLIC_TOOLTIP')); ?>"><?php echo $this->escape($classificationLabels[$classification]); ?></span>
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
