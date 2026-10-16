// Renders the README screenshots (../.github/screenshots/*.png): the widget
// alone on an empty page. Needs a build (npm run build) and Playwright browsers.
import { spawn } from 'node:child_process';
import { mkdirSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = resolve(root, '../.github/screenshots');
const port = 4179;
const base = `http://localhost:${port}`;
rmSync(out, { recursive: true, force: true });
mkdirSync(out, { recursive: true });

const server = spawn(process.execPath, ['--experimental-strip-types', 'tests/e2e/server.ts'], {
  cwd: root,
  env: { ...process.env, PORT: String(port) },
  stdio: 'ignore',
});
await new Promise((r) => setTimeout(r, 1500));

const browser = await chromium.launch();
const w = (page, sel) => page.locator('codebar-feedback').locator(sel);
const settle = (page) =>
  page.locator('codebar-feedback').evaluate(async (host) => {
    await Promise.all(
      host.shadowRoot
        .getAnimations()
        .filter((a) => a.effect?.getTiming().iterations !== Infinity)
        .map((a) => a.finished),
    );
  });

async function open(context, sid, params = {}, responses = [{ status: 201, delayMs: 200 }]) {
  const page = await context.newPage();
  await page.request.post(`${base}/s/${sid}/__mock`, { data: { color: '#0A7C66', responses } });
  await page.goto(`${base}/fixtures/blank.html?${new URLSearchParams({ sid, locale: 'en_CH', ...params })}`);
  await page.locator('html[data-feedback-ready="1"]').waitFor();
  await page.waitForTimeout(200);
  return page;
}

/** Neutral placeholder blocks, so the captured screenshot shows a page (not part of the widget shots). */
const skeleton = (page, on) =>
  page.evaluate((show) => {
    document.getElementById('skeleton')?.remove();
    if (!show) return;
    const el = document.createElement('div');
    el.id = 'skeleton';
    el.innerHTML = `
      <div style="height:64px;background:#fff"></div>
      <div style="margin:56px 48px 0;width:46%;height:28px;border-radius:8px;background:#c9d4e3"></div>
      <div style="margin:18px 48px 0;width:38%;height:12px;border-radius:6px;background:#dbe2ec"></div>
      <div style="margin:10px 48px 0;width:32%;height:12px;border-radius:6px;background:#dbe2ec"></div>
      <div style="display:flex;gap:16px;margin:40px 48px 0">
        <div style="flex:1;height:150px;border-radius:14px;background:#fff"></div>
        <div style="flex:1;height:150px;border-radius:14px;background:#fff"></div>
        <div style="flex:1;height:150px;border-radius:14px;background:#fff"></div>
      </div>`;
    document.body.prepend(el);
  }, on);

/** Screenshot of the panel and launcher with some margin. */
async function shotWidget(page, name) {
  await page.mouse.move(0, 0);
  await settle(page);
  const panel = await w(page, 'section.p').boundingBox();
  const launcher = await w(page, 'button.l').boundingBox();
  const pad = 28;
  const x = Math.min(panel.x, launcher.x) - pad;
  const y = panel.y - pad;
  const right = Math.max(panel.x + panel.width, launcher.x + launcher.width) + pad;
  const bottom = launcher.y + launcher.height + pad;
  await page.screenshot({ path: resolve(out, name), clip: { x, y, width: right - x, height: bottom - y } });
}

try {
  const desktop = await browser.newContext({ viewport: { width: 1000, height: 820 }, deviceScaleFactor: 2 });

  let page = await open(desktop, 'shot-form');
  await w(page, 'button.l').click();
  await w(page, 'input[type=email]').fill('anna@example.com');
  await w(page, 'textarea').fill('The "Get started" button does nothing on my iPad.');
  await w(page, 'textarea').blur();
  await shotWidget(page, 'form.png');

  page = await open(desktop, 'shot-identity', { identity: JSON.stringify({ id: '4711', name: 'Anna Muster', email: 'anna@example.com' }) });
  await w(page, 'button.l').click();
  await w(page, 'textarea').fill('The CSV export is missing in the customer portal.');
  await skeleton(page, true);
  await w(page, '.tr button').first().click();
  await w(page, '.tb').waitFor();
  await skeleton(page, false);
  // Mark the second card of the placeholder page.
  await page.mouse.move(343, 230);
  await page.mouse.down();
  await page.mouse.move(657, 402, { steps: 8 });
  await page.mouse.up();
  await w(page, '.tb [data-tool=marker]').click();
  await page.mouse.move(56, 134);
  await page.mouse.down();
  await page.mouse.move(500, 136, { steps: 12 });
  await page.mouse.up();
  await page.mouse.move(980, 800);
  await page.screenshot({ path: resolve(out, 'annotate.png'), clip: { x: 0, y: 0, width: 1000, height: 460 } });
  await w(page, '.tb [data-action=done]').click();
  await w(page, 'textarea').blur();
  await shotWidget(page, 'identity.png');

  await w(page, 'button[type=submit]').click();
  await w(page, '.st.ok').waitFor();
  await shotWidget(page, 'success.png');

  console.log(`screenshots written to ${out}`);
} finally {
  await browser.close();
  server.kill();
}
