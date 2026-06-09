# WT JoomShopping Finder

WT JoomShopping Finder is a Joomla Smart Search package that adds published JoomShopping products to the Joomla search index.

The package installs two plugins:

- `finder` plugin: Smart Search - JoomShopping products
- `jshopping` plugin: JoomShopping - Smart Search reindex bridge

The finder plugin builds searchable product documents. The JoomShopping bridge plugin helps keep those documents up to date after product and manufacturer changes.

## Installation

Install the extension with one of the standard Joomla installer methods.

### Upload ZIP Package

1. In Joomla administrator, open System -> Install -> Extensions.
2. Open the Upload Package File tab.
3. Upload the extension ZIP package.
4. After installation, check that both package plugins are enabled.

### Install from URL

1. In Joomla administrator, open System -> Install -> Extensions.
2. Open the Install from URL tab.
3. Paste the package URL:

```text
https://web-tolk.ru/get?element=wtjoomshoppingfinder
```

4. Click Check and Install, then check that both package plugins are enabled.

## Finding the Plugins

Open System -> Manage -> Plugins and search for `JoomShopping`.

To configure indexing, open the finder plugin:

```text
Smart Search - JoomShopping products
```

## Indexing Settings

The settings are located on the Smart Search - JoomShopping products plugin page. Additional text sources are enabled by default. Dependent switches are shown only when their parent data source is enabled.

| Setting | What is added to the index |
| --- | --- |
| Index manufacturer | Published manufacturer name assigned to the product. Manufacturer pages are not created as separate Smart Search results. |
| Index manufacturer label | Controls only the label next to the value. Enabled: `Manufacturer: Xiaomi`. Disabled: only `Xiaomi`. |
| Index characteristics | Characteristic name and selected or displayed value for the product and current language. Characteristic descriptions are not indexed. |
| Index characteristic names | Controls whether characteristic names are added next to selected values. |
| Index dependent attributes | Dependent attribute names and selected published value names from product variation rows. Attribute descriptions are not indexed. |
| Index dependent attribute names | Controls whether dependent attribute names are added next to selected values. |
| Index independent attributes | Independent attribute names and selected published value names from independent attribute assignments. Attribute descriptions are not indexed. |
| Index independent attribute names | Controls whether independent attribute names are added next to selected values. |
| Index free attributes | Only published free attribute names enabled for the product and current indexed language. Descriptions and customer-entered values are not indexed. |
| Index free attribute names | Free attributes currently contribute only their names. If disabled, free attributes add no searchable text. |
| Taxonomies to index | Smart Search content maps: category, language and type. This affects Finder filtering and content maps; it does not create separate category pages. |

## How It Works

The plugin works as a Joomla Smart Search adapter. During indexing it reads published JoomShopping products, respects access, publication state and Joomla languages, and creates searchable documents of type `Product`.

On multilingual sites, one product can produce separate Finder records for different languages.

The main product search text can be extended with manufacturer, characteristics, dependent attributes, independent attributes and free attributes. Attribute and characteristic descriptions are intentionally excluded because these fields are often used for technical notes.

The bridge plugin reacts to product and manufacturer changes. When a product is saved, published, unpublished or deleted, or when a manufacturer changes, affected product Finder links are updated or removed.

## Changing Indexing Parameters

Changing plugin parameters changes the text composition used by the next indexing run. Existing Smart Search records are not rebuilt automatically only because the settings were saved.

After changing any indexing parameter, save the plugin and run a full Smart Search reindex. Without reindexing, existing records may still contain text built with previous settings.

New or changed products will use current settings on the next normal index update, but a full rebuild is required for consistent results across the whole catalog.

## Reindexing from Administrator

1. Open Components -> Smart Search -> Index.
2. Click Index or use the indexing action in the toolbar.
3. Wait until the process finishes. JoomShopping products will then be rebuilt with current plugin settings.

## Reindexing from CLI

On the server, switch to the Joomla site root and run the standard Smart Search command:

```bash
php cli/joomla.php finder:index
```

CLI indexing is useful for large catalogs, scheduled jobs and planned rebuilds after changing settings. The command runs the global Smart Search indexer, so enabled finder plugins other than JoomShopping products may also be processed.
