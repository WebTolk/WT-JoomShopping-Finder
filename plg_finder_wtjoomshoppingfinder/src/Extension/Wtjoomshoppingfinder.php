<?php

/**
 * Smart Search adapter for JoomShopping products.
 *
 * @package       WT JoomShopping Finder
 * @subpackage    plg_finder_wtjoomshoppingfinder
 * @author     Sergey Tolkachyov
 * @copyright  Copyright (c) 2024 - 2026 Sergey Tolkachyov. All rights reserved.
 * @version       __DEPLOY_VERSION__
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

namespace Joomla\Plugin\Finder\Wtjoomshoppingfinder\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Finder as FinderEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper as FinderHelper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseQuery;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die;

/**
 * Smart Search adapter for JoomShopping products.
 *
 * @since  1.0.0
 */
final class Wtjoomshoppingfinder extends Adapter implements SubscriberInterface
{
	use DatabaseAwareTrait;

	/**
	 * Whether Joomla should automatically load plugin language files.
	 *
	 * @var    bool
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Finder adapter context.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $context = 'Wtjoomshoppingfinder';

	/**
	 * Indexed Joomla extension name.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $extension = 'com_jshopping';

	/**
	 * Finder layout name.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $layout = 'product';

	/**
	 * Source product table.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $table = '#__jshopping_products';

	/**
	 * Finder content type title.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type_title = 'Product';

	/**
	 * Cached JoomShopping configuration object.
	 *
	 * @var    object|null
	 * @since  1.0.0
	 */
	private ?object $jshopConfig = null;

	/**
	 * Cached default language tag.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	private string $languageTag = '';

	/**
	 * Cached Joomla language tags used for multilingual indexing.
	 *
	 * @var    array<int, string>|null
	 * @since  1.0.0
	 */
	private ?array $languageTags = null;

	/**
	 * Cached Joomla language SEF codes keyed by full language tag.
	 *
	 * @var    array<string, string>|null
	 * @since  1.0.0
	 */
	private ?array $languageSefCodes = null;

	/**
	 * Cached Joomla multilingual state.
	 *
	 * @var    bool|null
	 * @since  1.0.0
	 */
	private ?bool $multilingualEnabled = null;

	/**
	 * Cached menu Itemid maps keyed by language and access level.
	 *
	 * @var    array<string, array>|null
	 * @since  1.0.0
	 */
	private ?array $itemidMaps = null;

	/**
	 * Whether the JoomShopping bootstrap file has been loaded.
	 *
	 * @var    bool
	 * @since  1.0.0
	 */
	private bool $jshopBooted = false;

	/**
	 * Returns Finder events handled by this adapter.
	 *
	 * @return array<string, string>
	 *
	 * @since  1.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return array_merge(
			[
				'onFinderAfterDelete'         => 'onFinderAfterDelete',
				'onFinderAfterSave'           => 'onFinderAfterSave',
				'onFinderBeforeSave'          => 'onFinderBeforeSave',
				'onFinderCategoryChangeState' => 'onFinderCategoryChangeState',
				'onFinderChangeState'         => 'onFinderChangeState',
			],
			parent::getSubscribedEvents()
		);
	}

	/**
	 * Removes Finder links when a JoomShopping product or Finder index link is deleted.
	 *
	 * @param   FinderEvent\AfterDeleteEvent  $event  Finder delete event.
	 *
	 * @return void
	 * @throws \Exception
	 *
	 * @since  1.0.0
	 */
	public function onFinderAfterDelete(FinderEvent\AfterDeleteEvent $event): void
	{
		$context = $event->getContext();
		$item    = $event->getItem();

		if ($context === 'com_finder.index') {
			$this->remove((int) $item->link_id);

			return;
		}

		if ($context !== 'com_jshopping.product') {
			return;
		}

		$id = $this->getProductIdFromObject($item);

		if ($id > 0) {
			$this->removeProductLinks($id, false);
		}
	}

	/**
	 * Reindexes products after JoomShopping product or category saves.
	 *
	 * @param   FinderEvent\AfterSaveEvent  $event  Finder save event.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onFinderAfterSave(FinderEvent\AfterSaveEvent $event): void
	{
		$context = $event->getContext();
		$item    = $event->getItem();

		if ($context === 'com_jshopping.product') {
			$id = $this->getProductIdFromObject($item);

			if ($id > 0) {
				$this->reindexOrDisableProduct($id);
			}

			return;
		}

		if ($context === 'com_jshopping.category') {
			$id = $this->getCategoryIdFromObject($item);

			if ($id > 0) {
				$this->reindexProducts($this->getProductIdsByCategories([$id]));
			}
		}
	}

	/**
	 * Allows Finder save processing for bridged JoomShopping items.
	 *
	 * @param   FinderEvent\BeforeSaveEvent  $event  Finder before-save event.
	 *
	 * @return bool
	 *
	 * @since  1.0.0
	 */
	public function onFinderBeforeSave(FinderEvent\BeforeSaveEvent $event): bool
	{
		return true;
	}

