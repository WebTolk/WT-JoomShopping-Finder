<?php

/**
 * JoomShopping event bridge for WT JoomShopping Finder.
 *
 * @package       WT JoomShopping Finder
 * @subpackage    plg_jshopping_wtjoomshoppingfinderbridge
 * @author     Sergey Tolkachyov
 * @copyright  Copyright (c) 2024 - 2026 Sergey Tolkachyov. All rights reserved.
 * @version       __DEPLOY_VERSION__
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

namespace Joomla\Plugin\Jshopping\Wtjoomshoppingfinderbridge\Extension;

use Joomla\CMS\Event\Finder as FinderEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\QueryInterface;

defined('_JEXEC') or die;

/**
 * Bridges JoomShopping legacy CRUD events to Joomla Finder adapter events.
 *
 * @since  1.0.0
 */
final class Wtjoomshoppingfinderbridge extends CMSPlugin
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
	 * Dispatches a Finder save event after one product is saved.
	 *
	 * @param   object  $product  Saved JoomShopping product.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveProduct(object $product): void
	{
		$this->dispatchAfterSave('com_jshopping.product', $this->normaliseProduct($product), false);
	}

	/**
	 * Reindexes products after JoomShopping list save operations.
	 *
	 * @param   array<int, int|string>  $cid   Product ids.
	 * @param   array                   $post  Submitted request data.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveListProductEnd(array $cid, array $post): void
	{
		$this->dispatchProductReindex($cid);
	}

	/**
	 * Dispatches Finder delete events after products are removed.
	 *
	 * @param   array<int, int|string>  $cid  Product ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveProduct(array $cid): void
	{
		foreach ($this->normaliseIds($cid) as $id) {
			$this->dispatchAfterDelete('com_jshopping.product', (object) ['id' => $id, 'product_id' => $id]);
		}
	}

	/**
	 * Applies product publish state changes to Finder links.
	 *
	 * @param   array<int, int|string>  $cid   Product ids.
	 * @param   int                     $flag  Publish state.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterPublishProduct(array $cid, int $flag): void
	{
		$this->dispatchChangeState('com_jshopping.product', $cid, $flag);
	}

	/**
	 * Reindexes products after a category is saved.
	 *
	 * @param   object  $category  Saved JoomShopping category.
	 * @param   array   $post      Submitted request data.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveCategory(object $category, array $post): void
	{
		$this->dispatchAfterSave('com_jshopping.category', $this->normaliseCategory($category), false);
	}

	/**
	 * Disables Finder links after categories are removed.
	 *
	 * @param   array<int, int|string>  $cid  Category ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveCategory(array $cid): void
	{
		$this->dispatchCategoryChangeState($cid, 0);
	}

	/**
	 * Applies category publish state changes to related Finder product links.
	 *
	 * @param   array<int, int|string>  $cid   Category ids.
	 * @param   int                     $flag  Publish state.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterPublishCategory(array $cid, int $flag): void
	{
		$this->dispatchCategoryChangeState($cid, $flag);
	}

	/**
	 * Reindexes products after a characteristic field is saved.
	 *
	 * @param   object  $productField  JoomShopping product field.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveProductField(object $productField): void
	{
		$this->dispatchProductReindex($this->getProductsByExtraField((int) ($productField->id ?? 0)));
	}

	/**
	 * Reindexes products after characteristic fields are removed.
	 *
	 * @param   array<int, int|string>  $cid     Field ids.
	 * @param   bool                    $result  Removal result.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveProductField(array $cid, bool $result = true): void
	{
		$this->dispatchProductReindex($this->getAllProductIds());
	}

	/**
	 * Reindexes products after a characteristic value is saved.
	 *
	 * @param   object  $productFieldValue  JoomShopping product field value.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveProductFieldValue(object $productFieldValue): void
	{
		$this->dispatchProductReindex(
			$this->getProductsByExtraFieldValue((int) ($productFieldValue->field_id ?? 0), (int) ($productFieldValue->id ?? 0))
		);
	}

	/**
	 * Reindexes products after characteristic values are removed.
	 *
	 * @param   array<int, int|string>  $cid  Value ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveProductFieldValue(array $cid): void
	{
		$this->dispatchProductReindex($this->getAllProductIds());
	}

	/**
	 * Reindexes products after a characteristic group is saved.
	 *
	 * @param   object  $productFieldGroup  JoomShopping product field group.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveProductFieldGroup(object $productFieldGroup): void
	{
		$this->dispatchProductReindex($this->getAllProductIds());
	}

	/**
	 * Reindexes products after characteristic groups are removed.
	 *
	 * @param   array<int, int|string>  $cid  Group ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveProductFieldGroup(array $cid): void
	{
		$this->dispatchProductReindex($this->getAllProductIds());
	}

	/**
	 * Reindexes products after a dependent or independent attribute is saved.
	 *
	 * @param   object  $attribute  JoomShopping attribute.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveAttribut(object $attribute): void
	{
		$this->dispatchProductReindex($this->getProductsByAttribute((int) ($attribute->attr_id ?? 0)));
	}

	/**
	 * Reindexes products after attributes are removed.
	 *
	 * @param   array<int, int|string>  $cid  Attribute ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveAttribut(array $cid): void
	{
		$this->dispatchProductReindex($this->getAllProductIds());
	}

	/**
	 * Reindexes products after an attribute value is saved.
	 *
	 * @param   object  $attributeValue  JoomShopping attribute value.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveAttributValue(object $attributeValue): void
	{
		$this->dispatchProductReindex(
			$this->getProductsByAttributeValue((int) ($attributeValue->attr_id ?? 0), (int) ($attributeValue->value_id ?? 0))
		);
	}

	/**
	 * Reindexes products after attribute values are removed.
	 *
	 * @param   array<int, int|string>  $cid  Attribute value ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveAttributValue(array $cid): void
	{
		$this->dispatchProductReindex($this->getAllProductIds());
	}

	/**
	 * Reindexes products after a free attribute is saved.
	 *
	 * @param   object  $attribute  JoomShopping free attribute.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveFreeAtribut(object $attribute): void
	{
		$this->dispatchProductReindex($this->getProductsByFreeAttribute((int) ($attribute->id ?? 0)));
	}

	/**
	 * Reindexes products after free attributes are removed.
	 *
	 * @param   array<int, int|string>  $cid  Free attribute ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveFreeAtribut(array $cid): void
	{
		$this->dispatchProductReindex($this->getAllProductIds());
	}

	/**
	 * Reindexes products after a manufacturer is saved.
	 *
	 * @param   object  $manufacturer  JoomShopping manufacturer.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterSaveManufacturer(object $manufacturer): void
	{
		$this->dispatchProductReindex($this->getProductsByManufacturer((int) ($manufacturer->manufacturer_id ?? $manufacturer->id ?? 0)));
	}

	/**
	 * Reindexes products after manufacturers are removed.
	 *
	 * @param   array<int, int|string>  $cid  Manufacturer ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterRemoveManufacturer(array $cid): void
	{
		$this->dispatchProductReindex($this->getProductsByManufacturers($cid));
	}

	/**
	 * Reindexes products after manufacturer publish state changes.
	 *
	 * @param   array<int, int|string>  $cid   Manufacturer ids.
	 * @param   int                     $flag  Publish state.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	public function onAfterPublishManufacturer(array $cid, int $flag): void
	{
		$this->dispatchProductReindex($this->getProductsByManufacturers($cid));
	}

	/**
	 * Dispatches Finder save events for a list of product ids.
	 *
	 * @param   array<int, int|string>  $productIds  Product ids.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function dispatchProductReindex(array $productIds): void
	{
		foreach ($this->normaliseIds($productIds) as $id) {
			$this->dispatchAfterSave('com_jshopping.product', (object) ['id' => $id, 'product_id' => $id], false);
		}
	}

	/**
	 * Dispatches a typed Finder after-save event.
	 *
	 * @param   string  $context  Finder content context.
	 * @param   object  $item     Event item.
	 * @param   bool    $isNew    Whether the item is new.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function dispatchAfterSave(string $context, object $item, bool $isNew): void
	{
		$this->importFinderPlugin();
		$this->getDispatcher()->dispatch(
			'onFinderAfterSave',
			new FinderEvent\AfterSaveEvent(
				'onFinderAfterSave',
				[
					'context' => $context,
					'subject' => $item,
					'isNew'   => $isNew,
				]
			)
		);
	}

	/**
	 * Dispatches a typed Finder after-delete event.
	 *
	 * @param   string  $context  Finder content context.
	 * @param   object  $item     Event item.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function dispatchAfterDelete(string $context, object $item): void
	{
		$this->importFinderPlugin();
		$this->getDispatcher()->dispatch(
			'onFinderAfterDelete',
			new FinderEvent\AfterDeleteEvent(
				'onFinderAfterDelete',
				[
					'context' => $context,
					'subject' => $item,
				]
			)
		);
	}

	/**
	 * Dispatches a typed Finder state-change event.
	 *
	 * @param   string                  $context  Finder content context.
	 * @param   array<int, int|string>  $ids      Item ids.
	 * @param   int                     $value    New state value.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function dispatchChangeState(string $context, array $ids, int $value): void
	{
		$this->importFinderPlugin();
		$this->getDispatcher()->dispatch(
			'onFinderChangeState',
			new FinderEvent\AfterChangeStateEvent(
				'onFinderChangeState',
				[
					'context' => $context,
					'subject' => $this->normaliseIds($ids),
					'value'   => $value,
				]
			)
		);
	}

	/**
	 * Dispatches a typed Finder category state-change event.
	 *
	 * @param   array<int, int|string>  $ids    Category ids.
	 * @param   int                     $value  New state value.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function dispatchCategoryChangeState(array $ids, int $value): void
	{
		$this->importFinderPlugin();
		$this->getDispatcher()->dispatch(
			'onFinderCategoryChangeState',
			new FinderEvent\AfterCategoryChangeStateEvent(
				'onFinderCategoryChangeState',
				[
					'context' => 'com_jshopping',
					'subject' => $this->normaliseIds($ids),
					'value'   => $value,
				]
			)
		);
	}

	/**
	 * Imports the Finder adapter plugin into the shared event dispatcher.
	 *
	 * @return void
	 *
	 * @since  1.0.0
	 */
	private function importFinderPlugin(): void
	{
		PluginHelper::importPlugin('finder', 'wtjoomshoppingfinder', true, $this->getDispatcher());
	}

	/**
	 * Normalises a JoomShopping product object to expose id and product_id.
	 *
	 * @param   object  $product  Source product.
	 *
	 * @return object
	 *
	 * @since  1.0.0
	 */
	private function normaliseProduct(object $product): object
	{
		$id = (int) ($product->product_id ?? $product->id ?? 0);
		$product->id = $id;
		$product->product_id = $id;

		return $product;
	}

	/**
	 * Normalises a JoomShopping category object to expose id and category_id.
	 *
	 * @param   object  $category  Source category.
	 *
	 * @return object
	 *
	 * @since  1.0.0
	 */
	private function normaliseCategory(object $category): object
	{
		$id = (int) ($category->category_id ?? $category->id ?? 0);
		$category->id = $id;
		$category->category_id = $id;

		return $category;
	}

	/**
	 * Normalises ids to unique positive integers.
	 *
	 * @param   array<int, int|string>  $ids  Source ids.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function normaliseIds(array $ids): array
	{
		return array_values(array_unique(array_filter(array_map('intval', $ids))));
	}

	/**
	 * Loads products that use a characteristic field.
	 *
	 * @param   int  $fieldId  Characteristic field id.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByExtraField(int $fieldId): array
	{
		if ($fieldId <= 0) {
			return [];
		}

		$field = 'extra_field_' . $fieldId;
		$model = \JSFactory::getModel('productfields');
		$ids   = [];

		foreach ($model->getListProducsValueByExtraFieldId($fieldId) as $row) {
			$value = trim((string) ($row->$field ?? ''));

			if ($value === '' || $value === '0') {
				continue;
			}

			$ids[] = (int) $row->product_id;
		}

		return array_values(array_unique(array_filter($ids)));
	}

	/**
	 * Loads products that use a characteristic value.
	 *
	 * @param   int  $fieldId  Characteristic field id.
	 * @param   int  $valueId  Characteristic value id.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByExtraFieldValue(int $fieldId, int $valueId): array
	{
		if ($fieldId <= 0 || $valueId <= 0) {
			return [];
		}

		$field = 'extra_field_' . $fieldId;
		$model = \JSFactory::getModel('productfields');
		$ids   = [];

		foreach ($model->getListProducsValueByExtraFieldId($fieldId) as $row) {
			$values = array_map('intval', array_filter(explode(',', (string) ($row->$field ?? ''))));

			if (!in_array($valueId, $values, true)) {
				continue;
			}

			$ids[] = (int) $row->product_id;
		}

		return array_values(array_unique(array_filter($ids)));
	}

	/**
	 * Loads products that use a dependent or independent attribute.
	 *
	 * @param   int  $attributeId  Attribute id.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByAttribute(int $attributeId): array
	{
		if ($attributeId <= 0) {
			return [];
		}

		$ids = $this->getProductsByDependentAttribute($attributeId);

		return array_merge($ids, $this->getProductsByIndependentAttribute($attributeId));
	}

	/**
	 * Loads products that use a dependent or independent attribute value.
	 *
	 * @param   int  $attributeId  Attribute id.
	 * @param   int  $valueId      Attribute value id.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByAttributeValue(int $attributeId, int $valueId): array
	{
		if ($attributeId <= 0 || $valueId <= 0) {
			return [];
		}

		$ids = $this->getProductsByDependentAttribute($attributeId, $valueId);

		return array_merge($ids, $this->getProductsByIndependentAttribute($attributeId, $valueId));
	}

	/**
	 * Loads products that use a dependent attribute or value.
	 *
	 * @param   int  $attributeId  Attribute id.
	 * @param   int  $valueId      Optional attribute value id.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByDependentAttribute(int $attributeId, int $valueId = 0): array
	{
		$field = 'attr_' . $attributeId;
		$db = $this->getDatabase();
		$columns = $db->getTableColumns('#__jshopping_products_attr');

		if (!isset($columns[$field])) {
			return [];
		}

		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('product_id'))
			->from($db->quoteName('#__jshopping_products_attr'))
			->where($db->quoteName($field) . ' > 0');

		if ($valueId > 0) {
			$query->where($db->quoteName($field) . ' = ' . $valueId);
		}

		return $this->loadIds($query);
	}

	/**
	 * Loads products that use an independent attribute or value.
	 *
	 * @param   int  $attributeId  Attribute id.
	 * @param   int  $valueId      Optional attribute value id.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByIndependentAttribute(int $attributeId, int $valueId = 0): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('product_id'))
			->from($db->quoteName('#__jshopping_products_attr2'))
			->where($db->quoteName('attr_id') . ' = ' . $attributeId);

		if ($valueId > 0) {
			$query->where($db->quoteName('attr_value_id') . ' = ' . $valueId);
		}

		return $this->loadIds($query);
	}

	/**
	 * Loads products that use a free attribute.
	 *
	 * @param   int  $attributeId  Free attribute id.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByFreeAttribute(int $attributeId): array
	{
		if ($attributeId <= 0) {
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('DISTINCT ' . $db->quoteName('product_id'))
			->from($db->quoteName('#__jshopping_products_free_attr'))
			->where($db->quoteName('attr_id') . ' = ' . $attributeId);

		return $this->loadIds($query);
	}

	/**
	 * Loads products assigned to one manufacturer.
	 *
	 * @param   int  $manufacturerId  Manufacturer id.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByManufacturer(int $manufacturerId): array
	{
		if ($manufacturerId <= 0) {
			return [];
		}

		return $this->getProductsByManufacturers([$manufacturerId]);
	}

	/**
	 * Loads products assigned to any of the supplied manufacturers.
	 *
	 * @param   array<int, int|string>  $manufacturerIds  Manufacturer ids.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getProductsByManufacturers(array $manufacturerIds): array
	{
		$manufacturerIds = $this->normaliseIds($manufacturerIds);

		if ($manufacturerIds === []) {
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('product_id'))
			->from($db->quoteName('#__jshopping_products'))
			->whereIn($db->quoteName('product_manufacturer_id'), $manufacturerIds);

		return $this->loadIds($query);
	}

	/**
	 * Loads all JoomShopping product ids.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function getAllProductIds(): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select($db->quoteName('product_id'))
			->from($db->quoteName('#__jshopping_products'));

		return $this->loadIds($query);
	}

	/**
	 * Executes an id query and normalises the loaded ids.
	 *
	 * @param   QueryInterface  $query  Query that returns ids in the first column.
	 *
	 * @return array<int, int>
	 *
	 * @since  1.0.0
	 */
	private function loadIds(QueryInterface $query): array
	{
		$db = $this->getDatabase();
		$db->setQuery($query);

		return $this->normaliseIds($db->loadColumn());
	}
}
