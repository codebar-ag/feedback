import { expect, test } from '@playwright/test';
import { box, settle, sid, visit, w } from './helpers';

test.beforeEach(async ({ page }, info) => {
  await visit(page, sid(info));
});

test('launcher opens, X and Escape close, focus returns to the launcher', async ({ page }) => {
  const x = w(page);
  await x.launcher.click();
  await expect(x.panel).toBeVisible();
  await x.close.click();
  await expect(x.panel).toBeHidden();
  await expect(x.launcher).toBeFocused();

  await x.launcher.click();
  await x.message.click();
  await page.keyboard.press('Escape');
  await expect(x.panel).toBeHidden();
  await expect(x.launcher).toBeFocused();
});

test('launcher shows sparkle when closed and a cross when open', async ({ page }) => {
  const x = w(page);
  const icon = () => x.launcher.locator('svg path').first().getAttribute('d');
  const sparkle = await icon();
  expect(sparkle).toContain('M11 3l1.9');
  await expect(x.launcher).toHaveAttribute('title', 'Feedback geben');
  await x.launcher.click();
  expect(await icon()).toBe('M6 6l12 12M18 6L6 18');
  await expect(x.launcher).toHaveAttribute('aria-label', 'Schliessen');
});

test('header: icon left, X right, no title, no minimize', async ({ page }) => {
  const x = w(page);
  await x.launcher.click();
  await expect(x.header.locator('button')).toHaveCount(1);
  await expect(x.header).toHaveText('');
  const icon = await box(x.header.locator(':scope > svg'));
  const close = await box(x.close);
  const header = await box(x.header);
  expect(icon.x - header.x).toBeCloseTo(20, 0);
  expect(icon.width).toBeCloseTo(24, 0);
  expect(close.width).toBeCloseTo(34, 0);
  expect(header.x + header.width - (close.x + close.width)).toBeCloseTo(12, 0);
  await expect(x.panel.locator('h1, h2:visible, [class*=title]')).toHaveCount(0);
});

// position, size, no overlap with the launcher.
test('panel geometry: 380 wide, right 20, above the launcher', async ({ page }) => {
  const x = w(page);
  await x.launcher.click();
  await page.mouse.move(0, 0);
  await settle(page);
  const vp = page.viewportSize()!;
  const panel = await box(x.panel);
  const launcher = await box(x.launcher);
  expect(launcher.width).toBe(56);
  expect(launcher.height).toBe(56);
  expect(vp.width - (launcher.x + launcher.width)).toBeCloseTo(20, 0);
  expect(vp.height - (launcher.y + launcher.height)).toBeCloseTo(20, 0);
  expect(panel.width).toBe(380);
  expect(vp.width - (panel.x + panel.width)).toBeCloseTo(20, 0);
  expect(vp.height - (panel.y + panel.height)).toBeCloseTo(92, 0);
  expect(panel.y + panel.height).toBeLessThanOrEqual(launcher.y);
  expect(panel.height).toBeLessThanOrEqual(vp.height - 112);
});

test('open() and close() from the host page', async ({ page }) => {
  const x = w(page);
  await page.evaluate(() => (window as unknown as { feedback: { open(): void } }).feedback.open());
  await expect(x.panel).toBeVisible();
  expect(await page.evaluate(() => (window as unknown as { feedback: { isOpen(): boolean } }).feedback.isOpen())).toBe(true);
  await page.evaluate(() => (window as unknown as { feedback: { close(): void } }).feedback.close());
  await expect(x.panel).toBeHidden();
});
