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

## Installation

```bash
composer require supertext/typo3-translation
vendor/bin/typo3 extension:setup -e supertext_translation
```

Then set the API key under *Admin Tools → Settings → Extension Configuration → supertext_translation* (from supertext.com → Integrations → API), or provide it as the environment variable `SUPERTEXT_API_KEY`.

## Languages

Each site language is sent as its locale in BCP-47 form (`de_CH.UTF-8` → `de-CH`); the source language is the default language's primary subtag (`en`). Override per language in `config/sites/<site>/config.yaml`:

```yaml
languages:
  -
    languageId: 1
    locale: de_CH.UTF-8
    supertext_code: de-CH          # optional: Supertext target code
    supertext_politeness: more     # optional: more = formal (Sie), less = informal (du)
```

## Extension configuration

| Setting | Default | Purpose |
| --- | --- | --- |
| `enabled` | on | Translate automatically on localization |
| `apiKey` | – | Supertext API key (`SUPERTEXT_API_KEY` env var wins) |
| `environment` | live | `live`, `staging` or `testing` API |
| `endpoint` | – | Custom base URL (`SUPERTEXT_API_ENDPOINT` env var wins) |
| `pollTimeout` / `pollInterval` | 180 / 2 s | Waiting for the asynchronous translation |
| `skipBodytextCTypes` | `html` | Content types whose bodytext is code |
| `regenerateSlugs` | on | Build translated page URLs from translated titles |

## CLI

```bash
# Translate page 1 and its content into site language 1
vendor/bin/typo3 supertext:localize 1 1

# ...including all subpages
vendor/bin/typo3 supertext:localize 1 1 --recursive
```

## Development

`Tests/HtmlDocumentTest.php` checks that text survives the HTML round trip (`php Tests/HtmlDocumentTest.php`). The GitHub workflow lints on PHP 8.2–8.4, runs the test, and deploys `main` to the demo server once the `DEPLOY_*` secrets are set (see `.github/workflows/deploy.yml`).

The protocol matches the [Supertext WordPress plugin](https://github.com/Supertext/supertext-wordpress-polylang): `POST translate/ai/file` → poll `…/status` → `GET …/translation` → `DELETE`.

## Roadmap

- Asynchronous mode (queue + scheduler) for very large pages
- FlexForm fields and container/grid extensions
- "Retranslate" action for already localized records
- Human (professional) translation orders, as in the WordPress plugin

## License

GPL-2.0-or-later