	/**
	 * Applies category publish state changes to indexed JoomShopping product links.
	 *
	 * @param   FinderEvent\AfterCategoryChangeStateEvent  $event  Category state event.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onFinderCategoryChangeState(FinderEvent\AfterCategoryChangeStateEvent $event): void
	{
		if ($event->getExtension() !== 'com_jshopping') {
			return;
		}

		$productIds = $this->getProductIdsByCategories($event->getPks());

		if ((int) $event->getValue() === 1) {
			$this->reindexProducts($productIds);

			return;
		}

		foreach ($productIds as $productId) {
			$this->changeProductLinks($productId, 'state', 0);
		}
	}

	/**
	 * Applies product or plugin state changes to Finder links.
	 *
	 * @param   FinderEvent\AfterChangeStateEvent  $event  Finder state event.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onFinderChangeState(FinderEvent\AfterChangeStateEvent $event): void
	{
		$context = $event->getContext();

		if ($context === 'com_jshopping.product') {
			$this->itemStateChange($event->getPks(), (int) $event->getValue());

			return;
		}

		if ($context === 'com_plugins.plugin' && (int) $event->getValue() === 0) {
			$this->pluginDisable($event->getPks());
		}
	}

	/**
	 * Loads one product item for the base Finder adapter flow.
	 *
	 * @param   int  $id  Product id.
	 *
	 * @return Result
	 *
	 * @since  1.0.0
	 */
	protected function getItem($id): Result
	{
		$query = $this->getListQuery();
		$query->where($this->db->quoteName('prod.product_id') . ' = ' . (int) $id);

		$this->db->setQuery($query);
		$item = $this->db->loadAssoc();

		if (!$item) {
			$item = [
				'id'       => (int) $id,
				'slug'     => (int) $id,
				'state'    => 0,
				'cat_state'=> 0,
				'language' => $this->getDefaultLanguageTag(),
			];
		}

		$item = ArrayHelper::toObject($item, Result::class);
		/** @var Result $item */
		$item->type_id = $this->type_id;
		$item->layout  = $this->layout;

		return $item;
	}

	/**
	 * Builds the base product list query.
	 *
	 * @param   DatabaseQuery|null  $query  Optional query instance to extend.
	 *
	 * @return DatabaseQuery
	 *
	 * @since  1.0.0
	 */
	protected function getListQuery($query = null): DatabaseQuery
	{
		return $this->getListQueryForLanguage($query);
	}

	/**
	 * Loads product rows for the Finder adapter batch process.
	 *
	 * @param   int                 $offset  Query offset.
	 * @param   int                 $limit   Query limit.
	 * @param   DatabaseQuery|null  $query   Optional query instance to extend.
	 *
	 * @return array<int, Result>
	 *
	 * @since  1.0.0
	 */
	protected function getItems($offset, $limit, $query = null): array
	{
		$this->db->setQuery($this->getListQuery($query)->setLimit($limit, $offset));
		$items = $this->db->loadAssocList();

		foreach ($items as &$item) {
			$item = ArrayHelper::toObject($item, Result::class);
			$item->type_id = $this->type_id;
			$item->mime    = $this->mime;
			$item->layout  = $this->layout;
		}

		return $items;
	}

	/**
	 * Indexes one JoomShopping product in every configured Joomla language.
	 *
	 * @param   Result  $item  Finder item produced by the adapter list query.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	protected function index(Result $item): void
	{
		if (ComponentHelper::isEnabled($this->extension) === false || (int) $item->state !== 1) {
			return;
		}

		$this->loadJshopConfig();
		$this->removeProductLinks((int) $item->id, false);

		foreach ($this->getLanguageTags() as $language) {
			$languageItem = $this->getItemForLanguage((int) $item->id, $language);

			if ($languageItem === null) {
				continue;
			}

			$this->indexLanguageItem($languageItem);
		}
	}

	/**
	 * Builds the canonical Finder URL for a JoomShopping product.
	 *
	 * @param   int|string  $id         Product id.
	 * @param   string      $extension  Extension name.
	 * @param   string      $view       View or layout name.
	 * @param   string      $language   Optional Joomla language tag.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	public function getUrl($id, $extension, $view, string $language = ''): string
	{
		$url = 'index.php?option=com_jshopping&controller=product&task=view&product_id=' . (int) $id;
		$languageCode = $this->getRouteLanguageCode($language);

		if ($languageCode !== '') {
			$url .= '&lang=' . $languageCode;
		}

		return $url;
	}

	/**
	 * Applies product state changes to Finder links.
	 *
	 * @param   array<int, int>|int  $pks    Product ids.
	 * @param   int|string          $value  New state value.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	protected function itemStateChange($pks, $value): void
	{
		foreach ((array) $pks as $pk) {
			if ((int) $value === 1) {
				$this->reindexOrDisableProduct((int) $pk);

				continue;
			}

			$this->changeProductLinks((int) $pk, 'state', 0);
		}
	}

	/**
	 * Boots JoomShopping and prepares adapter runtime dependencies.
	 *
	 * @return bool
	 *
	 * @since  1.0.0
	 */
	protected function setup(): bool
	{
		if (!$this->bootJoomShopping()) {
			Log::add('JoomShopping bootstrap was not found. WT JoomShopping Finder indexing skipped.', Log::WARNING, 'finder');

			return false;
		}

		$this->loadJshopConfig();

		if ($this->getApplication()->isClient('cli') && trim((string) Factory::getConfig()->get('live_site')) === '') {
			$message = Text::_('PLG_FINDER_WTJOOMSHOPPINGFINDER_LIVE_SITE_EMPTY_WARNING');
			$this->getApplication()->enqueueMessage($message, 'warning');
			Log::add($message, Log::WARNING, 'finder');
		}

		return true;
	}

	/**
	 * Merges product params with component and JoomShopping configuration.
	 *
	 * @param   Result  $item  Finder product item.
	 *
	 * @return Registry
	 *
	 * @since  1.0.0
	 */
	private function buildItemParams(Result $item): Registry
	{
		$registry = new Registry($item->params ?? '');
		$params   = clone ComponentHelper::getParams('com_jshopping', true);
		$params->merge($registry);
		$params->merge(new Registry((array) $this->jshopConfig));

		return $params;
	}

	/**
	 * Loads the JoomShopping bootstrap file once.
	 *
	 * @return bool
	 *
	 * @since  1.0.0
	 */
	private function bootJoomShopping(): bool
	{
		if ($this->jshopBooted) {
			return true;
		}

		$bootstrap = JPATH_SITE . '/components/com_jshopping/bootstrap.php';

		if (!is_file($bootstrap)) {
			return false;
		}

		if (!class_exists('JSFactory', false)) {
			require_once $bootstrap;
		}

		$this->jshopBooted = class_exists('JSFactory', false);

		return $this->jshopBooted;
	}

