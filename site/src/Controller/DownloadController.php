<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Exception\RouteNotFoundException;

class DownloadController extends BaseController
{
	public function redirect(): void
	{
		$alias = trim($this->input->getString('alias', ''));

		if ($alias === '') {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		/** @var \GrantHood\Component\DownloadTracker\Site\Model\DownloadModel $model */
		$model = $this->getModel('Download', 'Site');
		$item = $model->getDownloadByAlias($alias);

		if (!$item) {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		if ((int) ($item->requires_token ?? 0) === 1) {
			$token = $this->input->getString('token', '');

			if (!$model->validateToken($item, $token)) {
				throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_AVAILABLE'));
			}
		}

		$sourceType = (string) ($item->source_type ?: 'external');

		if ($sourceType !== 'private_file') {
			$model->logDownload($item, $alias);
			$this->app->redirect((string) $item->target_url, 302);

			return;
		}

		$filePath = $this->resolvePrivateFilePath((string) $item->private_file);

		$model->logDownload($item, $alias, 'downloaded', (string) $item->private_file);
		$this->streamPrivateFile($filePath);
	}

	private function resolvePrivateFilePath(string $privateFile): string
	{
		$params = ComponentHelper::getParams('com_downloadtracker');
		$basePath = trim((string) $params->get('private_downloads_path', ''));
		$privateFile = trim(str_replace('\\', '/', $privateFile));
		$segments = array_filter(explode('/', $privateFile), static fn ($segment) => $segment !== '');

		if (
			$basePath === ''
			|| $privateFile === ''
			|| str_starts_with($privateFile, '/')
			|| preg_match('#^[a-zA-Z]:/#', $privateFile)
			|| str_contains($privateFile, "\0")
			|| str_contains($privateFile, '://')
			|| in_array('..', $segments, true)
		) {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		$basePath = realpath($basePath);

		if ($basePath === false || !is_dir($basePath)) {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		$filePath = realpath($basePath . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments));

		if ($filePath === false || !$this->isPathInsideBase($filePath, $basePath) || !is_file($filePath) || !is_readable($filePath)) {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		return $filePath;
	}

	private function isPathInsideBase(string $filePath, string $basePath): bool
	{
		$basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

		return $filePath === $basePath || str_starts_with($filePath, $basePath . DIRECTORY_SEPARATOR);
	}

	private function streamPrivateFile(string $filePath): void
	{
		$fileName = str_replace(['"', "\r", "\n"], '', basename($filePath));
		$fileSize = filesize($filePath);

		if ($fileSize === false) {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_FOUND'));
		}

		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		$this->app->setHeader('Content-Type', 'application/octet-stream', true);
		$this->app->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"', true);
		$this->app->setHeader('Content-Length', (string) $fileSize, true);
		$this->app->setHeader('Cache-Control', 'private', true);
		$this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$this->app->sendHeaders();

		readfile($filePath);
		$this->app->close();
	}
}
