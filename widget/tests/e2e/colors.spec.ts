import { expect, test } from '@playwright/test';
import { contrastOf, mock, sid, style, visit, w } from './helpers';

const cases = [
  { name: 'black', color: 'black', bg: 'rgb(0, 0, 0)', fg: 'rgb(255, 255, 255)', button: 'rgb(0, 0, 0)', divider: false },
  { name: 'white', color: 'white', bg: 'rgb(255, 255, 255)', fg: 'rgb(16, 31, 60)', button: 'rgb(16, 31, 60)', divider: true },
  { name: 'custom orange', color: '#ED813A', bg: 'rgb(237, 129, 58)', fg: 'rgb(16, 31, 60)', button: 'rgb(237, 129, 58)', divider: false },
  {
    name: 'custom green',
    color: '#0A7C66',
    bg: 'rgb(10, 124, 102)',
    fg: 'rgb(255, 255, 255)',
    button: 'rgb(10, 124, 102)',
    divider: false,
  },
];

for (const c of cases) {
  test(`project color ${c.name}: header, launcher, button and contrast`, async ({ page }, info) => {
    const id = sid(info);
    await mock(page, id, { color: c.color === 'black' ? '#000000' : c.color === 'white' ? '#FFFFFF' : c.color });
    await visit(page, id);
    const x = w(page);
    await expect.poll(() => style(x.launcher, 'background-color')).toBe(c.button);
    await x.launcher.click();
    expect(await style(x.header, 'background-color')).toBe(c.bg);
    expect(await style(x.header, 'color')).toBe(c.fg);
    expect(await style(x.submit, 'background-color')).toBe(c.button);
    expect(contrastOf(await style(x.header, 'color'), c.bg)).toBeGreaterThanOrEqual(4.5);
    expect(contrastOf(await style(x.submit, 'color'), await style(x.submit, 'background-color'))).toBeGreaterThanOrEqual(4.5);
    const line = await style(x.header, 'border-bottom-color');
    if (c.divider) {
      expect(line).toBe('rgb(226, 232, 240)');
      await x.close.click();
      expect(await style(x.launcher, 'box-shadow')).toContain('rgb(226, 232, 240)');
    } else {
      expect(line).toMatch(/rgba\(0, 0, 0, 0\)|transparent/);
    }
  });
}

test('colors.primary overrides the project color, mid tones are adjusted for buttons', async ({ page }, info) => {
  const id = sid(info);
  await mock(page, id, { color: '#000000' });
  await visit(page, id, { color: '#808080' });
  const x = w(page);
  await x.launcher.click();
  expect(await style(x.header, 'background-color')).toBe('rgb(128, 128, 128)');
  const bg = await style(x.submit, 'background-color');
  expect(bg).not.toBe('rgb(128, 128, 128)');
  expect(contrastOf(await style(x.submit, 'color'), bg)).toBeGreaterThanOrEqual(4.5);
});

test('starts in neutral black when the config is not reachable', async ({ page }, info) => {
  const id = sid(info);
  await mock(page, id, { configStatus: 404 });
  await visit(page, id);
  expect(await style(w(page).launcher, 'background-color')).toBe('rgb(0, 0, 0)');
});

test('no dark mode', async ({ page }, info) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  expect(await style(x.panel, 'background-color')).toBe('rgb(255, 255, 255)');
  expect(await style(x.message, 'color')).toBe('rgb(16, 31, 60)');
});