	/**
	 * Returns the cached JoomShopping configuration object.
	 *
	 * @return object
	 *
	 * @since  1.0.0
	 */
	private function loadJshopConfig(): object
	{
		if ($this->jshopConfig !== null) {
			return $this->jshopConfig;
		}

		$this->bootJoomShopping();
		$this->jshopConfig = \JSFactory::getConfig();

		return $this->jshopConfig;
	}

	/**
	 * Resolves the default JoomShopping language tag.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function getDefaultLanguageTag(): string
	{
		if ($this->languageTag !== '') {
			return $this->languageTag;
		}

		$this->loadJshopConfig();
		$this->languageTag = (string) ($this->jshopConfig->getFrontLang() ?: $this->jshopConfig->getLang() ?: $this->jshopConfig->defaultLanguage);

		if (strlen($this->languageTag) === 2 && strpos((string) $this->jshopConfig->defaultLanguage, $this->languageTag . '-') === 0) {
			$this->languageTag = (string) $this->jshopConfig->defaultLanguage;
		}

		return $this->languageTag;
	}

	/**
	 * Returns all Joomla language tags that should receive product index records.
	 *
	 * @return array<int, string>
	 *
	 * @since  1.0.0
	 */
	private function getLanguageTags(): array
	{
		if ($this->languageTags !== null) {
			return $this->languageTags;
		}

		if (!$this->isMultilingualEnabled()) {
			$this->languageTags = [$this->getDefaultLanguageTag()];

			return $this->languageTags;
		}

		$languages = LanguageHelper::getLanguages('lang_code');
		$tags = [];

		foreach ($languages as $language) {
			$tag = (string) ($language->lang_code ?? '');

			if ($tag !== '') {
				$tags[] = $tag;
			}
		}

		if (!$tags) {
			$tags[] = $this->getDefaultLanguageTag();
		}

		$this->languageTags = array_values(array_unique($tags));

		return $this->languageTags;
	}

	/**
	 * Loads a localized Finder result for one JoomShopping product.
	 *
	 * @param   int     $productId  Product id.
	 * @param   string  $language   Joomla language tag.
	 *
	 * @return Result|null
	 *
	 * @since  1.0.0
	 */
	private function getItemForLanguage(int $productId, string $language): ?Result
	{
		$query = $this->getListQueryForLanguage(null, $language);
		$query->where($this->db->quoteName('prod.product_id') . ' = ' . $productId);

		$this->db->setQuery($query);
		$item = $this->db->loadAssoc();

		if (!$item) {
			return null;
		}

		$item = ArrayHelper::toObject($item, Result::class);
		/** @var Result $item */
		$item->type_id = $this->type_id;
		$item->mime    = $this->mime;
		$item->layout  = $this->layout;

		return $item;
	}

	/**
	 * Builds a localized product list query for Finder indexing.
	 *
	 * @param   DatabaseQuery|null  $query     Optional query instance to extend.
	 * @param   string|null         $language  Optional Joomla language tag.
	 *
	 * @return DatabaseQuery
	 *
	 * @since  1.0.0
	 */
	private function getListQueryForLanguage(?DatabaseQuery $query = null, ?string $language = null): DatabaseQuery
	{
		$this->loadJshopConfig();

		$db       = $this->db;
		$tag      = $language ?: $this->getDefaultLanguageTag();
		$name     = $this->resolveLanguageField('#__jshopping_products', 'name', $tag);
		$alias    = $this->resolveLanguageField('#__jshopping_products', 'alias', $tag);
		$desc     = $this->resolveLanguageField('#__jshopping_products', 'description', $tag);
		$short    = $this->resolveLanguageField('#__jshopping_products', 'short_description', $tag);
		$metaKey  = $this->resolveLanguageField('#__jshopping_products', 'meta_keyword', $tag);
		$metaDesc = $this->resolveLanguageField('#__jshopping_products', 'meta_description', $tag);
		$catName  = $this->resolveLanguageField('#__jshopping_categories', 'name', $tag);
		$manName  = $this->resolveLanguageField('#__jshopping_manufacturers', 'name', $tag);

		$query = ($query instanceof DatabaseQuery) ? $query : $db->getQuery(true);
		$query->select(
			[
				$db->quoteName('prod.product_id', 'id'),
				$db->quoteName('prod.product_id', 'slug'),
				$db->quoteName('prod.' . $name, 'title'),
				$db->quoteName('prod.' . $alias, 'alias'),
				$db->quoteName('prod.' . $desc, 'body'),
				$db->quoteName('prod.' . $short, 'summary'),
				$db->quoteName('prod.' . $metaKey, 'metakey'),
				$db->quoteName('prod.' . $metaDesc, 'metadesc'),
				$db->quoteName('prod.product_date_added', 'created'),
				$db->quoteName('prod.product_publish', 'state'),
				$db->quoteName('prod.access', 'access'),
				$db->quoteName('prod.product_ean'),
				$db->quoteName('man.' . $manName, 'manufacturer'),
				$db->quoteName('prod.manufacturer_code'),
				$db->quoteName('prod.product_old_price'),
				$db->quoteName('prod.product_price'),
				$db->quoteName('prod.product_buy_price'),
				$db->quoteName('prod.min_price'),
				$db->quoteName('prod.product_weight'),
				$db->quoteName('prod.image', 'image'),
				$db->quoteName('cat.category_id', 'catid'),
				$db->quoteName('cat.category_id', 'catslug'),
				$db->quoteName('cat.category_publish', 'cat_state'),
				$db->quoteName('cat.access', 'cat_access'),
				$db->quoteName('cat.' . $catName, 'category'),
				$db->quote($tag) . ' AS ' . $db->quoteName('language'),
			]
		)
			->from($db->quoteName('#__jshopping_products', 'prod'))
			->join(
				'LEFT',
				$db->quoteName('#__jshopping_manufacturers', 'man')
				. ' ON ' . $db->quoteName('man.manufacturer_id') . ' = ' . $db->quoteName('prod.product_manufacturer_id')
				. ' AND ' . $db->quoteName('man.manufacturer_publish') . ' = 1'
			)
			->where($db->quoteName('prod.product_publish') . ' = 1')
			->where($db->quoteName('cat.category_publish') . ' = 1');

		if ((int) ($this->jshopConfig->product_use_main_category_id ?? 0) === 1) {
			$query->join(
				'INNER',
				$db->quoteName('#__jshopping_categories', 'cat')
				. ' ON ' . $db->quoteName('cat.category_id') . ' = ' . $db->quoteName('prod.main_category_id')
			);

			return $query;
		}

		$subQuery = $db->getQuery(true)
			->select($db->quoteName('pr_cat.product_id'))
			->select('MIN(' . $db->quoteName('pr_cat.category_id') . ') AS ' . $db->quoteName('category_id'))
			->from($db->quoteName('#__jshopping_products_to_categories', 'pr_cat'))
			->group($db->quoteName('pr_cat.product_id'));

		$query->join(
			'INNER',
			'(' . $subQuery . ') AS ' . $db->quoteName('product_category')
			. ' ON ' . $db->quoteName('product_category.product_id') . ' = ' . $db->quoteName('prod.product_id')
		)
			->join(
				'INNER',
				$db->quoteName('#__jshopping_categories', 'cat')
				. ' ON ' . $db->quoteName('cat.category_id') . ' = ' . $db->quoteName('product_category.category_id')
			);

		return $query;
	}

