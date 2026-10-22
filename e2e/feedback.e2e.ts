import { expect, test, type Page } from '@playwright/test';
import { API, WEB, WEB_PORT, artisan, artisanJson, keys } from './env';

interface ListItem {
  id: string;
  message: string;
  completed_at: string | null;
}

interface Entry extends Record<string, unknown> {
  id: string;
  message: string;
  email: string;
  origin: string;
  completed_at: string | null;
  console_log: { type: string; message: string }[] | null;
  screenshot: {
    mime: string;
    width: number;
    height: number;
    bytes: number;
    sha256: string;
  } | null;
}

const shadow = (page: Page, selector: string) => page.locator('codebar-feedback').locator(selector);

async function open(page: Page, key: string, origin = WEB): Promise<void> {
  await page.goto(`${origin}/index.html?key=${key}&api=${encodeURIComponent(API)}`);
  await expect(page.locator('html')).toHaveAttribute('data-feedback-ready', '1');
  await shadow(page, 'button.l').click();
}

async function write(page: Page, message: string): Promise<void> {
  await shadow(page, 'input[type=email]').fill('e2e@beispiel.ch');
  await shadow(page, 'textarea').fill(message);
}

const entries = (): ListItem[] => artisanJson<ListItem[]>('feedback:list', '--limit=100');

