import { expect, test } from '@playwright/test';
import { box, drag, draft, expectState, fill, marks, requests, settle, sid, takeShot, visit, w } from './helpers';

test.beforeEach(async ({ page }, info) => {
  await visit(page, sid(info));
  await w(page).launcher.click();
});

test('never takes a screenshot automatically; tools row has only the camera', async ({ page }) => {
  const x = w(page);
  await expect(x.tools.locator('button:visible')).toHaveCount(1);
  await expect(x.camera).toHaveAttribute('aria-label', 'Screenshot hinzufügen');
  await expect(x.thumb).toBeHidden();
  await expect(x.form).toContainText('Tools');
  await page.waitForTimeout(800);
  await expectState(page, 'form');
  await expect(x.thumb).toBeHidden();
  expect((await draft(page))?.shot ?? null).toBeNull();
});

test('camera hides the panel, shows the annotation mode, Done brings it back', async ({ page }) => {
  const x = w(page);
  await x.camera.click();
  await expect(x.panel).toBeHidden();
  await expect(x.launcher).toBeHidden();
  await expectState(page, 'annotating');
  await expect(x.toolbar).toBeVisible();
  await expect(x.tool('rect')).toHaveAttribute('aria-pressed', 'true');
  const tb = await box(x.toolbar);
  expect(tb.y).toBeCloseTo(20, 0);
  expect(tb.x + tb.width / 2).toBeCloseTo(page.viewportSize()!.width / 2, 0);
  await x.done.click();
  await expect(x.panel).toBeVisible();
  await expect(x.thumb).toBeVisible();
  await expect(x.edit).toHaveAttribute('aria-label', 'Bearbeiten');
  await expect(x.remove).toHaveAttribute('aria-label', 'Screenshot entfernen');
  expect((await box(x.thumb)).width).toBeCloseTo(72, 1);
  expect((await box(x.thumb)).height).toBeCloseTo(44, 1);
  // No texts like "Ganze Seite" or "Markierungen" in the tools row.
  await expect(x.tools).toHaveText('');
  await expect(x.host).not.toContainText('Ganze Seite');
  await expect(x.host).not.toContainText('Markierungen');
});

test('the close button in the corner cancels like Escape', async ({ page }) => {
  const x = w(page);
  const close = x.host.locator('.xb');
  await takeShot(page);
  await expect(close).toBeVisible();
  await expect(close).toHaveAttribute('aria-label', 'Abbrechen');
  const b = await box(close);
  const vp = page.viewportSize()!;
  if (vp.width > 640) expect(b.y).toBeCloseTo(20, 0);
  else expect(b.y + b.height).toBeCloseTo(vp.height - 20, 0);
  expect(b.x + b.width).toBeCloseTo(vp.width - 20, 0);
  await drag(page, [100, 150], [300, 260]);
  await close.click();
  await expectState(page, 'form');
  await expect(x.panel).toBeVisible();
  // A fresh screenshot is dropped, as with Escape.
  await expect(x.thumb).toBeHidden();
  await expect(close).toBeHidden();
});

test('rectangle, marker, undo, clear all, Ctrl/Cmd+Z and minimum sizes', async ({ page }) => {
  const x = w(page);
  await takeShot(page);
  await drag(page, [100, 150], [300, 260]);
  await drag(page, [400, 400], [403, 403]);
  expect(await marks(page)).toEqual([{ type: 'rect', x: 100, y: 150, w: 200, h: 110 }]);

  await x.tool('marker').click();
  await expect(x.tool('marker')).toHaveAttribute('aria-pressed', 'true');
  await drag(page, [150, 450], [450, 470], 12);
  await page.mouse.click(600, 600);
  const m = await marks(page);
  expect(m).toHaveLength(2);
  expect((m[1] as { type: string; points: unknown[] }).type).toBe('marker');
  expect((m[1] as { points: unknown[] }).points.length).toBeGreaterThanOrEqual(3);

  await x.undo.click();
  expect(await marks(page)).toHaveLength(1);
  await x.clear.click();
  expect(await marks(page)).toHaveLength(0);
  await page.keyboard.press(process.platform === 'darwin' ? 'Meta+z' : 'Control+z');
  expect(await marks(page)).toHaveLength(1);
  await page.keyboard.press('ControlOrMeta+z');
  expect(await marks(page)).toHaveLength(0);
});

test('the mouse wheel scrolls the page in marking mode', async ({ page }) => {
  await takeShot(page);
  await page.mouse.move(400, 400);
  await page.mouse.wheel(0, 600);
  await expect.poll(() => page.evaluate(() => scrollY)).toBeGreaterThan(100);
  await expect(w(page).toolbar).toBeInViewport();
});

