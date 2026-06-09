<?php

/**
 * CLI SEF URL diagnostics field for WT JoomShopping Finder.
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
 * Renders live_site diagnostics for CLI SEF route generation.
 *
 * @since  1.0.0
 */
class LivesitenoticeField extends NoteField
{
	/**
	 * Joomla form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'Livesitenotice';

	/**
	 * Returns the live_site diagnostics alert markup.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	protected function getInput(): string
	{
		$liveSite = trim((string) Factory::getConfig()->get('live_site'));
		$textKey  = $liveSite === ''
			? 'PLG_FINDER_WTJOOMSHOPPINGFINDER_FIELD_LIVE_SITE_NOTICE_EMPTY_DESC'
			: 'PLG_FINDER_WTJOOMSHOPPINGFINDER_FIELD_LIVE_SITE_NOTICE_FILLED_DESC';

		return '</div><div class="alert alert-info mb-0 w-100">'
			. '<h4 class="alert-heading">' . Text::_('PLG_FINDER_WTJOOMSHOPPINGFINDER_FIELD_LIVE_SITE_NOTICE_LABEL') . '</h4>'
			. '<p class="mb-0">' . Text::_($textKey) . '</p>'
			. '</div><div>';
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
