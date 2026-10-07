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
| `Service\FieldCollector` | TCA-driven field selection: `input`/`text`, not `l10n_mode=exclude`, not read-only, no numeric/date/email evals, no code editors, no technical reference fields (`sys_file_reference.tablenames`/`fieldname`/`table_local`, link `target`); detects rich text. |
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

Auth header: `Authorization: Supertext-Auth-Key <key>`. The key may be configured with or without the `Supertext-Auth-Key ` prefix; the client strips it and always sends exactly one. The header name must be `Authorization` (the live API answers 403 to `Authentication`).

**Rate limit:** the API limits requests per second per key (HTTP 429, `RATE_LIMIT_EXCEEDED`); translating into several languages at once hits it. The client retries a 429 up to 4 times, waiting for `Retry-After` if sent, otherwise 1, 2, 4 and 8 seconds (plus jitter), before reporting *Too many requests*. Base URLs: `https://api.supertext.com/v1/` (live), `api.staging…`, `api.testing…`.

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

CI (`.github/workflows/ci.yml`) lints all PHP files on 8.2, 8.3 and 8.4, runs the test and syntax-checks the demo entrypoint on every push and pull request.

## Demo (Railway)

The public demo is a container built from `demo/Dockerfile`: TYPO3 14.3 with the official **Camino** theme and its demo content (7 pages, ~50 content elements), English as default language plus German and French (Switzerland), and this extension installed from the repo itself. It runs on Railway in the `supertext-cms-demos` project, service `typo3`, region EU West (Amsterdam): <https://typo3-production.up.railway.app/> (backend: `/typo3/`).

**Deploys:** Railway watches `main` of this repository and rebuilds on every push, so a merged change is live a few minutes later. No GitHub secrets are needed.

**What's in `demo/`:**

| File | Purpose |
| --- | --- |
| `Dockerfile` | `php:8.3-apache` + extensions, `composer install` from the lock file, extension copied to `packages/supertext_translation` |
| `composer.json`, `composer.lock` | The demo project (TYPO3 core packages, Camino, this extension via a path repository) |
| `entrypoint.sh` | Keeps only Apache's prefork MPM (Railway otherwise fails with *More than one MPM loaded*), links persistent folders into `/data`, installs TYPO3 on first boot, creates missing demo accounts and runs `extension:setup` + `cache:flush` on every boot |
| `site-config.yaml` | Site configuration written on first boot (languages, `supertext_politeness`) |
| `additional.php` | Reverse-proxy and trusted-host settings for Railway's TLS proxy |
| `apache.conf`, `php.ini` | Web server and PHP settings |

**Persistent state** lives on a Railway volume (`typo3-data`) mounted at `/data` — the Dockerfile has no `VOLUME` line because Railway's builder rejects it: `var/` (SQLite database, caches, logs), `fileadmin/`, `sites/` and `system/settings.php`. Everything else comes from the image, so code changes never touch content. To reset the demo to fresh Camino content, delete the files on the volume (or recreate the volume) and redeploy.

**Service variables:**

