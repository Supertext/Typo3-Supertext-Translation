# User guide — Supertext Translation for TYPO3

For editors. Once an administrator has installed the extension (see [INSTALLATION.md](INSTALLATION.md)), you translate content exactly as you always do in TYPO3 — Supertext fills in the text automatically.

## Try it on the demo

The Supertext TYPO3 demo (ask Supertext for the address and a backend login) has a sample website in English with German and French set up as target languages. Translate any page as described below and open the German or French version of the site to see the result. Pages that aren't translated yet show the English text in the German and French versions.

## Translate a page

1. Open the **Page** module and select the page.
2. Switch the language selector at the top to the target language (or choose *Languages* view).
3. If the page itself has no translation yet, click **Create new translation of this page** and choose the language.
4. Click **Translate** in the column of the target language. In the wizard, pick:
   - **Translate** (connected mode) — the translation stays linked to the original, the usual choice, or
   - **Copy** (free mode) — an independent copy you can restructure freely.
5. Select the content elements and finish the wizard.

A green message confirms what was translated, for example *"Supertext translated 9 field(s) in 6 record(s) into Deutsch (de-CH)."*

## Review and publish

New translations are **hidden** — TYPO3's default — so nothing goes live unreviewed.

1. Read through the translated page and elements, correct anything you'd phrase differently.
2. Unhide the page translation and the content elements (eye icon) when you're happy.

## What gets translated

- Page title, navigation title, subtitle, SEO and social media texts, abstracts
- Content element headers, subheaders and text (formatting, links and lists are kept)
- Image and file captions, alt texts and titles
- Table content, row by row

The page's URL (slug) is rebuilt from the translated title, e.g. `/about-us` → `/de/ueber-uns`.

## What is *not* translated

- **HTML** content elements — the code is copied unchanged.
- Numbers, dates, e-mail addresses, links' targets, colours and other technical fields.
- Content that already has a translation in that language — translating twice never overwrites your edits.

## Formal and informal language

Your administrator can set each language to formal (*Sie/vous*) or informal (*du/tu*). Ask them if the tone doesn't fit your site.

## When something goes wrong

If Supertext can't translate (no API key, network problem, quota reached), you'll see a yellow warning. The records are still created — as untranslated copies with TYPO3's usual *[Translate to …]* prefix — so you can translate by hand, or delete them and try again later.

## Tips

- Translate a whole page at once rather than element by element: it's one request to Supertext and the context makes the translation more consistent.
- To retranslate an element, delete its translation and translate again.
