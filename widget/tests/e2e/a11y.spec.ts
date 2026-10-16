import { expect, test } from '@playwright/test';
import { axe, expectState, fill, mock, settle, sid, takeShot, visit, w } from './helpers';

test('axe: no violations in any state', async ({ page }, info) => {
  const id = sid(info);
  await mock(page, id, { responses: [{ status: 500 }, { status: 500 }] });
  await visit(page, id);
  const x = w(page);
  await axe(page);
  await x.launcher.click();
  await settle(page);
  await axe(page);
  await x.submit.click();
  await axe(page);
  await fill(page, 'a@b.ch', 'Barrierefrei');
  await takeShot(page);
  await axe(page);
  await x.done.click();
  await axe(page);
  await x.submit.click();
  await expect(x.failure).toBeVisible({ timeout: 10_000 });
  await axe(page);
  await x.failure.locator('button').click();
  await mock(page, id, { responses: [{ status: 201 }] });
  await x.submit.click();
  await expect(x.success).toBeVisible();
  await axe(page);
});

test('axe with identity', async ({ page }, info) => {
  await visit(page, sid(info), { identity: '1' });
  await w(page).launcher.click();
  await settle(page);
  await axe(page);
});

test('tab order and focus trap', async ({ page, browserName }, info) => {
  test.skip(browserName === 'webkit', 'Safari does not tab to buttons by default');
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.focus();
  await page.keyboard.press('Enter');
  await expect(x.message).toBeFocused();
  await x.close.focus();
  const order: string[] = [];
  for (let i = 0; i < 6; i++) {
    await page.keyboard.press('Tab');
    order.push(
      await x.host.evaluate((h) => {
        const a = h.shadowRoot!.activeElement as HTMLElement | null;
        return a ? (a.getAttribute('aria-label') ?? a.getAttribute('name') ?? a.textContent ?? a.tagName) : 'outside';
      }),
    );
  }
  expect(order).toEqual(['email', 'message', 'Screenshot hinzufügen', 'Senden', 'Schliessen', 'email']);
  await page.keyboard.press('Shift+Tab');
  await expect(x.close).toBeFocused();
});

test('dialog semantics and live region', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await expect(x.panel).toHaveAttribute('role', 'dialog');
  await expect(x.panel).toHaveAttribute('aria-label', 'Feedback geben');
  await expect(x.message).toHaveAttribute('aria-required', 'true');
  await expect(x.host.locator('[aria-live=polite]')).toHaveCount(1);
  await fill(page, 'a@b.ch', 'Live');
  await x.camera.click();
  await expectState(page, 'annotating');
  await expect(x.host.locator('[aria-live=polite]')).toHaveText('Screenshot wird erstellt …');
});

test('prefers-reduced-motion disables animations', async ({ page }, info) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  expect(await x.panel.evaluate((el) => getComputedStyle(el).animationName)).toBe('none');
});

test('visible focus ring (3 px, focus color)', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await x.message.focus();
  expect(await x.message.evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('3px');
  expect(await x.message.evaluate((el) => getComputedStyle(el).outlineColor)).toBe('rgb(29, 78, 216)');
});
