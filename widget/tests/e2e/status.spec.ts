import { expect, test } from '@playwright/test';
import { box, fill, mock, settle, sid, visit, w } from './helpers';

// Board 7: own pages, same height as the form, one button.
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
  await expect(x.success.locator('button')).toHaveText('Schliessen');
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
  await x.success.locator('button').click();
  await expect(x.panel).toBeHidden();
  await x.launcher.click();
  await expect(x.message).toHaveValue('');
  await expect(x.email).toHaveValue('');
});
