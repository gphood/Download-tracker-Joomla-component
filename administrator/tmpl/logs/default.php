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
use GrantHood\Component\DownloadTracker\Administrator\Helper\DownloadTrackerHelper;
use GrantHood\Component\DownloadTracker\Administrator\Service\DownloadLogStatus;

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

	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info"><?php echo Text::_($hasActiveFilters ? 'JGLOBAL_NO_MATCHING_RESULTS' : 'COM_DOWNLOADTRACKER_NO_LOGS'); ?></div>
	<?php else : ?>
		<div class="table-responsive">
		<table class="table itemList com-downloadtracker-logs-table">
			<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_LOGS_TABLE_CAPTION'); ?></caption>
			<colgroup>
				<col class="com-downloadtracker-log-col--select">
				<col class="com-downloadtracker-log-col--date">
				<col class="com-downloadtracker-log-col--download">
				<col class="com-downloadtracker-log-col--release">
				<col class="com-downloadtracker-log-col--visitor">
				<col class="com-downloadtracker-log-col--request">
				<col class="com-downloadtracker-log-col--result">
			</colgroup>
			<thead>
				<tr>
					<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
					<th scope="col" class="com-downloadtracker-nowrap"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_DATE', 'a.downloaded_at', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_DOWNLOAD', 'i.title', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_RELEASE', 'a.version', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_VISITOR', 'a.ip_address', $listDirn, $listOrder); ?></th>
					<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_REQUEST'); ?></th>
					<th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_DOWNLOADTRACKER_HEADING_ACCESS_RESULT', 'a.status', $listDirn, $listOrder); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $i => $item) : ?>
					<?php
					$botReason = trim((string) $item->bot_reason);
					$botReasonLabels = [
						'user_agent_match' => Text::_('COM_DOWNLOADTRACKER_BOT_REASON_USER_AGENT_MATCH'),
						'microsoft_email_security_scan' => Text::_('COM_DOWNLOADTRACKER_BOT_REASON_MICROSOFT_EMAIL_SECURITY_SCAN'),
					];
					$botReasonLabel = $botReasonLabels[$botReason] ?? $botReason;
					$isTestDownload = DownloadLogStatus::isTest((string) $item->status);
					$statusLabel = DownloadTrackerHelper::getLogStatusLabel((string) $item->status);
					$itemTitle = trim((string) $item->item_title);
					$productTitle = trim((string) $item->product_title);
					$downloadMetaParts = [];

					if ($productTitle !== '' && strcasecmp($productTitle, $itemTitle) !== 0) {
						$downloadMetaParts[] = $productTitle;
					}

					if (trim((string) $item->edition) !== '') {
						$downloadMetaParts[] = trim((string) $item->edition);
					}

					$downloadMeta = implode(' · ', $downloadMetaParts);
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
					$locationCheckedAt = trim((string) $item->ip_location_checked_at);
					$locationCheckedAtFormatted = $locationCheckedAt !== ''
						? HTMLHelper::_('date', $locationCheckedAt, Text::_('COM_DOWNLOADTRACKER_DATE_FORMAT_LOG'))
						: '';
					$countryTooltipParts = array_filter([
						trim((string) $item->country_name) !== '' ? rtrim(trim((string) $item->country_name), '.') . '.' : '',
						trim((string) $item->continent_name) !== '' ? Text::sprintf('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_CONTINENT', trim((string) $item->continent_name)) : '',
						trim((string) $item->asn . ' ' . (string) $item->asn_name) !== '' ? Text::sprintf('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_ASN', trim((string) $item->asn . ' ' . (string) $item->asn_name)) : '',
						trim((string) $item->ip_location_provider) !== '' ? Text::sprintf('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_PROVIDER', Text::_('COM_DOWNLOADTRACKER_PROVIDER_IPINFO_LITE')) : '',
						$locationCheckedAtFormatted !== '' ? Text::sprintf('COM_DOWNLOADTRACKER_LOCATION_TOOLTIP_CHECKED', $locationCheckedAtFormatted) : '',
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
						<td class="com-downloadtracker-nowrap">
							<span class="d-block"><?php echo HTMLHelper::_('date', $item->downloaded_at, 'd M Y', true); ?></span>
							<span class="com-downloadtracker-log-meta d-block"><?php echo HTMLHelper::_('date', $item->downloaded_at, 'H:i', true); ?></span>
						</td>
						<td>
							<strong class="d-block"><?php echo $this->escape($itemTitle); ?></strong>
							<?php if ($downloadMeta !== '') : ?>
								<span class="com-downloadtracker-log-meta d-block"><?php echo $this->escape($downloadMeta); ?></span>
							<?php endif; ?>
							<code class="com-downloadtracker-log-code d-block"><?php echo $this->escape((string) $item->requested_alias); ?></code>
						</td>
						<td>
							<div><span class="com-downloadtracker-log-label"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_VERSION'); ?>:</span> <?php echo $this->escape((string) $item->version); ?></div>
							<div class="com-downloadtracker-log-meta"><span class="com-downloadtracker-log-label"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_RESOLVED_VERSION'); ?>:</span> <?php echo $this->escape((string) $item->resolved_version); ?></div>
						</td>
						<td>
							<div class="com-downloadtracker-nowrap">
								<?php echo $this->escape((string) $item->ip_address); ?>
								<?php if (trim((string) $item->country_code) !== '') : ?>
									<span class="badge bg-info ms-1 hasTooltip com-downloadtracker-ip-badge com-downloadtracker-ip-badge--country" title="<?php echo $this->escape($countryTooltip); ?>"><?php echo $this->escape((string) $item->country_code); ?></span>
								<?php elseif (isset($classificationLabels[$classification])) : ?>
									<span class="badge bg-secondary ms-1 hasTooltip com-downloadtracker-ip-badge" title="<?php echo $this->escape($classificationTooltips[$classification] ?? Text::_('COM_DOWNLOADTRACKER_IP_CLASSIFICATION_NOT_PUBLIC_TOOLTIP')); ?>"><?php echo $this->escape($classificationLabels[$classification]); ?></span>
								<?php endif; ?>
							</div>
							<div class="com-downloadtracker-log-meta">
								<span class="com-downloadtracker-log-label"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_IS_BOT'); ?>:</span>
								<?php echo ((int) $item->is_bot === 1) ? Text::_('JYES') : Text::_('JNO'); ?>
							</div>
							<?php if ((int) $item->is_bot === 1 && $botReasonLabel !== '') : ?>
								<small class="text-muted d-block com-downloadtracker-log-reason"><?php echo $this->escape($botReasonLabel); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<div class="com-downloadtracker-log-request-line">
								<span class="com-downloadtracker-log-label"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_REQUESTED_URL'); ?>:</span>
								<span class="com-downloadtracker-log-value--truncate" title="<?php echo $this->escape($requestedUrl); ?>"><?php echo $this->escape($requestedUrl); ?></span>
							</div>
							<div class="com-downloadtracker-log-request-line com-downloadtracker-log-meta">
								<span class="com-downloadtracker-log-label"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_REFERRER'); ?>:</span>
								<span class="com-downloadtracker-log-value--truncate" title="<?php echo $this->escape($displayReferrer); ?>"><?php echo $this->escape($displayReferrer); ?></span>
							</div>
						</td>
						<td>
							<div><span class="com-downloadtracker-log-label"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_TOKEN_PREFIX'); ?>:</span> <?php if (trim((string) $item->token_prefix) !== '') : ?><code><?php echo $this->escape((string) $item->token_prefix); ?></code><?php else : ?>&mdash;<?php endif; ?></div>
							<div class="com-downloadtracker-log-meta"><span class="com-downloadtracker-log-label"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_TOKEN_STATUS'); ?>:</span> <?php echo trim((string) $item->token_status) !== '' ? $this->escape((string) $item->token_status) : '&mdash;'; ?></div>
							<div class="com-downloadtracker-log-meta">
								<span class="com-downloadtracker-log-label"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_STATUS'); ?>:</span>
								<?php if ($isTestDownload) : ?>
									<span class="badge bg-info text-dark"><?php echo Text::_('COM_DOWNLOADTRACKER_BADGE_CODEX_TEST'); ?></span>
								<?php endif; ?>
								<?php echo $this->escape($statusLabel); ?>
							</div>
						</td>
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
