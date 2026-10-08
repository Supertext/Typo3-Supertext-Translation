# Changelog

## Unreleased

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