test('marks stick to the page while scrolling (document coordinates)', async ({ page }) => {
  await takeShot(page);
  // Instant scroll to a known position: wheel scrolling animates differently per browser.
  await page.evaluate(() => window.scrollTo({ top: 600, behavior: 'instant' }));
  await expect.poll(() => page.evaluate(() => Math.round(scrollY))).toBe(600);
  await drag(page, [100, 100], [200, 200]);
  expect(await marks(page)).toEqual([{ type: 'rect', x: 100, y: 700, w: 100, h: 100 }]);

  // Scrolling back keeps the mark at the same document position.
  await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
  await drag(page, [100, 100], [200, 200]);
  expect(await marks(page)).toEqual([
    { type: 'rect', x: 100, y: 700, w: 100, h: 100 },
    { type: 'rect', x: 100, y: 100, w: 100, h: 100 },
  ]);
  await expect(w(page).toolbar).toBeInViewport();
});

test('Escape cancels: fresh screenshot removed, existing one kept with old marks', async ({ page }) => {
  const x = w(page);
  await takeShot(page);
  await page.keyboard.press('Escape');
  await expectState(page, 'form');
  await expect(x.thumb).toBeHidden();

  await takeShot(page);
  await drag(page, [100, 150], [300, 260]);
  await x.done.click();
  await x.edit.click();
  await expectState(page, 'annotating');
  await drag(page, [300, 300], [400, 400]);
  expect(await marks(page)).toHaveLength(2);
  await page.keyboard.press('Escape');
  await expect(x.thumb).toBeVisible();
  expect(await marks(page)).toHaveLength(1);
});

test('pen re-opens the annotation mode without re-rendering, trash removes the screenshot', async ({ page }) => {
  const x = w(page);
  await takeShot(page);
  await x.done.click();
  await page.evaluate(() => {
    const w = window as unknown as { encodes: number };
    w.encodes = 0;
    const original = HTMLCanvasElement.prototype.toBlob;
    HTMLCanvasElement.prototype.toBlob = function (...args) {
      w.encodes++;
      return original.apply(this, args);
    };
  });
  await x.edit.click();
  await expectState(page, 'annotating');
  await x.done.click();
  expect(await page.evaluate(() => (window as unknown as { encodes: number }).encodes)).toBe(0);
  await x.remove.click();
  await expect(x.thumb).toBeHidden();
  await expect(x.camera).toBeFocused();
  expect((await draft(page))?.shot).toBeNull();
});

// full page and privacy in the image.
test('sends the full page with masks and burnt-in marks', async ({ page }, info) => {
  const id = sid(info);
  const x = w(page);
  await fill(page, 'a@b.ch', 'Mit Screenshot');
  await page.evaluate(() => scrollTo(0, 500));
  await takeShot(page);
  await drag(page, [100, 150], [300, 260]);
  await x.done.click();
  await x.submit.click();
  await expect(x.success).toBeVisible();

  const [req] = await requests(page, id);
  expect(req!.screenshot!.type).toBe('image/jpeg');
  const pageSize = await page.evaluate(() => ({ w: document.documentElement.scrollWidth, h: document.documentElement.scrollHeight }));
  const masked = await page.evaluate(() => {
    const r = document.getElementById('masked')!.getBoundingClientRect();
    const i = document.getElementById('ignored')!.getBoundingClientRect();
    return { x: r.left + scrollX + 10, y: r.top + scrollY + 10, ix: i.left + scrollX + 10, iy: i.top + scrollY + 10 };
  });
  const pixels = await page.evaluate(
    async ({ url, points }) => {
      const img = new Image();
      img.src = url;
      await img.decode();
      const c = document.createElement('canvas');
      c.width = img.naturalWidth;
      c.height = img.naturalHeight;
      const ctx = c.getContext('2d')!;
      ctx.drawImage(img, 0, 0);
      const px = (x: number, y: number) => Array.from(ctx.getImageData(x, y, 1, 1).data.slice(0, 3));
      return {
        w: img.naturalWidth,
        h: img.naturalHeight,
        mask: px(points.x, points.y),
        ignored: px(points.ix, points.iy),
        mark: px(100, 650),
        header: px(5, 5),
      };
    },
    { url: `/s/${id}/__shot/0`, points: masked },
  );

  expect(pixels.w).toBe(pageSize.w);
  expect(pixels.h).toBe(pageSize.h);
  const near = (a: number[], b: number[], d = 40) => a.every((v, i) => Math.abs(v - (b[i] as number)) <= d);
  // The white header, not the body color that a blank render falls back to.
  expect(near(pixels.header, [255, 255, 255], 6), `header ${pixels.header}`).toBe(true);
  expect(near(pixels.mask, [0xc9, 0xd1, 0xdc]), `mask ${pixels.mask}`).toBe(true);
  expect(near(pixels.ignored, [0, 255, 0], 80), `ignored ${pixels.ignored}`).toBe(false);
  expect(near(pixels.mark, [0xe0, 0x2b, 0x20], 60), `mark ${pixels.mark}`).toBe(true);
  const annotations = JSON.parse(req!.fields.annotations!);
  expect(annotations[0]).toEqual({ type: 'rect', x: 100, y: 650, w: 200, h: 110 });
});