	/**
	 * Prepares and stores one localized Finder product item.
	 *
	 * @param   Result  $item  Localized Finder product item.
	 *
	 * @return void
	 * @throws \Exception
	 *
	 * @since  1.0.0
	 */
	private function indexLanguageItem(Result $item): void
	{
		$this->setJoomShoppingLanguage((string) $item->language);

		$item->context = 'com_jshopping.product';
		$item->params  = $this->buildItemParams($item);
		$item->metadata = new Registry($item->metadata ?? '');

		$extraContent  = $this->getProductCharacteristicsContent((int) $item->id, (string) $item->language);
		$extraContent .= ' ' . $this->getProductAttributesContent((int) $item->id, (string) $item->language);
		$extraContent .= ' ' . $this->getProductPriceContent($item);

		$item->summary = FinderHelper::prepareContent((string) $item->summary, $item->params, $item);
		$item->body    = FinderHelper::prepareContent(trim((string) $item->body . ' ' . $extraContent), $item->params, $item);
		$item->access  = max((int) ($item->access ?? 1), (int) ($item->cat_access ?? 1));
		$item->url     = $this->getUrl((int) $item->id, $this->extension, $this->layout, (string) $item->language);
		$item->route   = $this->getProductRoute(
			(int) $item->id,
			(int) $item->catslug,
			(string) $item->language,
			(string) $item->alias !== '',
			(int) $item->access
		);
		$item->state   = $this->translateState((int) $item->state, (int) $item->cat_state);

		if (!empty($item->image) && !empty($this->jshopConfig->image_product_live_path)) {
			$item->imageUrl = rtrim((string) $this->jshopConfig->image_product_live_path, '/') . '/' . $item->image;
			$item->imageAlt = $item->title;
		}

		$item->addInstruction(Indexer::META_CONTEXT, 'metakey');
		$item->addInstruction(Indexer::META_CONTEXT, 'metadesc');

		$taxonomies = $this->getConfiguredTaxonomies();

		if (in_array('type', $taxonomies, true)) {
			$item->addTaxonomy('Type', 'Product');
		}

		if (in_array('category', $taxonomies, true) && !empty($item->category)) {
			$item->addTaxonomy('Category', $item->category, (int) $item->state, (int) $item->cat_access, (string) $item->language);
		}

		if (in_array('language', $taxonomies, true)) {
			$item->addTaxonomy('Language', (string) $item->language, 1, 1, (string) $item->language);
		}

		$item->publish_start_date = Factory::getDate()->toSql();
		$item->start_date         = $item->publish_start_date;

		FinderHelper::getContentExtras($item);
		$this->indexer->index($item);
	}

	/**
	 * Returns enabled Finder taxonomy names from plugin parameters.
	 *
	 * @return array<int, string>
	 *
	 * @since  1.0.0
	 */
	private function getConfiguredTaxonomies(): array
	{
		$taxonomies = $this->params->get('taxonomies', ['type', 'category', 'language']);

		if (is_string($taxonomies)) {
			$taxonomies = explode(',', $taxonomies);
		}

		if (!is_array($taxonomies)) {
			return [];
		}

		$taxonomies = array_map(
			static fn($taxonomy): string => strtolower(trim((string) $taxonomy)),
			$taxonomies
		);

		return array_values(array_unique(array_filter(
			$taxonomies,
			static fn(string $taxonomy): bool => $taxonomy !== '' && $taxonomy !== 'none'
		)));
	}

	/**
	 * Returns the JoomShopping language helper for a Joomla language tag.
	 *
	 * @param   string  $tag  Joomla language tag.
	 *
	 * @return object
	 *
	 * @since  1.0.0
	 */
	private function getJoomShoppingLanguage(string $tag): object
	{
		$this->bootJoomShopping();

		return \JSFactory::getLang($tag);
	}

