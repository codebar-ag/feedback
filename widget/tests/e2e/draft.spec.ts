import { expect, test } from '@playwright/test';
import { drag, draft, expectState, fill, marks, sid, takeShot, visit, w } from './helpers';

test('text and email survive close/open and reload, panel reopens', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'anna@beispiel.ch', 'Entwurf bleibt');
  await x.close.click();
  await x.launcher.click();
  await expect(x.message).toHaveValue('Entwurf bleibt');

  await page.reload();
  await expect(x.panel).toBeVisible();
  await expect(x.email).toHaveValue('anna@beispiel.ch');
  await expect(x.message).toHaveValue('Entwurf bleibt');
  await expect(x.message).not.toBeFocused();
  expect(await page.evaluate(() => scrollY)).toBe(0);
});

test('closed state is restored as closed', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Geschlossen');
  await x.close.click();
  await page.reload();
  await expect(x.panel).toBeHidden();
  await x.launcher.click();
  await expect(x.message).toHaveValue('Geschlossen');
});

test('draft survives navigation to a second page (multi page)', async ({ page }, info) => {
  const id = sid(info);
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Über Seiten hinweg');
  await page.locator('#nav-second').click();
  await expect(page).toHaveTitle('Beispiel Zweite Seite');
  await expect(page.locator('html')).toHaveAttribute('data-feedback-ready', '1');
  await expect(x.panel).toBeVisible();
  await expect(x.message).toHaveValue('Über Seiten hinweg');
});

test('draft survives SPA navigation', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'SPA bleibt');
  await page.locator('#nav-spa').click();
  await expect(page).toHaveURL(/spa\.html/);
  await expect(x.panel).toBeVisible();
  await expect(x.message).toHaveValue('SPA bleibt');
});

test('screenshot and marks survive a reload and stay editable', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await takeShot(page);
  await drag(page, [100, 150], [300, 260]);
  await x.done.click();
  await expect.poll(async () => (await draft(page))?.shot).toBeTruthy();

  await page.reload();
  await expect(x.thumb).toBeVisible();
  await x.edit.click();
  await expectState(page, 'annotating');
  expect(await marks(page)).toEqual([{ type: 'rect', x: 100, y: 150, w: 200, h: 110 }]);
  await x.undo.click();
  expect(await marks(page)).toEqual([]);
});

test('draft is empty after sending', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Wird gesendet');
  await takeShot(page);
  await x.done.click();
  await x.submit.click();
  await expect(x.success).toBeVisible();
  expect(await draft(page)).toBeNull();
  await page.reload();
  await x.launcher.click();
  await expect(x.message).toHaveValue('');
  await expect(x.thumb).toBeHidden();
});

test('an expired draft (TTL 24 h) is discarded', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Alt');
  // Age the stored draft right before the widget loads again.
  await page.addInitScript(() => {
    const key = Object.keys(sessionStorage).find((k) => k.endsWith(':draft'));
    if (!key) return;
    const d = JSON.parse(sessionStorage.getItem(key)!);
    d.savedAt = Date.now() - 25 * 3600 * 1000;
    sessionStorage.setItem(key, JSON.stringify(d));
  });
  await page.reload();
  await expect(page.locator('html')).toHaveAttribute('data-feedback-ready', '1');
  await expect(x.panel).toBeHidden();
  await x.launcher.click();
  await expect(x.message).toHaveValue('');
});

test('works without storage (private mode simulation)', async ({ page }, info) => {
  await page.addInitScript(() => {
    const fail = () => {
      throw new DOMException('denied', 'SecurityError');
    };
    Storage.prototype.getItem = fail;
    Storage.prototype.setItem = fail;
    Storage.prototype.removeItem = fail;
    IDBFactory.prototype.open = fail as never;
  });
  const id = sid(info);
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await fill(page, 'a@b.ch', 'Ohne Speicher');
  await takeShot(page);
  await x.done.click();
  await x.submit.click();
  await expect(x.success).toBeVisible();
});
