# Changelog

## Unreleased

- Added: support for PHP 8.5 (now tested in CI next to 8.2, 8.3 and 8.4).
- Changed: **no more guessed language codes.** Supertext accepts only its own codes (regional for German, English, French, Italian and Portuguese, e.g. `de-CH`; bare for most others, e.g. `es`, `nl`, `sl`; `zh-Hans`, `sr-Latn`). A site language is sent with its `supertext_code`, else its locale if that is a Supertext code; otherwise the translation into it stops with *The language … has no Supertext language yet* and nothing is sent. Locales without region no longer get a default region (`de` → `de-DE`, `es` → `es-ES`, …; several of those were rejected by Supertext). After updating, check the new Status block and map the languages it marks as *Not mapped*.
- Added: a *Supertext* palette in the site language form (Sites → Setup → Languages) to choose the Supertext language from the list of Supertext languages and the tone; stored as `supertext_code` / `supertext_politeness` as before.
- Added: a *Status* block at the top of the extension configuration: the latest API key check with its time, whether version information is sent (with the ping address), and every site language with the Supertext code it is sent as (unmapped languages and a tone a language doesn't offer are marked).
- Added: automatic API key check (Supertext's cost-free `features` call) after the configuration was saved, when the key changes and when Supertext rejects the key during a translation; the result shows in the Status block, with the links to create a Supertext account and to generate a key.
- Added: setting *Send version information to Supertext* (on by default; `SUPERTEXT_VERSION_REPORTS=0` turns it off): sends the site URL and the extension, TYPO3, PHP and SDK versions, whether a key is set and the key check result to the Supertext Commando Center after a configuration save and an update. No content, no API key, no personal data. Never slows down or breaks a save or a translation.
- Added: public ping endpoint `GET /supertext/ping?challenge=…` (answers 404 while version information is off), so Supertext can see that the site is reachable.
- Added: messages for a language without Supertext language and for a tone Supertext doesn't offer for a language, in English, German, French and Italian.
- Fixed: bundled Supertext SDK 0.2.1 (`eeba8fa`): one `supertext_code` serves both directions; the default language is sent as the source in its source form (`de-CH` → `de`), so English as the source no longer fails with an `en-US` Supertext language.
- Changed: bundled Supertext SDK 0.2.0 (`c66cb4d`, API classes in the `V1` namespace). The User-Agent now carries the version from `ext_emconf.php`.
- Changed: the extension now talks to Supertext through the official [Supertext SDK for PHP](https://github.com/Supertext/Supertext-API-SDK-and-Guide) instead of its own API client, HTML packer and language code rules. The SDK is bundled in the extension (`lib/supertext-sdk`), so there is nothing extra to install. Same behaviour; requests go through TYPO3's HTTP client as before.
- Added: clear messages when Supertext rejects a language pair or returns an incomplete translation (in English, German, French and Italian).
- Demo: TYPO3 14.3.9 (security update).
- Fixed: target languages whose locale has no region (e.g. `de`) are now sent to Supertext as a regional code (`de-DE`, `fr-FR`, `en-US`, …), which Supertext requires; a `supertext_code` override is normalized (`de_ch` → `de-CH`). The source is always sent without region.
- Fixed: a target language Supertext doesn't know is now reported as an unsupported language pair (was: *file not found*).
- Fixed: locale variants such as `de_DE_formal` (in a `supertext_code`) are sent to Supertext as `de-DE`.
- Added: French and Italian interface (and German where it was missing): the extension configuration and the messages after a translation follow the backend user's language. The authentication-failed message now also links to Supertext account signup.

## 0.1.0 — 2026-10-07

- First version: Supertext AI translation on localization (Page module wizard, new translation, List module, CLI).
- One Supertext request per target language and DataHandler run; TCA-driven field selection; rich text preserved; HTML elements kept verbatim.
- Per-language Supertext code and politeness via site configuration; slugs rebuilt from translated titles.
- `supertext:localize` CLI command; CI and SSH deploy workflow.
- Docs/UI: the *Supertext API key* setting, the missing-key and authentication-failed messages, the installation guide, README and demo `.env.example` now link to Supertext account signup and API key generation (supertext.com → Integrations → API, Admin role required).
- Docs: how to translate content added after the page translation (Language Comparison → Translate).
- Fix: translating into several languages at once no longer fails with *Too many requests*: requests that hit Supertext's per-second rate limit are retried automatically.
- Fix: the API key now works whether it is entered with or without the `Supertext-Auth-Key ` prefix Supertext shows it with.
- Docs: screenshots in the user and installation guides (TYPO3 14 Localize wizard, result, extension configuration, site languages), regenerated with `Tests/Docs/screenshots.mjs`; translation steps updated for TYPO3 14's wizard.
- Fix: technical fields of image and file references (`tablenames`, `fieldname`, `table_local`) and link targets are no longer sent for translation. Translating them could detach images from translated content elements.
- Demo: accounts from `DEMO_ADMIN_*` and `DEMO_EDITOR_*` variables, created on every boot if missing (`TYPO3_ADMIN_*` still work); the admin password is no longer passed on the command line.
- Demo container (`demo/`): TYPO3 14.3 with Camino demo content in English, German and French, deployed to Railway on every push to `main`. Replaces the SSH deploy to demo.supertext.com.
- CI workflow renamed to `ci.yml`; the SSH deploy job and its secrets are gone.
- Added installation guide, user guide and developer guide (`docs/`).
