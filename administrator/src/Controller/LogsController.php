<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class LogsController extends BaseController
{
	public function delete(): void
	{
		Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

		$app = Factory::getApplication();

		if (!$app->getIdentity()->authorise('core.delete', 'com_downloadtracker')) {
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$ids = $app->getInput()->get('cid', [], 'array');

		if ($ids === []) {
			$app->enqueueMessage(Text::_('COM_DOWNLOADTRACKER_WARNING_NO_LOGS_SELECTED'), 'warning');
			$this->setRedirect(Route::_('index.php?option=com_downloadtracker&view=logs', false));

			return;
		}

		/** @var \GrantHood\Component\DownloadTracker\Administrator\Model\LogsModel $model */
		$model = $this->getModel('Logs', 'Administrator');
		$deleted = $model->deleteSelected($ids);

		if ($deleted === 0) {
			$app->enqueueMessage(Text::_('COM_DOWNLOADTRACKER_WARNING_NO_LOGS_SELECTED'), 'warning');
		} else {
			$app->enqueueMessage(Text::plural('COM_DOWNLOADTRACKER_N_LOGS_DELETED', $deleted), 'message');
		}

		$this->setRedirect(Route::_('index.php?option=com_downloadtracker&view=logs', false));
	}

	public function enrichLocations(): void
	{
		Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

		$app = Factory::getApplication();

		if (!$app->getIdentity()->authorise('core.manage', 'com_downloadtracker')) {
			throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var \GrantHood\Component\DownloadTracker\Administrator\Model\LogsModel $model */
		$model = $this->getModel('Logs', 'Administrator');
		$stats = $model->enrichLocations();

		if ($stats['error'] === 'provider') {
			$app->enqueueMessage(Text::_('COM_DOWNLOADTRACKER_WARNING_IP_LOCATION_PROVIDER_DISABLED'), 'warning');
		} elseif ($stats['error'] === 'token') {
			$app->enqueueMessage(Text::_('COM_DOWNLOADTRACKER_WARNING_IPINFO_LITE_TOKEN_MISSING_ENABLED'), 'warning');
		} elseif ((int) $stats['processed'] === 0) {
			$app->enqueueMessage(Text::_('COM_DOWNLOADTRACKER_IP_LOCATION_NO_LOGS'), 'message');
		} else {
			$app->enqueueMessage(
				Text::sprintf(
					'COM_DOWNLOADTRACKER_IP_LOCATION_ENRICHED',
					(int) $stats['processed'],
					(int) $stats['success'],
					(int) $stats['failed'],
					(int) $stats['skipped']
				),
				'message'
			);
		}

		$this->setRedirect(Route::_('index.php?option=com_downloadtracker&view=logs', false));
	}

	public function exportCsv(): void
	{
		Session::checkToken() or die(Text::_('JINVALID_TOKEN'));

		/** @var \GrantHood\Component\DownloadTracker\Administrator\Model\LogsModel $model */
		$model = $this->getModel('Logs', 'Administrator');
		$rows = $model->getExportItems();

		$handle = fopen('php://temp', 'r+');

		if ($handle === false) {
			throw new \RuntimeException(Text::_('COM_DOWNLOADTRACKER_ERROR_CSV_EXPORT_FAILED'));
		}

		$this->writeCsvRow($handle, [
			'downloaded_at',
			'product',
			'item',
			'requested_alias',
			'edition',
			'version',
			'resolved_version',
			'ip_address',
			'ip_classification',
			'country_code',
			'country_name',
			'continent_code',
			'continent_name',
			'asn',
			'asn_name',
			'asn_domain',
			'ip_location_provider',
			'ip_location_checked_at',
			'ip_location_status',
			'is_bot',
			'referrer',
			'requested_url',
			'user_agent',
			'target_url',
			'status',
		]);

		foreach ($rows as $row) {
			$this->writeCsvRow($handle, [
				(string) $row->downloaded_at,
				(string) $row->product_title,
				(string) $row->item_title,
				(string) $row->requested_alias,
				(string) $row->edition,
				(string) $row->version,
				(string) $row->resolved_version,
				(string) $row->ip_address,
				(string) $row->ip_classification,
				(string) $row->country_code,
				(string) $row->country_name,
				(string) $row->continent_code,
				(string) $row->continent_name,
				(string) $row->asn,
				(string) $row->asn_name,
				(string) $row->asn_domain,
				(string) $row->ip_location_provider,
				(string) $row->ip_location_checked_at,
				(string) $row->ip_location_status,
				(int) $row->is_bot,
				(string) $row->referrer,
				(string) $row->requested_url,
				(string) $row->user_agent,
				(string) $row->target_url,
				(string) $row->status,
			]);
		}

		rewind($handle);
		$csv = stream_get_contents($handle);
		fclose($handle);

		$app = Factory::getApplication();
		$filename = 'downloadtracker_logs_' . Factory::getDate()->format('Ymd_His') . '.csv';

		$app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
		$app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
		$app->setHeader('Content-Length', (string) strlen($csv), true);
		$app->sendHeaders();

		echo $csv;

		$app->close();
	}

	/**
	 * @param resource $handle
	 */
	private function writeCsvRow($handle, array $row): void
	{
		fputcsv($handle, $row, ',', '"', '', "\r\n");
	}
}
