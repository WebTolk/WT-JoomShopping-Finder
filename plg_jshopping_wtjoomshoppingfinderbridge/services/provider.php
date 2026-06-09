<?php

/**
 * Service provider for the WT JoomShopping Finder JoomShopping bridge plugin.
 *
 * @package       WT JoomShopping Finder
 * @subpackage    plg_jshopping_wtjoomshoppingfinderbridge
 * @author     Sergey Tolkachyov
 * @copyright  Copyright (c) 2024 - 2026 Sergey Tolkachyov. All rights reserved.
 * @version       __DEPLOY_VERSION__
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\Jshopping\Wtjoomshoppingfinderbridge\Extension\Wtjoomshoppingfinderbridge;

return new class () implements ServiceProviderInterface {
	/**
	 * Registers the JoomShopping bridge plugin instance in Joomla's service container.
	 *
	 * @param   Container  $container  Joomla dependency injection container.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			$container->lazy(Wtjoomshoppingfinderbridge::class, static function (Container $container): PluginInterface {
				$plugin = new Wtjoomshoppingfinderbridge(
					(array) PluginHelper::getPlugin('jshopping', 'wtjoomshoppingfinderbridge')
				);
				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get(DatabaseInterface::class));

				return $plugin;
			})
		);
	}
};
