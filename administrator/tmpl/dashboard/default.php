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

$summaryCards = [
	'total' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_TOTAL_DOWNLOADS'),
	'today' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS_TODAY'),
	'last7' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS_LAST_7_DAYS'),
	'last30' => Text::_('COM_DOWNLOADTRACKER_DASHBOARD_DOWNLOADS_LAST_30_DAYS'),
];
?>
<div class="com-downloadtracker-dashboard">
	<div class="row">
		<?php foreach ($summaryCards as $key => $label) : ?>
			<div class="col-sm-6 col-xl-3 mb-3">
				<div class="card">
					<div class="card-body">
						<div class="text-muted"><?php echo $this->escape($label); ?></div>
						<div class="h2 mb-0"><?php echo number_format((int) ($this->summary[$key] ?? 0)); ?></div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="row">
		<div class="col-lg-5 mb-4">
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
								<td><?php echo HTMLHelper::_('date', $log->downloaded_at, Text::_('DATE_FORMAT_LC4')); ?></td>
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
