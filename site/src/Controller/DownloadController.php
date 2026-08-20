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

use GrantHood\Component\DownloadTracker\Administrator\Helper\DownloadTrackerHelper;
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

		$sourceType = (string) ($item->source_type ?: 'external');
		$filePath = null;

		if ($sourceType === 'private_file') {
			$filePath = $this->resolvePrivateFilePath((string) $item->private_file);
		}

		$tokenResult = null;

		if ((int) ($item->requires_token ?? 0) === 1) {
			$token = $this->input->getString('token', '');
			$tokenResult = $model->validateToken($item, $token);

			if (empty($tokenResult['valid'])) {
				$model->logDownload(
					$item,
					$alias,
					'denied',
					$sourceType === 'private_file' ? (string) $item->private_file : (string) $item->target_url,
					$tokenResult
				);
				throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_DOWNLOAD_NOT_AVAILABLE'));
			}
		}

		if ($sourceType !== 'private_file') {
			$model->logDownload($item, $alias, 'redirected', null, $tokenResult);
			$this->app->redirect((string) $item->target_url, 302);

			return;
		}

		$model->logDownload($item, $alias, 'downloaded', (string) $item->private_file, $tokenResult);
		$this->streamPrivateFile((string) $filePath);
	}

	public function update(): void
	{
		$alias = trim($this->input->getString('alias', ''));

		if ($alias === '') {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_ERROR_UPDATE_NOT_AVAILABLE'));
		}

		/** @var \GrantHood\Component\DownloadTracker\Site\Model\DownloadModel $model */
		$model = $this->getModel('Download', 'Site');
		$item = $model->getUpdateByAlias($alias);

		if (!$item) {
			throw new RouteNotFoundException(Text::_('COM_DOWNLOADTRACKER_ERROR_UPDATE_NOT_AVAILABLE'));
		}

		$document = new \DOMDocument('1.0', 'utf-8');
		$document->formatOutput = true;
		$updates = $document->appendChild($document->createElement('updates'));
		$update = $updates->appendChild($document->createElement('update'));
		$this->appendXmlElement($document, $update, 'name', (string) $item->title);
		$this->appendXmlElement($document, $update, 'description', (string) $item->product_title . ' update package.');
		$this->appendXmlElement($document, $update, 'element', (string) $item->update_element);
		$this->appendXmlElement($document, $update, 'type', (string) $item->update_type);

		if (trim((string) $item->update_folder) !== '') {
			$this->appendXmlElement($document, $update, 'folder', (string) $item->update_folder);
		}

		$this->appendXmlElement($document, $update, 'client', (string) ($item->update_client ?: 'site'));

		$this->appendXmlElement($document, $update, 'version', (string) $item->version);
		$downloads = $update->appendChild($document->createElement('downloads'));
		$downloadUrl = $downloads->appendChild($document->createElement('downloadurl'));
		$downloadUrl->setAttribute('type', 'full');
		$downloadUrl->setAttribute('format', 'zip');
		$downloadUrl->appendChild($document->createTextNode(DownloadTrackerHelper::buildPublicDownloadUrlForAlias($alias)));
		$this->appendXmlElement($document, $update, 'sha256', (string) $item->update_sha256);
		$targetPlatform = $update->appendChild($document->createElement('targetplatform'));
		$targetPlatform->setAttribute('name', 'joomla');
		$targetPlatform->setAttribute('version', (string) ($item->update_targetplatform ?: '[56]\\..*'));
		$this->appendXmlElement($document, $update, 'php_minimum', (string) ($item->update_php_minimum ?: '8.1'));
		$tags = $update->appendChild($document->createElement('tags'));
		$this->appendXmlElement($document, $tags, 'tag', 'stable');
		$this->appendXmlElement($document, $update, 'maintainer', 'Grant Hood');
		$this->appendXmlElement($document, $update, 'maintainerurl', 'https://granthood.co.uk');

		$this->app->setHeader('Content-Type', 'application/xml; charset=utf-8', true);
		$this->app->setHeader('Cache-Control', 'public, max-age=300', true);
		$this->app->sendHeaders();

		echo $document->saveXML();
		$this->app->close();
	}

	private function appendXmlElement(\DOMDocument $document, \DOMNode $parent, string $name, string $value): void
	{
		$element = $document->createElement($name);
		$element->appendChild($document->createTextNode($value));
		$parent->appendChild($element);
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