	/**
	 * Resolves a localized JoomShopping database column name.
	 *
	 * @param   string  $table      Table name.
	 * @param   string  $baseField  Base column name.
	 * @param   string  $tag        Joomla language tag.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function resolveLanguageField(string $table, string $baseField, string $tag): string
	{
		$columns = $this->db->getTableColumns($table);
		$shortTag = strtolower(substr($tag, 0, 2));
		$candidates = [
			$baseField . '_' . $tag,
			$baseField . '_' . $shortTag,
		];

		if ($this->bootJoomShopping()) {
			$candidates[] = $this->getJoomShoppingLanguage($tag)->get($baseField);
		}

		$candidates[] = $baseField;

		foreach (array_unique(array_filter($candidates)) as $candidate) {
			if (isset($columns[$candidate])) {
				return $candidate;
			}
		}

		foreach (array_keys($columns) as $column) {
			if (preg_match('/^' . preg_quote($baseField . '_' . $shortTag, '/') . '-[A-Z]{2}$/', $column)) {
				return $column;
			}
		}

		return $baseField . '_' . $tag;
	}

	/**
	 * Switches JoomShopping runtime language for localized helpers.
	 *
	 * @param   string  $tag  Joomla language tag.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function setJoomShoppingLanguage(string $tag): void
	{
		$this->loadJshopConfig();

		if (method_exists($this->jshopConfig, 'setLang')) {
			$this->jshopConfig->setLang($tag);
		}

		if (class_exists('\Joomla\Component\Jshopping\Site\Lib\JSFactory')) {
			\Joomla\Component\Jshopping\Site\Lib\JSFactory::loadLanguageFile($tag, true);
			\Joomla\Component\Jshopping\Site\Lib\JSFactory::loadAdminLanguageFile($tag, true);
		}
	}

	/**
	 * Builds a product route with the best available JoomShopping menu Itemid.
	 *
	 * @param   int     $productId   Product id.
	 * @param   int     $categoryId  Product category id.
	 * @param   string  $language    Joomla language tag.
	 * @param   bool    $hasAlias    Whether the product has a localized alias.
	 * @param   int     $access      Product access level.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function getProductRoute(
		int $productId,
		int $categoryId,
		string $language,
		bool $hasAlias,
		int $access
	): string
	{
		$link = 'index.php?option=com_jshopping&controller=product&task=view'
			. '&category_id=' . $categoryId
			. '&product_id=' . $productId;
		$languageCode = $this->getRouteLanguageCode($language);

		if ($languageCode !== '') {
			$link .= '&lang=' . $languageCode;
		}

		$link = $this->appendItemid(
			$link,
			$this->getProductItemid($productId, $categoryId, $hasAlias, $language, $access)
		);

		return $link;
	}

	/**
	 * Checks whether Joomla language filtering is enabled for frontend routing.
	 *
	 * @return bool
	 *
	 * @since  1.0.0
	 */
	private function isMultilingualEnabled(): bool
	{
		if ($this->multilingualEnabled === null) {
			$this->multilingualEnabled = Multilanguage::isEnabled(null, $this->db);
		}

		return $this->multilingualEnabled;
	}

	/**
	 * Resolves the language value that should be written to an internal Joomla route.
	 *
	 * @param   string  $language  Joomla language tag.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function getRouteLanguageCode(string $language): string
	{
		if ($language === '' || $language === '*' || !$this->isMultilingualEnabled()) {
			return '';
		}

		if ($this->languageSefCodes !== null) {
			return $this->languageSefCodes[$language] ?? $language;
		}

		$this->languageSefCodes = [];

		foreach (LanguageHelper::getLanguages('lang_code') as $languageInfo) {
			$tag = (string) ($languageInfo->lang_code ?? '');

			if ($tag === '') {
				continue;
			}

			$sef = (string) ($languageInfo->sef ?? '');
			$this->languageSefCodes[$tag] = $sef !== '' ? $sef : $tag;
		}

		return $this->languageSefCodes[$language] ?? $language;
	}

	/**
	 * Selects the most specific menu Itemid for a product route.
	 *
	 * @param   int     $productId   Product id.
	 * @param   int     $categoryId  Product category id.
	 * @param   bool    $hasAlias    Whether category fallback is allowed.
	 * @param   string  $language    Joomla language tag.
	 * @param   int     $access      Product access level.
	 *
	 * @return int
	 *
	 * @since  1.0.0
	 */
	private function getProductItemid(int $productId, int $categoryId, bool $hasAlias, string $language, int $access): int
	{
		$map = $this->getItemidMap($language, $access);

		if (!empty($map['product'][$productId])) {
			return (int) $map['product'][$productId];
		}

		if ($categoryId > 0 && $hasAlias && !empty($map['category'][$categoryId])) {
			return (int) $map['category'][$categoryId];
		}

		return $this->getShopItemid($map);
	}

	/**
	 * Returns the cached JoomShopping menu Itemid map for a language and access level.
	 *
	 * @param   string  $language  Joomla language tag.
	 * @param   int     $access    Access level id.
	 *
	 * @return array{product: array<int, int>, category: array<int, int>, controller: array<string, int>, shop: int}
	 *
	 * @since  1.0.0
	 */
	private function getItemidMap(string $language, int $access): array
	{
		$language = $language !== '' ? $language : '*';
		$access   = max(1, $access);
		$key      = $language . ':' . $access;

		if ($this->itemidMaps === null) {
			$this->itemidMaps = [];
		}

		if (!isset($this->itemidMaps[$key])) {
			$this->itemidMaps[$key] = $this->createEmptyItemidMap();
			$this->loadMenuItemidMap($this->itemidMaps[$key], $language, $access);
		}

		return $this->itemidMaps[$key];
	}

	/**
	 * Creates an empty menu Itemid map.
	 *
	 * @return array{product: array<int, int>, category: array<int, int>, controller: array<string, int>, shop: int}
	 *
	 * @since  1.0.0
	 */
	private function createEmptyItemidMap(): array
	{
		return [
			'product'    => [],
			'category'   => [],
			'controller' => [],
			'shop'       => 0,
		];
	}

