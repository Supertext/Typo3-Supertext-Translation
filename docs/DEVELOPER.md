# Developer guide — Supertext Translation for TYPO3

How the extension is built, how to work on it, and how it is released and deployed.

## Architecture

```
Editor localizes (Page module / List module / CLI)
        │
        ▼
TYPO3 DataHandler  ── localize | copyToLanguage ──►  new records (copies)
        │
        │ processCmdmap_postProcess   collect source→target uids per command
        │ processCmdmap_afterFinish   once per DataHandler run
        ▼
Hooks\DataHandlerHook ──► Service\TranslationService
                              │  FieldCollector    which fields to translate (TCA)
                              │  LanguageResolver  site language → Supertext code/politeness
                              │  HtmlDocument      pack all fields into ONE HTML document
                              ▼
                          Api\SupertextClient  POST file → poll status → GET translation → DELETE
                              │
                              ▼
                          DataHandler datamap  write translations back (+ slugs)
```

| Class | Responsibility |
| --- | --- |
| `Hooks\DataHandlerHook` | Collects every record localized in a DataHandler run (from `copyMappingArray`, so inline children like `sys_file_reference` are included), triggers translation in `afterFinish`, shows flash messages. |
| `Service\TranslationService` | Groups segments per language pair, chunks at ~900k characters, writes results through a second DataHandler run (guarded by `TranslationService::$running` to avoid recursion), regenerates page slugs, keeps skipped code fields verbatim. |
| `Service\FieldCollector` | TCA-driven field selection: `input`/`text`, not `l10n_mode=exclude`, not read-only, no numeric/date/email evals, no code editors; detects rich text. |
| `Service\LanguageResolver` | Maps a site language to a Supertext target (`supertext_code` or locale `de-CH`), source (default language's primary subtag), politeness. |
| `Api\HtmlDocument` | Builds `<div data-st-id="N">…</div>` documents and parses them back; plain text is escaped and line breaks travel as `<br>`. |
| `Api\SupertextClient` | Supertext AI file translation API v1. |
| `Command\LocalizeCommand` | `supertext:localize <page> <language> [--recursive]`. |
| `Configuration\ExtensionSettings` | Typed extension configuration plus `SUPERTEXT_API_KEY` / `SUPERTEXT_API_ENDPOINT` env overrides. |

## Supertext API protocol

Shared with the WordPress plugin and every other Supertext CMS plugin:

1. `POST {base}translate/ai/file` — multipart: `file` (part `Content-Type` exactly `text/html`, no charset, or the API answers 415), `target_lang` (BCP-47, e.g. `de-CH`), optional `source_lang` (primary subtag only, e.g. `de`), optional `politeness` (`more`/`less`) → `{file_id}`
2. `GET …/{file_id}/status` until `done` (`error`, `limit_exceeded`, `deleted` are terminal)
3. `GET …/{file_id}/translation` → translated HTML
4. `DELETE …/{file_id}` (files also expire after 24 h)

Auth header: `Authorization: Supertext-Auth-Key <key>`. Base URLs: `https://api.supertext.com/v1/` (live), `api.staging…`, `api.testing…`.

## Local development

```bash
composer create-project typo3/cms-base-distribution:^14.3 t3
cd t3
vendor/bin/typo3 setup --driver=sqlite --create-site=http://localhost:8080/ ...
git clone https://github.com/Supertext/Typo3-Supertext-Translation packages/supertext_translation
composer require supertext/typo3-translation:@dev
vendor/bin/typo3 extension:setup -e supertext_translation
```

Add a second language with a locale to `config/sites/*/config.yaml`, create some content, then:

```bash
SUPERTEXT_API_KEY=... vendor/bin/typo3 supertext:localize 1 1 --recursive
```

To test without a real key, point `SUPERTEXT_API_ENDPOINT` at a mock that implements the four calls above (one that prefixes every text node with `[<lang>] ` is enough to see the round trip).

## Tests

```bash
php Tests/HtmlDocumentTest.php   # HTML packing round trip, no TYPO3 needed
```

CI (`.github/workflows/deploy.yml`) lints all PHP files on 8.2, 8.3 and 8.4 and runs the test on every push and pull request.

## Deployment to the demo

Pushes to `main` deploy to the TYPO3 demo on demo.supertext.com once these repository secrets exist (*Settings → Secrets and variables → Actions*):

| Secret | |
| --- | --- |
| `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY` | SSH access to the demo server |
| `DEPLOY_PATH` | TYPO3 project root (folder with `composer.json`) |
| `DEPLOY_PORT`, `DEPLOY_PHP`, `DEPLOY_COMPOSER`, `DEPLOY_KNOWN_HOSTS` | optional |

The job rsyncs the extension into `packages/supertext_translation/`, requires it on first deploy, runs `extension:setup` and flushes caches. Without the secrets it skips quietly.

## Releasing

1. Bump `version` in `ext_emconf.php`.
2. Add an entry to `CHANGELOG.md`.
3. Tag `vX.Y.Z` on `main`.

## Conventions

- Strict types, final classes, constructor injection (`Configuration/Services.yaml`).
- Exception codes are Unix timestamps (`1759500xxx` range for the API client).
- Keep the three docs in `docs/` current with every change (see `CLAUDE.md`).

## Known limitations / roadmap

- Translation runs synchronously inside the editor's request (up to `pollTimeout`). Planned: queue + scheduler task for large pages, and webhook completion via TYPO3 `reactions` if Supertext offers callbacks.
- FlexForm fields and container/grid extensions are not translated yet.
- No "retranslate" action for existing translations.
- Human (professional) translation orders are not supported yet (the WordPress plugin has them).
- Not yet tested on TYPO3 13.4 or against the live API.