test('framework attributes like :class or x-on:click do not blank the screenshot', async ({ page }, info) => {
  const id = sid(info);
  const x = w(page);
  await page.evaluate(() => {
    const el = document.createElement('div');
    el.id = 'alpine';
    el.style.cssText = 'width:120px;height:120px;background:rgb(0,0,255)';
    for (const name of [':class', 'x-on:click', 'x-bind:title', '@click']) el.setAttribute(name, 'x');
    const tpl = document.createElement('template');
    tpl.innerHTML = '<div :role="a" x-on:toast-close="b"></div>';
    document.getElementById('intro')!.append(el, tpl);
  });
  await fill(page, 'a@b.ch', 'Alpine Seite');
  await takeShot(page);
  await x.done.click();
  await x.submit.click();
  await expect(x.success).toBeVisible();

  // Position independent: browsers lay out the clone slightly differently. A blank capture has no blue at all.
  const blue = await page.evaluate(async (url) => {
    const img = new Image();
    img.src = url;
    await img.decode();
    const c = document.createElement('canvas');
    c.width = img.naturalWidth;
    c.height = img.naturalHeight;
    const ctx = c.getContext('2d')!;
    ctx.drawImage(img, 0, 0);
    const d = ctx.getImageData(0, 0, c.width, c.height).data;
    let count = 0;
    for (let i = 0; i < d.length; i += 4) if (d[i]! < 80 && d[i + 1]! < 80 && d[i + 2]! > 140) count++;
    return count;
  }, `/s/${id}/__shot/0`);
  // The block is 120 × 120 = 14400 px.
  expect(blue, `blue pixels ${blue}`).toBeGreaterThan(10_000);
});

test('noscript and template content never shows up in the screenshot', async ({ page }, info) => {
  const id = sid(info);
  const x = w(page);
  await page.evaluate(() => {
    const ns = document.createElement('noscript');
    ns.style.cssText = 'display:block;width:200px;height:200px;background:rgb(255,0,0)';
    ns.innerHTML = '<div class="toast">Nur ohne JavaScript</div>';
    const tpl = document.createElement('template');
    tpl.innerHTML = '<div style="width:200px;height:200px;background:rgb(255,0,0)"></div>';
    document.getElementById('intro')!.prepend(ns, tpl);
  });
  await fill(page, 'a@b.ch', 'Noscript');
  await takeShot(page);
  await x.done.click();
  await x.submit.click();
  await expect(x.success).toBeVisible();
  const red = await page.evaluate(async (url) => {
    const img = new Image();
    img.src = url;
    await img.decode();
    const c = document.createElement('canvas');
    c.width = img.naturalWidth;
    c.height = img.naturalHeight;
    const ctx = c.getContext('2d')!;
    ctx.drawImage(img, 0, 0);
    const d = ctx.getImageData(0, 0, c.width, c.height).data;
    let count = 0;
    for (let i = 0; i < d.length; i += 4) if (d[i]! > 200 && d[i + 1]! < 60 && d[i + 2]! < 60) count++;
    return count;
  }, `/s/${id}/__shot/0`);
  // The fixture has red text of its own (about 1500 px), the block would add up to 40000.
  expect(red, `red pixels ${red}`).toBeLessThan(5000);
});

test('a failed screenshot keeps the camera usable and sends without image', async ({ page }, info) => {
  const id = sid(info);
  const x = w(page);
  await page.route('**/chunks/capture-*.js', (route) => route.abort());
  await page.reload();
  await settle(page);
  await expect(x.panel).toBeVisible();
  await x.camera.click();
  await expectState(page, 'form');
  await expect(x.camera).toHaveClass(/bad/);
  await expect(x.tools.locator('xpath=..').locator('p.e')).toHaveText('Screenshot nicht möglich.');
  await fill(page, 'a@b.ch', 'Ohne Bild');
  await x.submit.click();
  await expect(x.success).toBeVisible();
  const [req] = await requests(page, id);
  expect(req!.screenshot).toBeNull();
  expect(req!.fields.screenshot_error).toBe('render_failed');
});
