import AxeBuilder from '@axe-core/playwright';
import { expect, type Locator, type Page, type TestInfo } from '@playwright/test';

export interface Recorded {
  idempotencyKey: string | null;
  origin: string | null;
  fields: Record<string, string>;
  screenshot: { size: number; type: string; name: string } | null;
  duplicate: boolean;
  status: number;
}

/** One mock API session per test so that parallel tests do not interfere. */
export function sid(info: TestInfo): string {
  return `${info.project.name}-${info.workerIndex}-${info.testId}`.replace(/[^\w-]/g, '');
}

export async function mock(page: Page, id: string, config: Record<string, unknown>): Promise<void> {
  await page.request.post(`/s/${id}/__mock`, { data: config });
}

export async function requests(page: Page, id: string): Promise<Recorded[]> {
  return (await page.request.get(`/s/${id}/__requests`)).json() as Promise<Recorded[]>;
}

export async function visit(page: Page, id: string, params: Record<string, string> = {}, path = '/fixtures/index.html'): Promise<void> {
  const q = new URLSearchParams({ sid: id, ...params });
  await page.goto(`${path}?${q}`);
  await expect(page.locator('html')).toHaveAttribute('data-feedback-ready', '1');
}

/** Locators inside the widget's (open) shadow root; Playwright CSS pierces it. */
export function w(page: Page) {
  const host = page.locator('codebar-feedback');
  return {
    host,
    root: host.locator('.r'),
    launcher: host.locator('button.l'),
    panel: host.locator('section.p'),
    header: host.locator('.hd'),
    close: host.locator('.hd button'),
    form: host.locator('form.fm'),
    email: host.locator('input[type=email]'),
    message: host.locator('textarea'),
    submit: host.locator('button[type=submit]'),
    badge: host.locator('.id'),
    tools: host.locator('.tr'),
    camera: host.locator('.tr button').first(),
    thumb: host.locator('img.th'),
    edit: host.locator('.tr button').nth(1),
    remove: host.locator('.tr button').nth(2),
    errors: host.locator('p.e'),
    success: host.locator('.st.ok'),
    failure: host.locator('.st.err'),
    toolbar: host.locator('.tb'),
    canvas: host.locator('canvas.cv'),
    tool: (name: 'rect' | 'marker') => host.locator(`.tb [data-tool=${name}]`),
    undo: host.locator('.tb [data-action=undo]'),
    clear: host.locator('.tb [data-action=clear]'),
    done: host.locator('.tb [data-action=done]'),
  };
}

/** Waits until the widget's animations (panel pop) are finished. */
export async function settle(page: Page): Promise<void> {
  await page.locator('codebar-feedback').evaluate(async (host) => {
    await Promise.all(
      host
        .shadowRoot!.getAnimations()
        .filter((a) => a.effect?.getTiming().iterations !== Infinity)
        .map((a) => a.finished),
    );
  });
}

export async function state(page: Page): Promise<string | null> {
  return w(page).root.getAttribute('data-state');
}

export async function expectState(page: Page, value: string): Promise<void> {
  await expect(w(page).root).toHaveAttribute('data-state', value);
}

export async function fill(page: Page, email: string | null, message: string): Promise<void> {
  const x = w(page);
  if (email !== null) await x.email.fill(email);
  await x.message.fill(message);
}

/** Takes a screenshot and waits for the annotation mode. */
export async function takeShot(page: Page): Promise<void> {
  await w(page).camera.click();
  await expectState(page, 'annotating');
}

/** Draws with the mouse (rect or marker) between two viewport points. */
export async function drag(page: Page, from: [number, number], to: [number, number], steps = 8): Promise<void> {
  await page.mouse.move(from[0], from[1]);
  await page.mouse.down();
  for (let i = 1; i <= steps; i++) {
    await page.mouse.move(from[0] + ((to[0] - from[0]) * i) / steps, from[1] + ((to[1] - from[1]) * i) / steps);
  }
  await page.mouse.up();
}

export async function marks(page: Page): Promise<unknown[]> {
  return page.evaluate(() => {
    const keys = Object.keys(sessionStorage).filter((k) => k.endsWith(':draft'));
    const draft = JSON.parse(sessionStorage.getItem(keys[0] ?? '') ?? 'null') as { shot?: { annotations: unknown[] } } | null;
    return draft?.shot?.annotations ?? [];
  });
}

export async function draft(page: Page): Promise<Record<string, unknown> | null> {
  return page.evaluate(() => {
    const key = Object.keys(sessionStorage).find((k) => k.endsWith(':draft'));
    return key ? (JSON.parse(sessionStorage.getItem(key) ?? 'null') as Record<string, unknown>) : null;
  });
}

export async function box(locator: Locator): Promise<{ x: number; y: number; width: number; height: number }> {
  const b = await locator.boundingBox();
  if (!b) throw new Error('element not visible');
  return b;
}

export async function axe(page: Page): Promise<void> {
  const result = await new AxeBuilder({ page }).include('codebar-feedback').analyze();
  expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([]);
}

/** WCAG contrast of two computed rgb() colors. */
export function contrastOf(a: string, b: string): number {
  const lum = (c: string): number => {
    const [r, g, bl] = (c.match(/\d+(\.\d+)?/g) ?? []).slice(0, 3).map((v) => {
      const s = Number(v) / 255;
      return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    }) as [number, number, number];
    return 0.2126 * r + 0.7152 * g + 0.0722 * bl;
  };
  const x = lum(a);
  const y = lum(b);
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

export const style = (locator: Locator, prop: string): Promise<string> =>
  locator.evaluate((el, p) => getComputedStyle(el).getPropertyValue(p), prop);