	/**
	 * Loads published JoomShopping menu rows into an Itemid map.
	 *
	 * @param   array   $map       Menu Itemid map passed by reference.
	 * @param   string  $language  Joomla language tag.
	 * @param   int     $access    Access level id.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function loadMenuItemidMap(array &$map, string $language, int $access): void
	{
		$accessLevels = array_values(array_unique(array_filter([1, $access])));
		$db           = $this->db;
		$query        = $db->getQuery(true)
			->select(
				[
					$db->quoteName('id'),
					$db->quoteName('link'),
				]
			)
			->from($db->quoteName('#__menu'))
			->where($db->quoteName('type') . ' = ' . $db->quote('component'))
			->where($db->quoteName('published') . ' = 1')
			->where($db->quoteName('client_id') . ' = 0')
			->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_jshopping%'))
			->where($db->quoteName('access') . ' IN (' . implode(',', $accessLevels) . ')')
			->where(
				'(' . $db->quoteName('language') . ' = ' . $db->quote('*')
				. ' OR ' . $db->quoteName('language') . ' = ' . $db->quote($language) . ')'
			)
			->order(
				[
					'CASE WHEN ' . $db->quoteName('language') . ' = ' . $db->quote('*') . ' THEN 0 ELSE 1 END ASC',
					$db->quoteName('id') . ' ASC',
				]
			);

		foreach ($db->setQuery($query)->loadObjectList() as $row) {
			$this->addMenuRowToItemidMap($map, (int) $row->id, (string) $row->link);
		}
	}

	/**
	 * Adds one menu row to the product, category, controller, or shop Itemid map.
	 *
	 * @param   array   $map     Menu Itemid map passed by reference.
	 * @param   int     $itemid  Joomla menu item id.
	 * @param   string  $link    Internal menu link.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function addMenuRowToItemidMap(array &$map, int $itemid, string $link): void
	{
		$query = parse_url($link, PHP_URL_QUERY);

		if (!is_string($query) || $query === '') {
			return;
		}

		parse_str($query, $data);

		unset($data['option'], $data['layout']);

		if (!isset($data['controller']) && isset($data['view'])) {
			$data['controller'] = $data['view'];
			unset($data['view']);
		}

		$controller = (string) ($data['controller'] ?? '');
		$task       = (string) ($data['task'] ?? '');

		if ($controller === 'product' && $task === 'view' && !empty($data['product_id'])) {
			$map['product'][(int) $data['product_id']] = $itemid;

			return;
		}

		if ($controller === 'category' && $task === 'view' && !empty($data['category_id'])) {
			$map['category'][(int) $data['category_id']] = $itemid;

			return;
		}

		if ($controller !== '' && count($data) === 1) {
			$map['controller'][$controller] = $itemid;

			if ($controller === 'category') {
				$map['shop'] = $itemid;
			}
		}
	}

	/**
	 * Returns the shop or product controller fallback Itemid.
	 *
	 * @param   array  $map  Menu Itemid map.
	 *
	 * @return int
	 *
	 * @since  1.0.0
	 */
	private function getShopItemid(array $map): int
	{
		return (int) ($map['shop'] ?: ($map['controller']['product'] ?? 0));
	}

