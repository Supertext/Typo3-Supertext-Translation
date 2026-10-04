# Installation guide — Supertext Translation for TYPO3

For administrators setting up the extension on a TYPO3 site.

## Just want to try it?

A ready-to-run container with TYPO3 14.3, demo content in English, German and French, and this extension installed is in `demo/` (`docker build -f demo/Dockerfile .`). It is also what runs the public Supertext demo. See the *Demo* section of the [developer guide](DEVELOPER.md#demo-railway).

## Requirements

| | |
| --- | --- |
| TYPO3 | 14.3 LTS (tested), 13.4 LTS (supported, not yet tested) |
| PHP | 8.2 or newer, with `ext-dom` |
| Install mode | Composer-based TYPO3 (classic/non-Composer installs are not supported yet) |
| Supertext | An account with an API key (supertext.com → Integrations → API) |
| Network | The web server must reach `https://api.supertext.com` over HTTPS |

## 1. Add the package

The extension is not on Packagist yet. Install it from GitHub or from a local folder.

**From GitHub** — add the repository to your project's `composer.json`, then require it:

```bash
composer config repositories.supertext-typo3 vcs https://github.com/Supertext/Typo3-Supertext-Translation
composer require supertext/typo3-translation:dev-main
```

The repository is private for now, so Composer needs a GitHub token with read access (`composer config --global github-oauth.github.com <token>`).

**From a local folder** — the TYPO3 base distribution already reads packages from `packages/*`:

```bash
# copy or clone the repo to packages/supertext_translation, then
composer require supertext/typo3-translation:@dev
```

## 2. Activate it

```bash
vendor/bin/typo3 extension:setup -e supertext_translation
vendor/bin/typo3 cache:flush
```

## 3. Set the API key

Either:

- **Backend:** *Admin Tools → Settings → Extension Configuration → supertext_translation* → paste the key into *Supertext API key*, or
- **Environment variable:** `SUPERTEXT_API_KEY=...` (takes precedence over the backend setting — recommended for servers, as the key then stays out of the database and `config/system/settings.php`).

## 4. Configure the site languages

Each target language needs a locale in the site configuration (*Site Management → Sites*, or `config/sites/<site>/config.yaml`). The locale is sent to Supertext as the target language: `de_CH.UTF-8` → `de-CH`, `fr_FR.UTF-8` → `fr-FR`.

Optional per-language keys (edit the YAML directly):

```yaml
languages:
  -
    languageId: 1
    locale: de_CH.UTF-8
    supertext_code: de-CH          # override the code sent to Supertext
    supertext_politeness: more     # more = formal (Sie/vous), less = informal (du/tu)
```

## 5. Check it works

Localize a page in the Page module (*Translate* button). After the wizard finishes you should see a green "Supertext translated N field(s)…" message. Or from the CLI:

```bash
vendor/bin/typo3 supertext:localize <page-uid> <language-id>
```

## All settings

| Setting | Default | Purpose |
| --- | --- | --- |
| `enabled` | on | Translate automatically on localization |
| `apiKey` | – | Supertext API key (`SUPERTEXT_API_KEY` wins) |
| `environment` | live | `live`, `staging` or `testing` API |
| `endpoint` | – | Custom base URL (`SUPERTEXT_API_ENDPOINT` wins) |
| `pollTimeout` | 180 s | Maximum wait per translation |
| `pollInterval` | 2 s | Time between status checks |
| `skipBodytextCTypes` | `html` | Content types whose bodytext is code and must not be translated |
| `regenerateSlugs` | on | Build translated page URLs from translated titles |

## Updating

```bash
composer update supertext/typo3-translation
vendor/bin/typo3 extension:setup -e supertext_translation
vendor/bin/typo3 cache:flush
```

## Uninstalling

```bash
composer remove supertext/typo3-translation
vendor/bin/typo3 cache:flush
```

Existing translations stay untouched; only automatic translation stops.

## Troubleshooting

| Message | Cause / fix |
| --- | --- |
| *No Supertext API key configured* | Set the key (step 3). Records were still localized as plain copies. |
| *Authentication failed* | The key is wrong or revoked. |
| *has no site language N* | The page isn't inside a site, or the language isn't defined in that site's configuration. |
| *Timed out waiting* | Very large pages; raise `pollTimeout` (and PHP's `max_execution_time`). |
| *Could not reach Supertext* | The server can't make outbound HTTPS calls; check firewall/proxy (`$GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy']`). |

Errors are also written to the TYPO3 log (`var/log/typo3_*.log`).

## Security note for Composer installs

The web server's document root must point to the project's `public/` folder. If the whole project folder is web-accessible, files such as `composer.lock` and `config/` can be fetched from the internet.
