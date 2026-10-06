# Supertext Translation for TYPO3

Translates pages and content elements with **Supertext AI** the moment an editor localizes them in TYPO3 — via the Page module's *Translate* wizard, *Create new translation*, the List module, or the CLI. No new workflow to learn: editors keep using TYPO3's native localization.

Works with TYPO3 13.4 LTS and 14.3 LTS, PHP 8.2+.

## How it works

1. TYPO3 localizes the records as usual (connected *translate* mode or free *copy* mode).
2. A DataHandler hook collects every record localized in that run — pages, content elements and inline children such as image captions.
3. All their text fields go to Supertext as **one HTML document per target language** (AI file translation, up to ~1M characters), so a page with twenty content elements is a single API round trip.
4. The translations are written back through the DataHandler (history, reference index and RTE processing included), page slugs are rebuilt from the translated title, and the editor sees a confirmation message.

Translated records stay **hidden** (TYPO3's default for new translations) so editors can review before publishing. If Supertext fails, the records are still localized as plain TYPO3 copies and a warning explains why.

Which fields are translated is derived from TCA: `input` and `text` fields that aren't excluded from localization, read-only, numeric, code editors or similar. Rich-text fields keep their markup. `bodytext` of HTML content elements is never translated (configurable).

## Documentation

| Guide | For |
| --- | --- |
| [Installation guide](docs/INSTALLATION.md) | Administrators: requirements, install, API key, languages, settings, troubleshooting |
| [User guide](docs/USER_GUIDE.md) | Editors: translating, reviewing, what gets translated |
| [Developer guide](docs/DEVELOPER.md) | Architecture, API protocol, local setup, tests, deployment, releases |

Quick start (not on Packagist yet — add the GitHub repository first, see the installation guide):

```bash
composer require supertext/typo3-translation
vendor/bin/typo3 extension:setup -e supertext_translation
# then set SUPERTEXT_API_KEY or the key in Extension Configuration
```

You need a Supertext account ([log in or create one](https://www.supertext.com/person/en/account/signin)) and an API key from [supertext.com → Integrations → API](https://www.supertext.com/en/integrations/api) (requires the Admin role in your Supertext account).

## Demo

`demo/` builds a container with TYPO3 14.3, the Camino demo site (EN, DE-CH, FR-CH) and this extension. It's deployed to Railway on every push to `main` — details in the [developer guide](docs/DEVELOPER.md#demo-railway).

## Roadmap

See the [developer guide](docs/DEVELOPER.md#known-limitations--roadmap) and [CHANGELOG](CHANGELOG.md).

<!-- supertext-plugins:start (shared list, keep identical in every Supertext plugin repo) -->
## Supertext plugins for other systems

Supertext offers AI and professional translation plugins for these systems:

| System | Plugin | What it does |
| --- | --- | --- |
| Adobe Experience Manager | [supertext-aem-connector](https://github.com/Supertext/supertext-aem-connector) | Translation connector for AEM 6.5's Translation Integration Framework |
| Contao | [Contao-Supertext-Translation](https://github.com/Supertext/Contao-Supertext-Translation) | *Translate with Supertext* in the site structure: pages or whole websites into other languages |
| Craft CMS | [CraftCms-Supertext-Translation](https://github.com/Supertext/CraftCms-Supertext-Translation) | Translates entries into your other sites, Matrix and rich text included |
| Directus | [Directus-Supertext-Translation](https://github.com/Supertext/Directus-Supertext-Translation) | *Translate with Supertext* box on the item form, fills the Translations field |
| django CMS | [djangoCMS-Supertext-Translation](https://github.com/Supertext/djangoCMS-Supertext-Translation) | Translates pages and their plugins from the toolbar |
| Drupal | [tmgmt_supertext_ai](https://www.drupal.org/project/tmgmt_supertext_ai) | Supertext AI provider for Drupal's Translation Management Tool (TMGMT), by MD Systems |
| Ghost | [Ghost-Supertext-Translation](https://github.com/Supertext/Ghost-Supertext-Translation) | Tag a post `#translate-…` and a translated draft appears |
| Grav | [Grav-Supertext-Translation](https://github.com/Supertext/Grav-Supertext-Translation) | Supertext panel in Grav 2's page editor, Markdown kept intact |
| Joomla | [Joomla-Supertext-Translation](https://github.com/Supertext/Joomla-Supertext-Translation) | Translates articles into linked, unpublished language versions |
| Neos | [Neos-Supertext-Translation](https://github.com/Supertext/Neos-Supertext-Translation) | Translates automatically when an editor creates a page in another language |
| Orchard Core | [OrchardCore-Supertext-Translation](https://github.com/Supertext/OrchardCore-Supertext-Translation) | Translates content items into other cultures, on demand or on localization |
| Payload CMS | [Payload-Supertext-Translation](https://github.com/Supertext/Payload-Supertext-Translation) | *Translate* button for localized collections and globals |
| Silverstripe | [Silverstripe-Supertext-Translation](https://github.com/Supertext/Silverstripe-Supertext-Translation) | Supertext tab translates pages and Elemental blocks into Fluent locales |
| Strapi | [Strapi-Supertext-Translation](https://github.com/Supertext/Strapi-Supertext-Translation) | Translates entries into other locales from the Content Manager |
| TYPO3 | [Typo3-Supertext-Translation](https://github.com/Supertext/Typo3-Supertext-Translation) | Translates pages and content elements as editors localize them |
| Umbraco | [Umbraco-Supertext-Translation](https://github.com/Supertext/Umbraco-Supertext-Translation) | *Translate with Supertext* for pages, block lists and grids included |
| Wagtail | [Wagtail-Supertext-Translation](https://github.com/Supertext/Wagtail-Supertext-Translation) | Machine translator for wagtail-localize |
| WordPress (Polylang) | [supertext-wordpress-polylang](https://github.com/Supertext/supertext-wordpress-polylang) | Supertext as Polylang Pro's machine-translation service, plus professional translation orders |
<!-- supertext-plugins:end -->

## License

GPL-2.0-or-later
