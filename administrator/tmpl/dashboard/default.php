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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$summaryCards = [
	'total' => [
		'label' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_HUMAN_DOWNLOADS'),
		'help' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_HUMAN_DOWNLOADS_DESC'),
	],
	'today' => ['label' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS_TODAY'), 'help' => ''],
	'last7' => ['label' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS_LAST_7_DAYS'), 'help' => ''],
	'last30' => ['label' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS_LAST_30_DAYS'), 'help' => ''],
];

if ((int) ($this->summary['raw_total'] ?? 0) !== (int) ($this->summary['total'] ?? 0)) {
	$summaryCards['raw_total'] = [
		'label' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_ALL_DOWNLOADS'),
		'help' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_ALL_DOWNLOADS_DESC'),
	];
}

$siteHost = (string) (parse_url(Uri::root(), PHP_URL_HOST) ?: '');
$formatReferrer = static function (string $referrer) use ($siteHost): string {
	if ($referrer === '') {
		return Text::_('COM_DOWNLOADTRACKER_DIRECT_UNAVAILABLE');
	}

	$referrerHost = (string) (parse_url($referrer, PHP_URL_HOST) ?: '');

	if ($siteHost !== '' && strcasecmp($referrerHost, $siteHost) === 0) {
		$path = (string) (parse_url($referrer, PHP_URL_PATH) ?: '/');
		$query = (string) (parse_url($referrer, PHP_URL_QUERY) ?: '');

		return $query === '' ? $path : $path . '?' . $query;
	}

	return $referrer;
};
?>
<div class="com-downloadtracker-dashboard">
	<?php echo DownloadTrackerHelper::renderSefWarning(); ?>

	<div class="row">
		<?php foreach ($summaryCards as $key => $card) : ?>
			<div class="col-sm-6 col-xl-3 mb-3">
				<div class="card">
					<div class="card-body">
						<div class="text-muted"><?php echo $this->escape($card['label']); ?></div>
						<div class="h2 mb-0"><?php echo number_format((int) ($this->summary[$key] ?? 0)); ?></div>
						<?php if ($card['help'] !== '') : ?>
							<div class="small text-muted mt-1"><?php echo $this->escape($card['help']); ?></div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="row">
		<div class="col-lg-6 mb-4">
			<h2><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_TOP_ITEMS'); ?></h2>
			<?php if (empty($this->topItems)) : ?>
				<div class="alert alert-info"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_NO_DOWNLOADS'); ?></div>
			<?php else : ?>
				<table class="table">
					<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_TOP_ITEMS'); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_ITEM'); ?></th>
							<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_PRODUCT'); ?></th>
							<th scope="col" class="text-end"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->topItems as $item) : ?>
							<tr>
								<td><?php echo $this->escape((string) $item->item_title); ?></td>
								<td><?php echo $this->escape((string) $item->product_title); ?></td>
								<td class="text-end"><?php echo number_format((int) $item->download_count); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="col-lg-6 mb-4">
			<h2><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_TOP_REFERRERS'); ?></h2>
			<?php if (empty($this->topReferrers)) : ?>
				<div class="alert alert-info"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_NO_DOWNLOADS'); ?></div>
			<?php else : ?>
				<table class="table">
					<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_TOP_REFERRERS'); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_REFERRER'); ?></th>
							<th scope="col" class="text-end"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->topReferrers as $referrer) : ?>
							<?php
							$fullReferrer = (string) $referrer->referrer;
							$displayReferrer = $formatReferrer($fullReferrer);
							?>
							<tr>
								<td class="com-downloadtracker-referrer-cell" title="<?php echo $this->escape($fullReferrer); ?>">
									<?php echo $this->escape($displayReferrer); ?>
								</td>
								<td class="text-end"><?php echo number_format((int) $referrer->download_count); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-5 mb-4">
			<h2><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS_BY_DAY'); ?></h2>
			<div class="small text-muted mb-2"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_LAST_30_DAYS_HELP'); ?></div>
			<div class="com-downloadtracker-scroll-table">
				<table class="table">
					<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS_BY_DAY'); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php echo Text::_('JDATE'); ?></th>
							<th scope="col" class="text-end"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->downloadsByDay as $day) : ?>
							<tr>
								<td><?php echo HTMLHelper::_('date', $day->download_date, Text::_('DATE_FORMAT_LC4')); ?></td>
								<td class="text-end"><?php echo number_format((int) $day->download_count); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="col-lg-7 mb-4">
			<h2><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_LATEST_LOGS'); ?></h2>
			<?php if (empty($this->latestLogs)) : ?>
				<div class="alert alert-info"><?php echo Text::_('COM_DOWNLOADTRACKER_NO_LOGS'); ?></div>
			<?php else : ?>
				<table class="table">
					<caption class="visually-hidden"><?php echo Text::_('COM_DOWNLOADTRACKER_DASHBOARD_LATEST_LOGS'); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_DOWNLOADED_AT'); ?></th>
							<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_REQUESTED_ALIAS'); ?></th>
							<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_ITEM'); ?></th>
							<th scope="col"><?php echo Text::_('COM_DOWNLOADTRACKER_HEADING_STATUS'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($this->latestLogs as $log) : ?>
							<tr>
								<td><?php echo HTMLHelper::_('date', $log->downloaded_at, Text::_('COM_DOWNLOADTRACKER_DATE_FORMAT_LOG')); ?></td>
								<td><?php echo $this->escape((string) $log->requested_alias); ?></td>
								<td><?php echo $this->escape((string) $log->item_title); ?></td>
								<td><?php echo $this->escape((string) $log->status); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
</div>
