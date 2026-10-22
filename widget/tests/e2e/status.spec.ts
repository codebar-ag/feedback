import { expect, test } from '@playwright/test';
import { box, fill, mock, settle, sid, visit, w } from './helpers';

// own pages, same height as the form, one button.
test('success page has the form height, is centered and closes after 3 s', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await settle(page);
  await fill(page, 'a@b.ch', 'Danke');
  const formHeight = (await box(x.form)).height;
  const panelHeight = (await box(x.panel)).height;
  await x.submit.click();
  await expect(x.success).toBeVisible();
  expect((await box(x.success)).height).toBeCloseTo(formHeight, 0);
  expect((await box(x.panel)).height).toBeCloseTo(panelHeight, 0);
  await expect(x.success.locator('h2')).toHaveText('Danke für dein Feedback!');
  await expect(x.success.locator('p')).toHaveText('Wir haben deine Nachricht erhalten.');
  await expect(x.success.locator('button.pl')).toHaveText('Schliessen');
  await expect(x.success.locator('h2')).toHaveCSS('font-size', '18px');
  const content = await box(x.success.locator('.ic'));
  const page_ = await box(x.success);
  expect(content.x + content.width / 2).toBeCloseTo(page_.x + page_.width / 2, 0);
  await page.waitForTimeout(2500);
  await expect(x.panel).toBeVisible();
  await expect(x.panel).toBeHidden({ timeout: 1500 });
});

test('error page: same height, only "Zurück zum Formular", text is kept', async ({ page }, info) => {
  const id = sid(info);
  await mock(page, id, { responses: [{ status: 500 }, { status: 500 }] });
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await settle(page);
  await fill(page, 'a@b.ch', 'Bleibt erhalten');
  const formHeight = (await box(x.form)).height;
  await x.submit.click();
  await expect(x.failure).toBeVisible({ timeout: 10_000 });
  expect((await box(x.failure)).height).toBeCloseTo(formHeight, 0);
  await expect(x.failure.locator('h2')).toHaveText('Senden fehlgeschlagen');
  await expect(x.failure.locator('p')).toHaveText('Dein Feedback wurde nicht gesendet.');
  await expect(x.failure.locator('button')).toHaveCount(1);
  await expect(x.failure.locator('button')).toHaveText('Zurück zum Formular');
  await expect(x.failure.locator('button')).toBeFocused();
  await x.failure.locator('button').click();
  await expect(x.form).toBeVisible();
  await expect(x.message).toHaveValue('Bleibt erhalten');
  await x.submit.click();
  await expect(x.success).toBeVisible();
});

test('success closes with the button and empties the form', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Fertig');
  await x.submit.click();
  await x.success.locator('button.pl').click();
  await expect(x.panel).toBeHidden();
  await x.launcher.click();
  await expect(x.message).toHaveValue('');
  await expect(x.email).toHaveValue('');
});

const LINK = 'https://feedback.example/v1/feedback/01J?expires=1893456000&signature=abc123';

test('success shows the share link with a copy button and stays open', async ({ page, context, browserName }, info) => {
  const id = sid(info);
  await mock(page, id, { responses: [{ status: 201, body: { id: '01J', status: 'received', url: LINK } }] });
  if (browserName === 'chromium') await context.grantPermissions(['clipboard-read', 'clipboard-write']);
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await settle(page);
  await fill(page, 'a@b.ch', 'Mit Link');
  await x.submit.click();
  await expect(x.success).toBeVisible();
  const share = x.success.locator('.sh');
  await expect(share).toBeVisible();
  await expect(share.locator('label')).toHaveText('Link zu deinem Feedback');
  await expect(share.locator('input')).toHaveValue(LINK);
  await expect(share.locator('input')).toHaveAttribute('readonly', '');
  await expect(share.locator('button.cp')).toHaveText('Kopieren');
  await share.locator('button.cp').click();
  await expect(share.locator('button.cp')).toHaveText('Kopiert');
  if (browserName === 'chromium') expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(LINK);
  await expect(share.locator('button.cp')).toHaveText('Kopieren', { timeout: 4000 });
  // No auto close while a link is shown.
  await page.waitForTimeout(3500);
  await expect(x.panel).toBeVisible();
  await x.success.locator('button.pl').click();
  await expect(x.panel).toBeHidden();
});

test('success without or with an unsafe link shows no share field', async ({ page }, info) => {
  const id = sid(info);
  await mock(page, id, { responses: [{ status: 201, body: { id: '01J', status: 'received', url: 'javascript:alert(1)' } }] });
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await settle(page);
  await fill(page, 'a@b.ch', 'Ohne Link');
  await x.submit.click();
  await expect(x.success).toBeVisible();
  await expect(x.success.locator('.sh')).toBeHidden();
  await expect(x.panel).toBeHidden({ timeout: 5000 });
});
