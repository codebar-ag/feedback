// Renders the README screenshots (../.github/screenshots/*.png) from
// fixtures/demo.html. Needs a build (npm run build) and Playwright browsers.
import { spawn } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium, devices } from '@playwright/test';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = resolve(root, '../.github/screenshots');
const port = 4179;
const base = `http://localhost:${port}`;
mkdirSync(out, { recursive: true });

const server = spawn(process.execPath, ['--experimental-strip-types', 'tests/e2e/server.ts'], {
  cwd: root,
  env: { ...process.env, PORT: String(port) },
  stdio: 'ignore',
});
await new Promise((r) => setTimeout(r, 1500));

const browser = await chromium.launch();
const settle = (page) =>
  page.locator('codebar-feedback').evaluate(async (host) => {
    await Promise.all(
      host.shadowRoot
        .getAnimations()
        .filter((a) => a.effect?.getTiming().iterations !== Infinity)
        .map((a) => a.finished),
    );
  });

async function open(context, sid, params = {}) {
  const page = await context.newPage();
  await page.request.post(`${base}/s/${sid}/__mock`, { data: { color: '#0A7C66', responses: [{ status: 201, delayMs: 300 }] } });
  await page.goto(`${base}/fixtures/demo.html?${new URLSearchParams({ sid, ...params })}`);
  await page.locator('html[data-feedback-ready="1"]').waitFor();
  await page.waitForTimeout(300);
  return page;
}

const w = (page, sel) => page.locator('codebar-feedback').locator(sel);

try {
  const desktop = await browser.newContext({ viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2 });

  // 1. Form without identity
  let page = await open(desktop, 'shot-form');
  await w(page, 'button.l').click();
  await w(page, 'input[type=email]').fill('anna@beispiel.ch');
  await w(page, 'textarea').fill('Die Schaltfläche «Jetzt starten» reagiert auf dem iPad nicht.');
  await page.mouse.move(0, 0);
  await settle(page);
  await page.screenshot({ path: resolve(out, 'form.png') });

  // 2. Identity, screenshot preview and validation-free form
  page = await open(desktop, 'shot-identity', { identity: '1' });
  await w(page, 'button.l').click();
  await w(page, 'textarea').fill('Im Kundenportal fehlt der Export als CSV.');
  await w(page, '.tr button').first().click();
  await w(page, '.tb').waitFor();
  await page.mouse.move(700, 150);
  await page.mouse.down();
  await page.mouse.move(1140, 330, { steps: 8 });
  await page.mouse.up();
  await w(page, '.tb [data-tool=marker]').click();
  await page.mouse.move(60, 470);
  await page.mouse.down();
  await page.mouse.move(380, 476, { steps: 12 });
  await page.mouse.up();
  await page.mouse.move(640, 760);
  await page.screenshot({ path: resolve(out, 'annotate.png') });
  await w(page, '.tb [data-action=done]').click();
  await page.mouse.move(0, 0);
  await settle(page);
  await page.screenshot({ path: resolve(out, 'identity.png') });

  // 3. Success page
  await w(page, 'button[type=submit]').click();
  await w(page, '.st.ok').waitFor();
  await page.waitForTimeout(150);
  await page.screenshot({ path: resolve(out, 'success.png') });

  // 4. Mobile bottom sheet
  const mobile = await browser.newContext({ ...devices['iPhone 15'] });
  page = await open(mobile, 'shot-mobile', { locale: 'fr_CH' });
  await w(page, 'button.l').tap();
  await w(page, 'input[type=email]').fill('luc@exemple.ch');
  await w(page, 'textarea').fill('Le bouton de connexion est caché par la barre.');
  await settle(page);
  await page.screenshot({ path: resolve(out, 'mobile.png') });

  console.log(`screenshots written to ${out}`);
} finally {
  await browser.close();
  server.kill();
}
