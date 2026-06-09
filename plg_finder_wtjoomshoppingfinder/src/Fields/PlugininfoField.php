<?php

/**
 * Plugin information field for WT JoomShopping Finder.
 *
 * @package       WT JoomShopping Finder
 * @subpackage    plg_finder_wtjoomshoppingfinder
 * @author     Sergey Tolkachyov
 * @copyright  Copyright (c) 2024 - 2026 Sergey Tolkachyov. All rights reserved.
 * @version       __DEPLOY_VERSION__
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

namespace Joomla\Plugin\Finder\Wtjoomshoppingfinder\Fields;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\NoteField;
use Joomla\CMS\Language\Text;

/**
 * Renders the WebTolk plugin information block in the Finder plugin form.
 *
 * @since  1.0.0
 */
class PlugininfoField extends NoteField
{
	/**
	 * Joomla form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'Plugininfo';

	/**
	 * Returns the plugin information HTML block.
	 *
	 * @return string
	 * @throws \Exception
	 *
	 * @since  1.0.0
	 */
	protected function getInput(): string
	{
		$data    = $this->form->getData();
		$element = $data->get('element');
		$folder  = $data->get('folder');
		$wa      = Factory::getApplication()->getDocument()->getWebAssetManager();

		$wa->addInlineStyle("
			.plugin-info-img-svg:hover * {
				cursor:pointer;
			}
		");

		$manifest = simplexml_load_file(JPATH_SITE . '/plugins/' . $folder . '/' . $element . '/' . $element . '.xml');

		return '</div><div class="d-flex shadow p-4">
			<div class="flex-shrink-0">
				<a href="https://web-tolk.ru" target="_blank" rel="noopener noreferrer">
					<svg class="plugin-info-img-svg" width="200" height="50" xmlns="http://www.w3.org/2000/svg">
						<g>
							<title>Go to https://web-tolk.ru</title>
							<text font-weight="bold" xml:space="preserve" text-anchor="start"
							      font-family="Helvetica, Arial, sans-serif" font-size="32" id="svg_3" y="36.085949"
							      x="8.152073" stroke-opacity="null" stroke-width="0" stroke="#000"
							      fill="#0fa2e6">Web</text>
							<text font-weight="bold" xml:space="preserve" text-anchor="start"
							      font-family="Helvetica, Arial, sans-serif" font-size="32" id="svg_4" y="36.081862"
							      x="74.239105" stroke-opacity="null" stroke-width="0" stroke="#000"
							      fill="#384148">Tolk</text>
						</g>
					</svg>
				</a>
			</div>
			<div class="flex-grow-1 ms-3">
				<span class="badge bg-success text-white">v.' . $manifest->version . '</span>
				' . Text::_('PLG_' . strtoupper((string) $element) . '_DESC') . '
			</div>
		</div><div>';
	}

	/**
	 * Suppresses the default field label.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	protected function getLabel(): string
	{
		return ' ';
	}

	/**
	 * Returns the field title used by Joomla's form renderer.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	protected function getTitle(): string
	{
		return $this->getLabel();
	}
}
