#!/usr/bin/env node
/**
 * Regenerates docs/images from a local, freshly installed TYPO3 demo whose extension
 * talks to stand-in.mjs. See docs/DEVELOPER.md -> Docs screenshots.
 *
 *   BASE_URL (default http://127.0.0.1:8090), DEMO_ADMIN_EMAIL, DEMO_ADMIN_PASSWORD
 *   (must be a system maintainer: the setup admin is one).
 */
import { chromium } from 'playwright';

const B = process.env.BASE_URL || 'http://127.0.0.1:8090';
const USER = process.env.DEMO_ADMIN_EMAIL || 'anna.muster@example.com';
const PASSWORD = process.env.DEMO_ADMIN_PASSWORD || 'Docs12345!';
const PAGE_ID = Number(process.env.PAGE_ID || 5); // Camino demo: "FAQs"
const OUT = new URL('../../docs/images', import.meta.url).pathname;

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1400, height: 940 } })).newPage();
const shot = (name, clip) => page.screenshot({ path: `${OUT}/${name}.png`, ...(clip ? { clip } : {}) });
const pad = (r, p = 8) => ({ x: Math.max(0, r.x - p), y: Math.max(0, r.y - p), width: r.width + 2 * p, height: r.height + 2 * p });
const boxOf = async (locator) => pad(await locator.boundingBox());
const frame = () => page.frame('list_frame');

// Log in
await page.goto(`${B}/typo3/`);
await page.fill('input[name=username]', USER);
await page.fill('input[type=password]', PASSWORD);
await page.click('button[type=submit]');
await page.waitForURL(/\/typo3\/(main|module)/, { timeout: 30000 });

// --- User guide -------------------------------------------------------------
await page.goto(`${B}/typo3/module/web/layout?id=${PAGE_ID}`);
await frame().getByRole('button', { name: /English/ }).first().waitFor();
await frame().getByRole('button', { name: /English/ }).first().click();
await page.waitForTimeout(600);
await shot('language-menu');

await frame().getByText('Deutsch (Schweiz)').first().click();
await page.getByText(/Step \d of \d/).first().waitFor({ timeout: 30000 });
await page.waitForTimeout(1200);
// The wizard renders in a modal inside web components; find the dialog box from its title.
const dialogBox = (titlePattern = '^Localize: ') =>
  page.evaluate((pattern) => {
    const walk = (root) => [...root.querySelectorAll('*')].flatMap((el) => [el, ...(el.shadowRoot ? walk(el.shadowRoot) : [])]);
    const title = walk(document).find((el) => el.childElementCount === 0 && new RegExp(pattern).test(el.textContent.trim()));
    let el = title;
    while (el) {
      const r = el.getBoundingClientRect();
      if (r.height > 300 && r.width > 600) return { x: r.x - 8, y: r.y - 8, width: r.width + 16, height: r.height + 16 };
      el = el.parentElement || el.getRootNode().host;
    }
    throw new Error('dialog not found: ' + pattern);
  }, titlePattern);
const next = (label = 'Next') => page.locator('.wizard-actions button').filter({ hasText: label }).last().click();

await shot('wizard-content', await dialogBox()); // step: content selection
await next();
await page.waitForTimeout(1200);
await shot('wizard-mode', await dialogBox()); // step: Translate / Copy
await next();
await page.waitForTimeout(1200);
await shot('wizard-confirm', await dialogBox()); // step: confirm
await next('Localize'); // Supertext translates here
await page.getByText(/Localization completed/).waitFor({ timeout: 120000 });
await next('Finish');
await frame().getByText(/Supertext translated/).waitFor({ timeout: 30000 });
await page.waitForTimeout(1500);
await shot('translated-page');

// --- Installation guide -----------------------------------------------------
// Extension configuration (System -> Settings; asks to confirm the password first)
await page.locator('a[href*="/module/system/settings"]').first().click();
await page.waitForTimeout(2500);
const verify = page.frames().some((f) => f.url().includes('/sudo-mode/'));
if (verify) {
  await page.keyboard.type(PASSWORD);
  await page.keyboard.press('Enter');
}
await frame().getByText(/Configure extensions/i).first().click({ timeout: 30000 });
await page.waitForTimeout(2500);
await page.frames().find((f) => f.url().includes('extensionConfiguration') || true).page().getByText('supertext_translation', { exact: true }).last().click();
await page.waitForTimeout(1500);
await shot('extension-configuration', await dialogBox('^Extension Configuration$'));
await page.keyboard.press('Escape');
await page.waitForTimeout(800);

// Site languages (Sites -> Setup -> edit the site -> Languages)
await page.locator('a[href*="/module/site/configuration"]').first().click();
await page.waitForTimeout(2500);
await frame().locator('a[href*="edit"], a[title*="Edit"]').first().click();
await page.waitForTimeout(2500);
await frame().getByRole('tab', { name: /Languages/i }).click();
await page.waitForTimeout(1500);
await shot('site-languages', { x: 240, y: 60, width: 1160, height: 520 });

await browser.close();
console.log(`Screenshots written to ${OUT}`);
