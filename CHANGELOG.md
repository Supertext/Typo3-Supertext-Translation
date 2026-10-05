# Changelog

## Unreleased
- Docs: screenshots in the user and installation guides (TYPO3 14 Localize wizard, result, extension configuration, site languages), regenerated with `Tests/Docs/screenshots.mjs`; translation steps updated for TYPO3 14's wizard.
- Fix: technical fields of image and file references (`tablenames`, `fieldname`, `table_local`) and link targets are no longer sent for translation. Translating them could detach images from translated content elements.
- Demo: accounts from `DEMO_ADMIN_*` and `DEMO_EDITOR_*` variables, created on every boot if missing (`TYPO3_ADMIN_*` still work); the admin password is no longer passed on the command line.
- Demo container (`demo/`): TYPO3 14.3 with Camino demo content in English, German and French, deployed to Railway on every push to `main`. Replaces the SSH deploy to demo.supertext.com.
- CI workflow renamed to `ci.yml`; the SSH deploy job and its secrets are gone.
- Added installation guide, user guide and developer guide (`docs/`).

## 0.1.0 — 2026-10-03
- First version: Supertext AI translation on localization (Page module wizard, new translation, List module, CLI).
- One Supertext request per target language and DataHandler run; TCA-driven field selection; rich text preserved; HTML elements kept verbatim.
- Per-language Supertext code and politeness via site configuration; slugs rebuilt from translated titles.
- `supertext:localize` CLI command; CI and SSH deploy workflow.
