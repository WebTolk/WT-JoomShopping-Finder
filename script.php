<?php
/**
 * Installer script service provider for the WT JoomShopping Finder package.
 *
 * @package       WT JoomShopping Finder
 * @subpackage    pkg_wtjoomshoppingfinder
 * @author     Sergey Tolkachyov
 * @version       __DEPLOY_VERSION__
 * @copyright  Copyright (c) 2024 - 2026 Sergey Tolkachyov. All rights reserved.
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
	/**
	 * Registers the package installer script in Joomla's service container.
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
			InstallerScriptInterface::class,
			new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
				/**
				 * Joomla administrator application.
				 *
				 * @var    AdministratorApplication
				 * @since  1.0.0
				 */
				private readonly AdministratorApplication $app;

				/**
				 * Joomla database driver.
				 *
				 * @var    DatabaseDriver
				 * @since  1.0.0
				 */
				private readonly DatabaseDriver $db;

				/**
				 * Minimum supported Joomla version.
				 *
				 * @var    string
				 * @since  1.0.0
				 */
				private string $minimumJoomla = '5.4.0';

				/**
				 * Minimum supported PHP version.
				 *
				 * @var    string
				 * @since  1.0.0
				 */
				private string $minimumPhp = '8.1';

				/**
				 * Creates the package installer script.
				 *
				 * @param   AdministratorApplication  $app  Joomla administrator application.
				 *
				 * @since  1.0.0
				 */
				public function __construct(AdministratorApplication $app)
				{
					$this->app = $app;
					$this->db  = Factory::getContainer()->get('DatabaseDriver');
				}

				/**
				 * Handles package installation.
				 *
				 * @param   InstallerAdapter  $adapter  Joomla installer adapter.
				 *
				 * @return bool
				 *
				 * @since  1.0.0
				 */
				public function install(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Handles package uninstallation.
				 *
				 * @param   InstallerAdapter  $adapter  Joomla installer adapter.
				 *
				 * @return bool
				 *
				 * @since  1.0.0
				 */
				public function uninstall(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Handles package updates.
				 *
				 * @param   InstallerAdapter  $adapter  Joomla installer adapter.
				 *
				 * @return bool
				 *
				 * @since  1.0.0
				 */
				public function update(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Checks platform compatibility before install, update, or uninstall.
				 *
				 * @param   string            $type     Installer operation type.
				 * @param   InstallerAdapter  $adapter  Joomla installer adapter.
				 *
				 * @return bool
				 *
				 * @since  1.0.0
				 */
				public function preflight(string $type, InstallerAdapter $adapter): bool
				{
					return $this->checkCompatible();
				}

				/**
				 * Enables bundled plugins and renders the post-install message.
				 *
				 * @param   string            $type     Installer operation type.
				 * @param   InstallerAdapter  $adapter  Joomla installer adapter.
				 *
				 * @return bool
				 *
				 * @since  1.0.0
				 */
				public function postflight(string $type, InstallerAdapter $adapter): bool
				{
					if ($type !== 'uninstall') {
						$this->enablePlugin('finder', 'wtjoomshoppingfinder');
						$this->enablePlugin('jshopping', 'wtjoomshoppingfinderbridge');
					}

					$smile = '';

					if ($type !== 'uninstall') {
						$smiles = ['&#9786;', '&#128512;', '&#128521;', '&#128525;', '&#128526;', '&#128522;', '&#128591;'];
						$smile  = $smiles[array_rand($smiles)];
					}

					$typeUpper = strtoupper($type);
					$html = '
					<div class="row m-0">
						<div class="col-12 col-md-8 p-0 pe-2">
							<h2>' . $smile . ' ' . Text::_('PKG_WTJOOMSHOPPINGFINDER_AFTER_' . $typeUpper) . ' <br>' . Text::_('PKG_WTJOOMSHOPPINGFINDER') . '</h2>
							<p>' . Text::_('PKG_WTJOOMSHOPPINGFINDER_DESC') . '</p>
							' . Text::_('PKG_WTJOOMSHOPPINGFINDER_WHATS_NEW') . '
						</div>
						<div class="col-12 col-md-4 p-0 d-flex flex-column justify-content-start">
							<img width="180" src="https://web-tolk.ru/web_tolk_logo_wide.png" alt="WebTolk">
							<p>Joomla Extensions</p>
							<p class="btn-group">
								<a class="btn btn-sm btn-outline-primary" href="https://web-tolk.ru" target="_blank" rel="noopener noreferrer">https://web-tolk.ru</a>
								<a class="btn btn-sm btn-outline-primary" href="mailto:info@web-tolk.ru"><span class="icon-envelope" aria-hidden="true"></span> info@web-tolk.ru</a>
							</p>
							<div class="btn-group-vertical mb-3 web-tolk-btn-links" role="group" aria-label="WebTolk community links">
								<a class="btn btn-danger text-white w-100" href="https://t.me/joomlaru" target="_blank" rel="noopener noreferrer">' . Text::_('PKG_WTJOOMSHOPPINGFINDER_JOOMLARU_TELEGRAM_CHAT') . '</a>
								<a class="btn btn-primary text-white w-100" href="https://t.me/webtolkru" target="_blank" rel="noopener noreferrer">' . Text::_('PKG_WTJOOMSHOPPINGFINDER_WEBTOLK_TELEGRAM_CHANNEL') . '</a>
								<a class="btn btn-success text-white w-100" href="https://max.ru/join/LChBfwGDmArJpK6--oS0qVAJA1WdRk0OPXciwryF4ZY" target="_blank" rel="noopener noreferrer">' . Text::_('PKG_WTJOOMSHOPPINGFINDER_MAX_CHANNEL') . '</a>
							</div>
							' . Text::_('PKG_WTJOOMSHOPPINGFINDER_MAYBE_INTERESTING') . '
						</div>
					</div>';

					$this->app->enqueueMessage($html, 'info');

					return true;
				}

				/**
				 * Checks Joomla and PHP version compatibility.
				 *
				 * @return bool
				 *
				 * @since  1.0.0
				 */
				private function checkCompatible(): bool
				{
					if (!(new Version())->isCompatible($this->minimumJoomla)) {
						$this->app->enqueueMessage(
							Text::sprintf('PKG_WTJOOMSHOPPINGFINDER_ERROR_COMPATIBLE_JOOMLA', $this->minimumJoomla),
							'error'
						);

						return false;
					}

					if (!(version_compare(PHP_VERSION, $this->minimumPhp) >= 0)) {
						$this->app->enqueueMessage(
							Text::sprintf('PKG_WTJOOMSHOPPINGFINDER_ERROR_COMPATIBLE_PHP', $this->minimumPhp),
							'error'
						);

						return false;
					}

					return true;
				}

				/**
				 * Enables one installed plugin by folder and element.
				 *
				 * @param   string  $folder   Plugin group.
				 * @param   string  $element  Plugin element.
				 *
				 * @return void
				 *
				 * @since  1.0.0
				 */
				private function enablePlugin(string $folder, string $element): void
				{
					$plugin          = new stdClass();
					$plugin->type    = 'plugin';
					$plugin->element = $element;
					$plugin->folder  = $folder;
					$plugin->enabled = 1;

					$this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
				}
			}
		);
	}
};
