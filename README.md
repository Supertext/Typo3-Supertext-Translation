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

## Demo

`demo/` builds a container with TYPO3 14.3, the Camino demo site (EN, DE-CH, FR-CH) and this extension. It's deployed to Railway on every push to `main` — details in the [developer guide](docs/DEVELOPER.md#demo-railway).

## Roadmap

See the [developer guide](docs/DEVELOPER.md#known-limitations--roadmap) and [CHANGELOG](CHANGELOG.md).

## License

GPL-2.0-or-later