	/**
	 * Appends an Itemid parameter to an internal Joomla route.
	 *
	 * @param   string  $url     Internal URL.
	 * @param   int     $itemid  Joomla menu item id.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function appendItemid(string $url, int $itemid): string
	{
		if ($itemid <= 0 || strpos($url, 'Itemid=') !== false) {
			return $url;
		}

		return $url . (str_contains($url, '?') ? '&' : '?') . 'Itemid=' . $itemid;
	}

	/**
	 * Builds searchable characteristic text for one product.
	 *
	 * @param   int     $productId  Product id.
	 * @param   string  $language   Joomla language tag.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function getProductCharacteristicsContent(int $productId, string $language): string
	{
		$this->loadJshopConfig();

		if ((int) $this->params->get('index_characteristics', 1) !== 1) {
			return '';
		}

		if (empty($this->jshopConfig->admin_show_product_extra_field)) {
			return '';
		}

		$this->setJoomShoppingLanguage($language);
		$product = \JSFactory::getTable('product');
		$product->load($productId);

		$rows = $product->getExtraFields(1);

		return $this->formatNameValueRows($rows, (int) $this->params->get('index_characteristic_names', 1) === 1);
	}

	/**
	 * Builds searchable attribute text for one product.
	 *
	 * @param   int     $productId  Product id.
	 * @param   string  $language   Joomla language tag.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function getProductAttributesContent(int $productId, string $language): string
	{
		$this->loadJshopConfig();

		$indexDependent   = (int) $this->params->get('index_dependent_attributes', 1) === 1;
		$indexIndependent = (int) $this->params->get('index_independent_attributes', 1) === 1;
		$indexFree        = (int) $this->params->get('index_free_attributes', 1) === 1;
		$indexDependentNames   = (int) $this->params->get('index_dependent_attribute_names', 1) === 1;
		$indexIndependentNames = (int) $this->params->get('index_independent_attribute_names', 1) === 1;

		if (!$indexDependent && !$indexIndependent && !$indexFree) {
			return '';
		}

		if (empty($this->jshopConfig->admin_show_attributes)) {
			return $indexFree ? $this->getProductFreeAttributesContent($productId, $language) : '';
		}

		$db       = $this->db;
		$name      = $this->resolveLanguageField('#__jshopping_attr', 'name', $language);
		$valueName = $this->resolveLanguageField('#__jshopping_attr_values', 'name', $language);
		$attrRows  = $this->getPublishedAttributes($name);
		$columns  = $db->getTableColumns('#__jshopping_products_attr');
		$content  = [];

		foreach ($attrRows as $attribute) {
			$isIndependent = (int) $attribute->independent === 1;

			if ($isIndependent && !$indexIndependent) {
				continue;
			}

			if (!$isIndependent && !$indexDependent) {
				continue;
			}

			$values = $isIndependent
				? $this->getIndependentAttributeValues($productId, (int) $attribute->attr_id, $valueName)
				: $this->getDependentAttributeValues($productId, (int) $attribute->attr_id, $valueName, $columns);

			if (!$values) {
				continue;
			}

			$indexNames = $isIndependent ? $indexIndependentNames : $indexDependentNames;
			$content[] = $indexNames ? trim($attribute->name . ': ' . implode(', ', $values)) : implode(', ', $values);
		}

		if ($indexFree) {
			$content[] = $this->getProductFreeAttributesContent($productId, $language);
		}

		return implode(' ', array_filter($content));
	}

	/**
	 * Loads published JoomShopping attributes.
	 *
	 * @param   string  $name  Localized attribute name column.
	 *
	 * @return array<int, object>
	 *
	 * @since  1.0.0
	 */
	private function getPublishedAttributes(string $name): array
	{
		$db = $this->db;
		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('attr_id'),
					$db->quoteName($name, 'name'),
					$db->quoteName('independent'),
				]
			)
			->from($db->quoteName('#__jshopping_attr'))
			->where($db->quoteName('publish') . ' = 1')
			->order($db->quoteName('attr_ordering'));

		$db->setQuery($query);

		return $db->loadObjectList();
	}

	/**
	 * Loads selected dependent attribute values for one product.
	 *
	 * @param   int                   $productId    Product id.
	 * @param   int                   $attributeId  Attribute id.
	 * @param   string                $name         Localized value name column.
	 * @param   array<string, mixed>  $columns      Product attribute table columns.
	 *
	 * @return array<int, string>
	 *
	 * @since  1.0.0
	 */
	private function getDependentAttributeValues(int $productId, int $attributeId, string $name, array $columns): array
	{
		$field = 'attr_' . $attributeId;

		if (!isset($columns[$field])) {
			return [];
		}

		$db = $this->db;
		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('value.' . $name, 'value_name'))
			->from($db->quoteName('#__jshopping_products_attr', 'product_attr'))
			->join(
				'INNER',
				$db->quoteName('#__jshopping_attr_values', 'value')
				. ' ON ' . $db->quoteName('value.value_id') . ' = ' . $db->quoteName('product_attr.' . $field)
			)
			->where($db->quoteName('product_attr.product_id') . ' = ' . $productId)
			->where($db->quoteName('product_attr.' . $field) . ' > 0')
			->where($db->quoteName('value.publish') . ' = 1')
			->order($db->quoteName('value.value_ordering'));

		$db->setQuery($query);

		return array_filter(array_map('strval', $db->loadColumn()));
	}

	/**
	 * Loads selected independent attribute values for one product.
	 *
	 * @param   int     $productId    Product id.
	 * @param   int     $attributeId  Attribute id.
	 * @param   string  $name         Localized value name column.
	 *
	 * @return array<int, string>
	 *
	 * @since  1.0.0
	 */
	private function getIndependentAttributeValues(int $productId, int $attributeId, string $name): array
	{
		$db = $this->db;
		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('value.' . $name, 'value_name'))
			->from($db->quoteName('#__jshopping_products_attr2', 'product_attr'))
			->join(
				'INNER',
				$db->quoteName('#__jshopping_attr_values', 'value')
				. ' ON ' . $db->quoteName('value.value_id') . ' = ' . $db->quoteName('product_attr.attr_value_id')
			)
			->where($db->quoteName('product_attr.product_id') . ' = ' . $productId)
			->where($db->quoteName('product_attr.attr_id') . ' = ' . $attributeId)
			->where($db->quoteName('value.publish') . ' = 1')
			->order($db->quoteName('value.value_ordering'));

		$db->setQuery($query);

		return array_filter(array_map('strval', $db->loadColumn()));
	}

	/**
	 * Builds searchable free-attribute text for one product.
	 *
	 * @param   int     $productId  Product id.
	 * @param   string  $language   Joomla language tag.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function getProductFreeAttributesContent(int $productId, string $language): string
	{
		$this->loadJshopConfig();

		if ((int) $this->params->get('index_free_attributes', 1) !== 1) {
			return '';
		}

		if (empty($this->jshopConfig->admin_show_freeattributes)) {
			return '';
		}

		if ((int) $this->params->get('index_free_attribute_names', 1) !== 1) {
			return '';
		}

		$db   = $this->db;
		$name = $this->resolveLanguageField('#__jshopping_free_attr', 'name', $language);

		$query = $db->getQuery(true)
			->select(
				[
					$db->quoteName('free_attr.' . $name, 'name'),
				]
			)
			->from($db->quoteName('#__jshopping_products_free_attr', 'product_free_attr'))
			->join(
				'INNER',
				$db->quoteName('#__jshopping_free_attr', 'free_attr')
				. ' ON ' . $db->quoteName('free_attr.id') . ' = ' . $db->quoteName('product_free_attr.attr_id')
			)
			->where($db->quoteName('product_free_attr.product_id') . ' = ' . $productId)
			->where($db->quoteName('free_attr.publish') . ' = 1')
			->order($db->quoteName('free_attr.ordering'));

		$db->setQuery($query);

		return $this->formatNameValueRows($db->loadAssocList());
	}

	/**
	 * Builds searchable manufacturer, code, EAN, and price text.
	 *
	 * @param   Result  $item  Finder product item.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function getProductPriceContent(Result $item): string
	{
		$this->bootJoomShopping();
		$parts = [];
		$indexManufacturer      = (int) $this->params->get('index_manufacturer', 1) === 1;
		$indexManufacturerLabel = (int) $this->params->get('index_manufacturer_label', 1) === 1;
		$map = [
			'manufacturer'      => 'JSHOP_MANUFACTURER',
			'manufacturer_code' => 'JSHOP_MANUFACTURER_CODE',
			'product_ean'       => 'JSHOP_EAN',
		];

		foreach ($map as $field => $label) {
			if ($field === 'manufacturer' && !$indexManufacturer) {
				continue;
			}

			$value = trim((string) $item->getElement($field));

			if ($value !== '') {
				$parts[] = $field === 'manufacturer' && !$indexManufacturerLabel ? $value : Text::_($label) . ': ' . $value;
			}
		}

		foreach (['product_old_price', 'product_buy_price', 'product_price'] as $field) {
			$value = (float) $item->getElement($field);

			if ($value > 0) {
				$parts[] = \Joomla\Component\Jshopping\Site\Helper\Helper::formatPrice($value);
			}
		}

		return implode(' ', $parts);
	}

	/**
	 * Formats rows with name and value fields into a searchable text fragment.
	 *
	 * @param   array<int, array|object>  $rows          Rows with name and value fields.
	 * @param   bool                      $includeNames  Whether to include row names.
	 *
	 * @return string
	 *
	 * @since  1.0.0
	 */
	private function formatNameValueRows(array $rows, bool $includeNames = true): string
	{
		$content = [];

		foreach ($rows as $row) {
			$row = (array) $row;
			$name = trim(strip_tags((string) ($row['name'] ?? '')));
			$value = trim(strip_tags((string) ($row['value'] ?? '')));
			$text = $includeNames ? trim($name . ($value !== '' ? ': ' . $value : '')) : $value;

			if ($text === '') {
				continue;
			}

			$content[] = $text;
		}

		return implode(' ', $content);
	}

	/**
	 * Builds all Finder URLs used to identify one product across languages.
	 *
	 * @param   int   $productId         Product id.
	 * @param   bool  $includeLegacyUrl  Whether to include the language-neutral legacy URL.
	 *
	 * @return array<int, string>
	 *
	 * @since  1.0.0
	 */
	private function getProductUrls(int $productId, bool $includeLegacyUrl = true): array
	{
		$urls = [];

		if ($includeLegacyUrl) {
			$urls[] = $this->getUrl($productId, $this->extension, $this->layout);
		}

		foreach ($this->getLanguageTags() as $language) {
			$urls[] = $this->getUrl($productId, $this->extension, $this->layout, $language);
		}

		return array_values(array_unique($urls));
	}

	/**
	 * Removes Finder links for all URLs of one product.
	 *
	 * @param   int   $productId         Product id.
	 * @param   bool  $removeTaxonomies  Whether Finder taxonomies should be removed.
	 *
	 * @return void
	 * @throws \Exception
	 *
	 * @since  1.0.0
	 */
	private function removeProductLinks(int $productId, bool $removeTaxonomies = true): void
	{
		$urls = $this->getProductUrls($productId);

		if (!$urls) {
			return;
		}

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName('link_id'))
			->from($this->db->quoteName('#__finder_links'))
			->whereIn($this->db->quoteName('url'), $urls, ParameterType::STRING);
		$this->db->setQuery($query);

		foreach (array_map('intval', $this->db->loadColumn()) as $linkId) {
			$this->indexer->remove($linkId, $removeTaxonomies);
		}
	}

	/**
	 * Updates a supported property on all Finder links for one product.
	 *
	 * @param   int     $productId  Product id.
	 * @param   string  $property   Finder link property.
	 * @param   int     $value      New property value.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function changeProductLinks(int $productId, string $property, int $value): void
	{
		if ($property !== 'state' && $property !== 'access') {
			return;
		}

		$urls = $this->getProductUrls($productId);

		if (!$urls) {
			return;
		}

		$query = $this->db->getQuery(true)
			->update($this->db->quoteName('#__finder_links'))
			->set($this->db->quoteName($property) . ' = ' . $value)
			->whereIn($this->db->quoteName('url'), $urls, ParameterType::STRING);
		$this->db->setQuery($query)->execute();
	}

	/**
	 * Reindexes a published product or disables its existing Finder links.
	 *
	 * @param   int  $productId  Product id.
	 *
	 * @return void
	 * @throws \Exception
	 *
	 * @since  1.0.0
	 */
	private function reindexOrDisableProduct(int $productId): void
	{
		$query = $this->getListQuery();
		$query->where($this->db->quoteName('prod.product_id') . ' = ' . $productId);

		$this->db->setQuery($query);

		if ($this->db->loadResult()) {
			$this->reindex($productId);

			return;
		}

		$this->changeProductLinks($productId, 'state', 0);
	}

	/**
	 * Reindexes a list of product ids.
	 *
	 * @param   array<int, int|string>  $productIds  Product ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function reindexProducts(array $productIds): void
	{
		foreach (array_unique(array_map('intval', $productIds)) as $productId) {
			if ($productId > 0) {
				$this->reindexOrDisableProduct($productId);
			}
		}
	}

	/**
	 * Loads product ids assigned to the supplied category ids.
	 *
	 * @param   array<int, int|string>  $categoryIds  Category ids.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductIdsByCategories(array $categoryIds): array
	{
		$categoryIds = array_values(array_filter(array_map('intval', $categoryIds)));

		if (!$categoryIds) {
			return [];
		}

		$db = $this->db;
		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('product_id'))
			->from($db->quoteName('#__jshopping_products_to_categories'))
			->whereIn($db->quoteName('category_id'), $categoryIds);

		$db->setQuery($query);

		return array_map('intval', $db->loadColumn());
	}

	/**
	 * Extracts a product id from a bridged JoomShopping object.
	 *
	 * @param   object  $item  Source item.
	 *
	 * @return int
	 *
	 * @since  1.0.0
	 */
	private function getProductIdFromObject(object $item): int
	{
		return (int) ($item->product_id ?? $item->id ?? 0);
	}

	/**
	 * Extracts a category id from a bridged JoomShopping object.
	 *
	 * @param   object  $item  Source item.
	 *
	 * @return int
	 *
	 * @since  1.0.0
	 */
	private function getCategoryIdFromObject(object $item): int
	{
		return (int) ($item->category_id ?? $item->id ?? 0);
	}
}
