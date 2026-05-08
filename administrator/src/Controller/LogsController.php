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
use Joomla\CMS\Session\Session;

class LogsController extends BaseController
{
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
			'is_bot',
			'referrer',
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
				(int) $row->is_bot,
				(string) $row->referrer,
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