test('feedback with screenshot, console error and browser data lands in the database', async ({ page, browserName }) => {
  const message = `Vollstack ${browserName} ${Date.now()}`;
  await open(page, keys().open);
  await expect.poll(() => shadow(page, '.hd').evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(10, 124, 102)');
  await write(page, message);
  await shadow(page, '.tr button').first().click();
  await expect(shadow(page, '.tb')).toBeVisible();
  const box = (await page.locator('main').boundingBox())!;
  await page.mouse.move(box.x + 60, box.y + 80);
  await page.mouse.down();
  await page.mouse.move(box.x + 260, box.y + 200, { steps: 6 });
  await page.mouse.up();
  await shadow(page, '.tb [data-action=done]').click();
  await shadow(page, 'button[type=submit]').click();
  await expect(shadow(page, '.st.ok')).toBeVisible();

  const item = entries().find((e) => e.message === message);
  expect(item, 'entry in feedback:list').toBeTruthy();
  const entry = artisanJson<Entry>('feedback:show', item!.id);

  expect(entry.email).toBe('e2e@beispiel.ch');
  expect(entry.origin).toBe(WEB);
  expect(entry.completed_at).toBeNull();
  expect(entry.locale).toBe('de_CH');
  expect(entry.page_url).toContain('/index.html');
  expect(entry.widget_version).toBe('1.0.0');
  expect(entry.viewport_w).toBe(page.viewportSize()!.width);
  expect(entry.browser_name).toBeTruthy();
  expect(entry.os_name).toBeTruthy();
  expect(entry.user_agent).toContain('Mozilla/5.0');
  expect(entry.annotations).toHaveLength(1);
  expect(entry.console_log!.some((l) => l.message === 'E2E console error with token=[redacted]')).toBe(true);
  expect(JSON.stringify(entry)).not.toContain('supersecret123');
  expect(entry.screenshot).toMatchObject({ mime: 'image/jpeg' });
  expect(entry.screenshot!.width).toBeGreaterThan(500);
  expect(entry.screenshot!.bytes).toBeGreaterThan(1000);
  expect(entry.screenshot!.sha256).toMatch(/^[0-9a-f]{64}$/);

  artisan('feedback:complete', entry.id);
  expect(artisanJson<Entry>('feedback:show', entry.id).completed_at).not.toBeNull();
});

test('a foreign origin is rejected with 403 and the error page', async ({ page, request }) => {
  const before = entries().length;
  await open(page, keys().open, `http://127.0.0.1:${WEB_PORT}`);
  await write(page, 'Fremde Origin');
  await shadow(page, 'button[type=submit]').click();
  await expect(shadow(page, '.st.err')).toBeVisible({ timeout: 15_000 });
  await expect(shadow(page, '.st.err')).toContainText('Senden fehlgeschlagen');
  expect(entries()).toHaveLength(before);

  const preflight = await request.fetch(`${API}/v1/projects/${keys().open}/feedback`, {
    method: 'OPTIONS',
    headers: {
      Origin: `http://127.0.0.1:${WEB_PORT}`,
      'Access-Control-Request-Method': 'POST',
    },
  });
  expect(preflight.status()).toBe(403);
  expect(await preflight.json()).toEqual({
    error: { code: 'origin_not_allowed' },
  });

  const forged = await request.post(`${API}/v1/projects/${keys().open}/feedback`, {
    headers: {
      Origin: 'https://evil.example',
      'Idempotency-Key': crypto.randomUUID(),
    },
    multipart: { message: 'x' },
  });
  expect(forged.status()).toBe(403);
});

test('an IP outside the allowed range is rejected with 403', async ({ page }) => {
  const before = entries().length;
  await open(page, keys().ipRestricted);
  await write(page, 'IP gesperrt');
  await shadow(page, 'button[type=submit]').click();
  await expect(shadow(page, '.st.err')).toBeVisible();
  expect(await page.evaluate(() => (window as unknown as { feedbackErrors: unknown[] }).feedbackErrors)).toEqual([
    { code: 'origin_not_allowed', status: 403 },
  ]);
  expect(entries()).toHaveLength(before);
});

test('the honeypot is accepted silently without storing', async ({ page }) => {
  const before = entries().length;
  await open(page, keys().open);
  await write(page, 'Honeypot');
  await shadow(page, 'input[name=website]').evaluate((el: HTMLInputElement) => (el.value = 'https://spam.example'));
  await shadow(page, 'button[type=submit]').click();
  await expect(shadow(page, '.st.ok')).toBeVisible();
  expect(entries()).toHaveLength(before);
});

test('a repeated request with the same Idempotency-Key creates no duplicate', async ({ request }) => {
  const key = crypto.randomUUID();
  const message = `Idempotenz ${Date.now()}`;
  const multipart = {
    message,
    email: 'e2e@beispiel.ch',
    locale: 'de_CH',
    page_url: `${WEB}/index.html`,
    client: JSON.stringify({ viewport: { w: 800, h: 600 } }),
    widget_version: '1.0.0',
    elapsed_ms: '5000',
  };
  const headers = { Origin: WEB, 'Idempotency-Key': key };
  const first = await request.post(`${API}/v1/projects/${keys().open}/feedback`, { headers, multipart });
  const second = await request.post(`${API}/v1/projects/${keys().open}/feedback`, { headers, multipart });
  expect(first.status()).toBe(201);
  expect(second.status()).toBe(200);
  expect((await second.json()).id).toBe((await first.json()).id);
  expect(entries().filter((e) => e.message === message)).toHaveLength(1);
});

test('the share link on the thank you screen and the team link open the feedback page', async ({ page, context, browserName }) => {
  const message = `Link ${browserName} ${Date.now()}`;
  await open(page, keys().open);
  await write(page, message);
  await shadow(page, '.tr button').first().click();
  await expect(shadow(page, '.tb')).toBeVisible();
  await shadow(page, '.tb [data-action=done]').click();
  await shadow(page, 'button[type=submit]').click();
  await expect(shadow(page, '.st.ok')).toBeVisible();

  // Sender: read-only link with copy button.
  const senderUrl = await shadow(page, '.st.ok .sh input').inputValue();
  expect(senderUrl).toMatch(new RegExp(`^${API}/v1/feedback/[0-9a-z]{26}\\?expires=\\d+&signature=[0-9a-f]{64}$`));
  await shadow(page, '.st.ok button.cp').click();
  await expect(shadow(page, '.st.ok button.cp')).toHaveText('Kopiert');

  const viewer = await context.newPage();
  await viewer.goto(senderUrl);
  await expect(viewer.locator('h1')).toHaveText('E2E Website');
  await expect(viewer.locator('.message')).toHaveText(message);
  await expect(viewer.locator('.status')).toHaveText('open');
  await expect(viewer.locator('form.state')).toHaveCount(0);
  const img = viewer.locator('.shot img');
  await expect(img).toBeVisible();
  expect(await img.evaluate((el: HTMLImageElement) => el.complete && el.naturalWidth > 500)).toBe(true);
  const [download] = await Promise.all([viewer.waitForEvent('download'), viewer.getByText(/^Download/).click()]);
  expect(download.suggestedFilename()).toMatch(/^feedback-[0-9a-z]{26}\.jpg$/);

  // Team: feedback:link can complete and reopen.
  const id = senderUrl.split('/v1/feedback/')[1]!.split('?')[0]!;
  const team = artisanJson<{ url: string; manage: boolean }>('feedback:link', id, '--by=E2E Team');
  expect(team.manage).toBe(true);
  await viewer.goto(team.url);
  await viewer.getByRole('button', { name: 'Mark as completed' }).click();
  await expect(viewer.locator('.status')).toContainText('completed');
  expect(artisanJson<Entry>('feedback:show', id).completed_at).not.toBeNull();
  await viewer.getByRole('button', { name: 'Reopen' }).click();
  await expect(viewer.locator('.status')).toHaveText('open');
  expect(artisanJson<Entry>('feedback:show', id).completed_at).toBeNull();

  // Tampered links reveal nothing.
  const forged = await viewer.goto(senderUrl.replace(/signature=./, 'signature=0'));
  expect(forged!.status()).toBe(403);
  expect(await forged!.text()).toBe('');
});
