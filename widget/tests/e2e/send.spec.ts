import { expect, test } from '@playwright/test';
import { expectState, fill, mock, requests, sid, takeShot, visit, w } from './helpers';

test('payload matches the API contract', async ({ page }, info) => {
  const id = sid(info);
  await visit(page, id, { identity: '1' });
  const x = w(page);
  await page.evaluate(() => {
    console.error('Uncaught TypeError at app.js token=abc123');
    void fetch('/s/none/v1/missing?secret=1');
  });
  await page.waitForTimeout(200);
  await x.launcher.click();
  await fill(page, null, 'Payload prüfen');
  await takeShot(page);
  await x.done.click();
  await x.submit.click();
  await expect(x.success).toBeVisible();

  const [req] = await requests(page, id);
  const f = req!.fields;
  expect(req!.idempotencyKey).toMatch(/^[0-9a-f-]{36}$/);
  expect(f.message).toBe('Payload prüfen');
  expect(f.email).toBe('anna.muster@beispiel.ch');
  expect(f.name).toBe('Anna Muster');
  expect(JSON.parse(f.identity!)).toEqual({ id: '4711', name: 'Anna Muster', email: 'anna.muster@beispiel.ch' });
  expect(f.locale).toBe('de_CH');
  expect(f.page_url).toContain('/fixtures/index.html');
  expect(f.page_title).toBe('Beispiel Startseite');
  expect(f.submitted_from_url).toContain('/fixtures/index.html');
  expect(f.widget_version).toBe('1.0.0');
  expect(Number(f.elapsed_ms)).toBeGreaterThan(0);
  expect(f.website).toBe('');
  const client = JSON.parse(f.client!);
  expect(client).toMatchObject({ viewport: { w: page.viewportSize()!.width }, timezone: expect.any(String), language: expect.any(String) });
  const consoleLog = JSON.parse(f.console_log!);
  expect(consoleLog[0]).toMatchObject({ type: 'error', message: 'Uncaught TypeError at app.js token=[redacted]' });
  const network = JSON.parse(f.network_log!);
  expect(network[0]).toMatchObject({ method: 'GET', status: 404 });
  expect(network[0].url).not.toContain('secret');
  expect(req!.screenshot).toMatchObject({ type: 'image/jpeg', name: 'screenshot.jpg' });
});

test('page_url is the capture page, submitted_from_url the current page', async ({ page }, info) => {
  const id = sid(info);
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Seitenwechsel');
  await takeShot(page);
  await x.done.click();
  await page.locator('#nav-spa').click();
  await x.submit.click();
  await expect(x.success).toBeVisible();
  const [req] = await requests(page, id);
  expect(req!.fields.page_url).toContain('/fixtures/index.html');
  expect(req!.fields.submitted_from_url).toContain('/fixtures/spa.html');
});

test('retries once on a network error with the same key, no duplicate', async ({ page }, info) => {
  const id = sid(info);
  await mock(page, id, { responses: [{ status: 201, drop: true }] });
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Retry');
  await x.submit.click();
  await expect(x.success).toBeVisible({ timeout: 10_000 });
  const reqs = await requests(page, id);
  expect(reqs).toHaveLength(2);
  expect(reqs[0]!.idempotencyKey).toBe(reqs[1]!.idempotencyKey);
});

test('a repeated send after an error reuses the Idempotency-Key', async ({ page }, info) => {
  const id = sid(info);
  await mock(page, id, { responses: [{ status: 503 }, { status: 503 }, { status: 201 }] });
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Zweimal');
  await x.submit.click();
  await expect(x.failure).toBeVisible({ timeout: 10_000 });
  await x.failure.locator('button').click();
  await x.submit.click();
  await expect(x.success).toBeVisible();
  const keys = new Set((await requests(page, id)).map((r) => r.idempotencyKey));
  expect(keys.size).toBe(1);
});

test('422 goes back to the form with field errors', async ({ page }, info) => {
  const id = sid(info);
  await mock(page, id, {
    responses: [
      {
        status: 422,
        body: { error: { code: 'validation_failed', fields: { email: ['The email field must be a valid email address.'] } } },
      },
    ],
  });
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.c', 'Server sagt nein');
  await x.submit.click();
  await expectState(page, 'form');
  await expect(x.errors.first()).toHaveText('Bitte gib eine gültige E-Mail ein.');
  await expect(x.email).toBeFocused();
});

for (const [status, code] of [
  [403, 'origin_not_allowed'],
  [404, 'project_not_found'],
  [429, 'rate_limited'],
  [500, 'server_error'],
] as const) {
  test(`${status} shows the error page and emits ${code}`, async ({ page }, info) => {
    const id = sid(info);
    await mock(page, id, {
      responses: [
        { status, body: status === 500 ? {} : { error: { code } }, headers: status === 429 ? { 'Retry-After': '30' } : {} },
        { status, body: {} },
      ],
    });
    await visit(page, id);
    const x = w(page);
    const event = page.evaluate(
      () =>
        new Promise((resolve) =>
          (window as unknown as { feedback: { on(n: string, f: (e: unknown) => void): void } }).feedback.on('error', resolve),
        ),
    );
    await x.launcher.click();
    await fill(page, 'a@b.ch', `Status ${status}`);
    await x.submit.click();
    await expect(x.failure).toBeVisible({ timeout: 10_000 });
    expect(await event).toEqual({ code, status, ...(status === 429 ? { retryAfter: 30 } : {}) });
    expect((await requests(page, id)).length).toBe(status === 500 ? 2 : 1);
  });
}
