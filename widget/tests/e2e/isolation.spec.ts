import { expect, test } from '@playwright/test';
import { sid, style, visit, w } from './helpers';

test('host page CSS does not affect the widget and vice versa', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  // fixtures/page.css styles buttons red 40px, inputs yellow and .l/.p/.s with an outline.
  expect(await style(x.submit, 'font-size')).toBe('15px');
  expect(await style(x.submit, 'color')).not.toBe('rgb(255, 0, 0)');
  expect(await style(x.message, 'background-color')).toBe('rgb(255, 255, 255)');
  expect(await style(x.launcher, 'outline-style')).toBe('none');
  // The page element with class "l" keeps its own styles.
  const hostButton = page.locator('#host-button');
  expect(await style(hostButton, 'position')).toBe('static');
  expect(await style(hostButton, 'font-size')).toBe('40px');
  expect(await page.locator('style').count()).toBe(0);
});

test('destroy removes the widget and the console hook', async ({ page }, info) => {
  await visit(page, sid(info));
  const result = await page.evaluate(() => {
    const wrapped = console.error;
    (window as unknown as { feedback: { destroy(): void } }).feedback.destroy();
    return {
      same: console.error === wrapped,
      native: console.error.toString().includes('[native code]'),
      host: !!document.querySelector('codebar-feedback'),
    };
  });
  expect(result).toEqual({ same: false, native: true, host: false });
});

test('a failing host handler does not break the widget', async ({ page }, info) => {
  await visit(page, sid(info));
  await page.evaluate(() =>
    (window as unknown as { feedback: { on(n: string, f: () => void): void } }).feedback.on('open', () => {
      throw new Error('host bug');
    }),
  );
  await w(page).launcher.click();
  await expect(w(page).panel).toBeVisible();
});
