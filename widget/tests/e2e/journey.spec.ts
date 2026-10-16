import { expect, test } from '@playwright/test';
import { drag, expectState, fill, requests, sid, takeShot, visit, w } from './helpers';

// all steps from the launcher to the confirmation.
test('journey: launcher → form → screenshot → mark → send → thanks', async ({ page }, info) => {
  const id = sid(info);
  await visit(page, id);
  const x = w(page);

  await expect(x.launcher).toBeVisible();
  await expect(x.panel).toBeHidden();
  await x.launcher.click();
  await expect(x.panel).toBeVisible();
  await expect(x.launcher).toHaveAttribute('aria-expanded', 'true');

  await fill(page, 'anna@beispiel.ch', 'Die Schaltfläche «Speichern» reagiert nicht.');
  await takeShot(page);
  await expect(x.panel).toBeHidden();
  await expect(x.toolbar).toBeVisible();
  await drag(page, [200, 200], [420, 320]);
  await x.done.click();
  await expectState(page, 'form');
  await expect(x.thumb).toBeVisible();

  await x.submit.click();
  await expect(x.success).toBeVisible();
  await expect(x.success).toContainText('Danke für dein Feedback!');

  const [req] = await requests(page, id);
  expect(req!.fields.message).toBe('Die Schaltfläche «Speichern» reagiert nicht.');
  expect(req!.screenshot?.type).toBe('image/jpeg');
  expect(JSON.parse(req!.fields.annotations!)).toHaveLength(1);

  await expect(x.panel).toBeHidden({ timeout: 5000 });
  await expect(x.launcher).toBeFocused();
});

test('script tag variant (IIFE) loads the screenshot chunk relative to the script', async ({ page }, info) => {
  const id = sid(info);
  await visit(page, id, {}, '/fixtures/iife.html');
  const x = w(page);
  await x.launcher.click();
  await takeShot(page);
  await x.done.click();
  await expect(x.thumb).toBeVisible();
  expect(await page.evaluate(() => typeof (window as unknown as { CodebarFeedback: { init: unknown } }).CodebarFeedback.init)).toBe(
    'function',
  );
});
