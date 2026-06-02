<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.downloadtrackerstripe
 *
 * @copyright   (C) 2026 Grant Hood. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use GrantHood\Plugin\System\DownloadTrackerStripe\Extension\DownloadTrackerStripe;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class () implements ServiceProviderInterface {
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			static function (Container $container): PluginInterface {
				$plugin = new DownloadTrackerStripe(
					$container->get(DispatcherInterface::class),
					(array) PluginHelper::getPlugin('system', 'downloadtrackerstripe')
				);

				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