| Variable | |
| --- | --- |
| `DEMO_ADMIN_EMAIL`, `DEMO_ADMIN_PASSWORD` | Admin backend user (the e-mail address is also the username). The password is required for the first boot, which installs TYPO3. Older names still work as fallbacks: `TYPO3_ADMIN_PASSWORD`, `TYPO3_ADMIN_USER` (default `admin`), `TYPO3_ADMIN_EMAIL`. |
| `DEMO_EDITOR_EMAIL`, `DEMO_EDITOR_PASSWORD` | Optional second account, e.g. for automated tests and screenshots. TYPO3 ships no ready-made editor group, so it is an admin without maintainer rights (no Install Tool or system maintenance). |
| `TYPO3_PROJECT_NAME` | Optional, first boot only |
| `SUPERTEXT_API_KEY` | Supertext key used by the extension |
| `SUPERTEXT_API_ENDPOINT` | Optional, e.g. the staging API |
| `TYPO3_TRUSTED_HOSTS` | Optional regex of allowed host names (default: any); on the demo it lists the Railway domain **and `healthcheck.railway.app`** (the host Railway's healthcheck sends; without it every check gets a 500) |
| `PORT` | Port Apache listens on; `8080` on Railway, matching the domain's target port |
| `RAILWAY_DOCKERFILE_PATH` | `demo/Dockerfile` (the build context is the repo root) |

**Demo accounts:** on every boot the entrypoint creates the `DEMO_ADMIN` and `DEMO_EDITOR` accounts if they don't exist yet, using TYPO3's `backend:user:create` with the values passed as environment variables (never on the command line). Existing accounts are never changed — to change a password, use the backend (*User settings* or *Backend users*). Passwords must meet TYPO3's password policy: at least 8 characters with an upper-case letter, a lower-case letter, a number and a special character. If one doesn't, that account is skipped and the log says which rule failed; the demo still starts.

**Run it locally:**

```bash
docker build -f demo/Dockerfile -t supertext-typo3-demo .
docker run --rm -p 8080:80 -v typo3demo:/data \
  -e DEMO_ADMIN_EMAIL=you@example.com -e DEMO_ADMIN_PASSWORD='Choose-one1!' -e SUPERTEXT_API_KEY=... supertext-typo3-demo
# frontend http://localhost:8080/  backend http://localhost:8080/typo3/
```

**Updating TYPO3 in the demo:** `cd demo && composer update "typo3/*"` in a checkout where `demo/packages/supertext_translation` exists (or mirror the container layout), then commit the new `composer.lock`. Composer must run with plugins enabled (`COMPOSER_ALLOW_SUPERUSER=1` when root), otherwise `vendor/typo3/autoload-include.php` is missing and TYPO3 fails with *"…/vendor/typo3/sysext/*/ directory does not exist"*.

The older install on demo.supertext.com (`/typo3-translation/`) is no longer deployed to and can be removed.

## Docs screenshots

The images in `docs/images/` are generated by `Tests/Docs/screenshots.mjs` (Playwright) from a local, freshly installed demo, against `Tests/Docs/stand-in.mjs`, which answers like the Supertext API and returns real German for the Camino FAQ page (`sample-de.json`). Regenerate them whenever the backend screens they show change:

```bash
# a local TYPO3 like the demo container: demo/composer.json + this extension in packages/,
# set up with `typo3 setup --distribution=theme_camino`, demo/site-config.yaml as the site config,
# and a system maintainer account (the setup admin)
cd Tests/Docs && npm install && npx playwright install chromium
npm run stand-in &
# serve TYPO3 with SUPERTEXT_API_KEY=test-key SUPERTEXT_API_ENDPOINT=http://127.0.0.1:8765/v1/
# (e.g. php -S 127.0.0.1:8090 -t public with a router that sends everything else to public/index.php)
BASE_URL=http://127.0.0.1:8090 DEMO_ADMIN_EMAIL=... DEMO_ADMIN_PASSWORD=... npm run screenshots
```

Start from a database where the FAQ page has no German translation yet. Locally, drop `demo/additional.php`: its reverse-proxy settings make TYPO3 redirect to HTTPS.

## Releasing

Releases are published by `.github/workflows/release.yml` when the version is officially bumped; nobody tags or creates releases by hand.

1. Move the *Unreleased* entries in `CHANGELOG.md` under a new `## X.Y.Z — YYYY-MM-DD` section, and keep an empty *Unreleased* above it.
2. Set the same version in:
   - `ext_emconf.php`: `version`, shown in TYPO3's extension manager
3. Push to `main`. The workflow checks that the version files match `CHANGELOG.md`, then tags `vX.Y.Z` and creates the GitHub release with the CHANGELOG section as notes (0.x versions as pre-releases). A push that adds no new version does nothing, and a version that is already released is skipped. After fixing a failed run, start it again with *Run workflow* on the *Release* workflow.
## Conventions

- Strict types, final classes, constructor injection (`Configuration/Services.yaml`).
- Exception codes are Unix timestamps (`1759500xxx` range for the API client).
- Keep the three docs in `docs/` current with every change (see `CLAUDE.md`).

## Known limitations / roadmap

- Translation runs synchronously inside the editor's request (up to `pollTimeout`). Planned: queue + scheduler task for large pages, and webhook completion via TYPO3 `reactions` if Supertext offers callbacks.
- FlexForm fields and container/grid extensions are not translated yet.
- No "retranslate" action for existing translations.
- Human (professional) translation orders are not supported yet (the WordPress plugin has them).
- Not yet tested on TYPO3 13.4.
