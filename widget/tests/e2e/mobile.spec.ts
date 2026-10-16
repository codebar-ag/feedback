import { expect, test } from '@playwright/test';
import { box, expectState, fill, marks, settle, sid, visit, w } from './helpers';

// bottom sheet, touch, status pages on mobile.
test('bottom sheet with 12 px margins, launcher hidden while open', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.tap();
  await settle(page);
  const vp = page.viewportSize()!;
  const panel = await box(x.panel);
  expect(panel.x).toBeCloseTo(12, 0);
  expect(vp.width - (panel.x + panel.width)).toBeCloseTo(12, 0);
  expect(vp.height - (panel.y + panel.height)).toBeCloseTo(12, 0);
  await expect(x.launcher).toBeHidden();
  await x.close.tap();
  await expect(x.launcher).toBeVisible();
});

test('touch targets are at least 44 px', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  expect((await box(x.launcher)).height).toBeGreaterThanOrEqual(43.9);
  await x.launcher.tap();
  for (const el of [x.camera, x.submit, x.email]) expect((await box(el)).height).toBeGreaterThanOrEqual(43.9);
  await fill(page, 'a@b.ch', 'Mobil');
  await x.camera.tap();
  await expectState(page, 'annotating');
  await expect(x.panel).toBeHidden();
  await expect(x.launcher).toBeHidden();
  for (const b of await x.toolbar.locator('button').all()) expect((await box(b)).height).toBeGreaterThanOrEqual(43.9);
});

test('marks with touch (pointer events)', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.tap();
  await x.camera.tap();
  await expectState(page, 'annotating');
  await x.canvas.evaluate((canvas) => {
    const fire = (type: string, x: number, y: number) =>
      canvas.dispatchEvent(
        new PointerEvent(type, { pointerId: 7, pointerType: 'touch', isPrimary: true, clientX: x, clientY: y, bubbles: true, button: 0 }),
      );
    fire('pointerdown', 40, 200);
    for (let i = 1; i <= 6; i++) fire('pointermove', 40 + i * 30, 200 + i * 20);
    fire('pointerup', 220, 320);
  });
  expect(await marks(page)).toEqual([{ type: 'rect', x: 40, y: 200, w: 180, h: 120 }]);
  await x.done.tap();
  await expect(x.thumb).toBeVisible();
});

test('the keyboard does not cover the field (visualViewport)', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.tap();
  await settle(page);
  const before = await box(x.panel);
  await page.evaluate(() => {
    const vv = window.visualViewport!;
    Object.defineProperty(vv, 'height', { configurable: true, get: () => window.innerHeight - 300 });
    vv.dispatchEvent(new Event('resize'));
  });
  await expect.poll(async () => before.y + before.height - ((await box(x.panel)).y + (await box(x.panel)).height)).toBeCloseTo(300, 0);
});

test('success and error pages on mobile', async ({ page }, info) => {
  const id = sid(info);
  await page.request.post(`/s/${id}/__mock`, { data: { responses: [{ status: 500 }, { status: 500 }] } });
  await visit(page, id);
  const x = w(page);
  await x.launcher.tap();
  await fill(page, 'a@b.ch', 'Mobil senden');
  await x.submit.tap();
  await expect(x.failure).toBeVisible({ timeout: 10_000 });
  await x.failure.locator('button').tap();
  await x.submit.tap();
  await expect(x.success).toBeVisible();
});
