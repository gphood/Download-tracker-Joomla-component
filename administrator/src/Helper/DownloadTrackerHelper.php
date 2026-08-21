<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_downloadtracker
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace GrantHood\Component\DownloadTracker\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use GrantHood\Component\DownloadTracker\Administrator\Service\DownloadLogStatus;

class DownloadTrackerHelper
{
	public static function loadAdminLanguage(): void
	{
		$lang = Factory::getApplication()->getLanguage();
		$lang->load('com_downloadtracker', JPATH_ADMINISTRATOR)
			|| $lang->load('com_downloadtracker', JPATH_ADMINISTRATOR . '/components/com_downloadtracker');
	}

	public static function isSefEnabled(): bool
	{
		return (bool) Factory::getApplication()->get('sef', 0);
	}

	public static function isUrlRewritingEnabled(): bool
	{
		return (bool) Factory::getApplication()->get('sef_rewrite', 0);
	}

	public static function buildPublicDownloadUrl(string $alias, int $menuItemId, string $menuPath): string
	{
		$root = rtrim(Uri::root(), '/') . '/';
		$alias = rawurlencode(ltrim($alias, '/'));
		$menuPath = trim($menuPath, '/');

		if (!self::isSefEnabled()) {
			$query = 'option=com_downloadtracker&task=download.redirect&alias=' . $alias;

			if ($menuItemId > 0) {
				$query .= '&Itemid=' . (int) $menuItemId;
			}

			return $root . 'index.php?' . $query;
		}

		$path = ($menuPath !== '' ? $menuPath . '/' : '') . $alias;

		if (!self::isUrlRewritingEnabled()) {
			return $root . 'index.php/' . $path;
		}

		return $root . $path;
	}

	public static function buildPublicDownloadUrlForAlias(string $alias): string
	{
		$downloadMenuItemId = self::getConfiguredDownloadMenuItemId();
		$downloadMenuPath = '';

		if ($downloadMenuItemId > 0) {
			$db = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)
				->select($db->quoteName(['path', 'alias']))
				->from($db->quoteName('#__menu'))
				->where($db->quoteName('id') . ' = :id')
				->where($db->quoteName('client_id') . ' = 0')
				->bind(':id', $downloadMenuItemId, ParameterType::INTEGER);

			$db->setQuery($query);
			$downloadMenuItem = $db->loadObject();

			if ($downloadMenuItem) {
				$downloadMenuPath = trim((string) ($downloadMenuItem->path ?: $downloadMenuItem->alias), '/');
			}
		}

		if ($downloadMenuPath !== '') {
			return self::buildPublicDownloadUrl($alias, $downloadMenuItemId, $downloadMenuPath);
		}

		return rtrim(Uri::root(), '/') . '/index.php?option=com_downloadtracker&task=download.redirect&alias=' . rawurlencode($alias);
	}

	public static function getConfiguredDownloadMenuItemId(): int
	{
		$value = ComponentHelper::getParams('com_downloadtracker')->get('download_menu_item', 0);

		if (is_numeric($value)) {
			return (int) $value;
		}

		if (is_array($value)) {
			return (int) ($value['id'] ?? $value['value'] ?? reset($value));
		}

		if (is_object($value)) {
			return (int) ($value->id ?? $value->value ?? 0);
		}

		if (preg_match('/^\d+/', (string) $value, $matches)) {
			return (int) $matches[0];
		}

		return 0;
	}

	public static function renderSefWarning(): string
	{
		if (self::isSefEnabled()) {
			return '';
		}

		return '<div class="alert alert-warning">'
			. '<h2 class="h4 alert-heading">' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_HEADING') . '</h2>'
			. '<p>' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_INTRO') . '</p>'
			. '<p><code>' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_EXAMPLE_URL') . '</code></p>'
			. '<p>' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_BEFORE_PUBLIC') . '</p>'
			. '<p><strong>' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_GLOBAL_CONFIG_PATH') . '</strong></p>'
			. '<p>' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_THEN_CHECK') . '</p>'
			. '<ol>'
			. '<li>' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_CHECK_SEF') . '</li>'
			. '<li>' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_CHECK_REWRITE') . '</li>'
			. '<li>' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_CHECK_HTACCESS') . '</li>'
			. '</ol>'
			. '<p class="mb-0">' . Text::_('COM_DOWNLOADTRACKER_SEF_WARNING_NON_BLOCKING') . '</p>'
			. '</div>';
	}

	public static function getLogStatusLabel(string $status): string
	{
		$languageKeys = [
			'downloaded' => 'COM_DOWNLOADTRACKER_STATUS_DOWNLOADED',
			'redirected' => 'COM_DOWNLOADTRACKER_STATUS_REDIRECTED',
			'denied' => 'COM_DOWNLOADTRACKER_STATUS_DENIED',
		];
		$baseStatus = DownloadLogStatus::getBaseStatus($status);

		return isset($languageKeys[$baseStatus]) ? Text::_($languageKeys[$baseStatus]) : $baseStatus;
	}
}
