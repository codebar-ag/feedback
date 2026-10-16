import { expect, test } from '@playwright/test';
import { box, settle, sid, style, visit, w } from './helpers';

test('with identity: "Senden als" with badge, no email field', async ({ page }, info) => {
  await visit(page, sid(info), { identity: '1' });
  const x = w(page);
  await x.launcher.click();
  await expect(x.email).toBeHidden();
  await expect(x.badge).toBeVisible();
  await expect(x.badge.locator('b')).toHaveText('Anna Muster');
  await expect(x.badge.locator('span')).toHaveText('anna.muster@beispiel.ch');
  await expect(x.form).toContainText('Senden als');
});

test('without identity: only the email field, no name field', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await expect(x.email).toBeVisible();
  await expect(x.badge).toBeHidden();
  await expect(x.host.locator('input[name=name]')).toHaveCount(0);
  await expect(x.form.locator('input:not(.hp):visible, textarea:visible')).toHaveCount(2);
});

test('required fields show * and errors, focus jumps to the first error', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  for (const label of await x.form.locator('label').all()) {
    expect(await label.evaluate((el) => getComputedStyle(el, '::after').content)).toBe('" *"');
  }
  await x.submit.click();
  await expect(x.errors.nth(0)).toHaveText('Bitte gib eine gültige E-Mail ein.');
  await expect(x.errors.nth(1)).toHaveText('Bitte gib mindestens 3 Zeichen ein.');
  await expect(x.email).toBeFocused();
  await expect(x.email).toHaveAttribute('aria-invalid', 'true');
  await expect(x.email).toHaveAttribute('aria-required', 'true');

  await x.email.fill('anna@beispiel.ch');
  await expect(x.errors.nth(0)).toHaveText('');
  await x.message.fill('ok!');
  await expect(x.errors.nth(1)).toHaveText('');
  await expect(x.message).not.toHaveAttribute('aria-invalid', 'true');
});

// validation without layout shift.
test('error messages do not shift the layout (0 px)', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  await settle(page);
  const before = await Promise.all([box(x.panel), box(x.message), box(x.tools), box(x.submit)]);
  await x.submit.click();
  await expect(x.errors.nth(1)).toBeVisible();
  const after = await Promise.all([box(x.panel), box(x.message), box(x.tools), box(x.submit)]);
  expect(after).toEqual(before);
  for (const e of await x.errors.all()) expect((await box(e)).height).toBe(16);
});

test('sending state: spinner, aria-busy, disabled', async ({ page }, info) => {
  const id = sid(info);
  await page.request.post(`/s/${id}/__mock`, { data: { responses: [{ status: 201, delayMs: 1500 }] } });
  await visit(page, id);
  const x = w(page);
  await x.launcher.click();
  await x.email.fill('a@b.ch');
  await x.message.fill('Bitte schneller');
  await x.submit.click();
  await expect(x.submit).toHaveAttribute('aria-busy', 'true');
  await expect(x.submit).toBeDisabled();
  await expect(x.submit).toHaveText('Wird gesendet …');
  await expect(x.submit.locator('.sp')).toBeVisible();
  await expect(x.message).toBeDisabled();
  await expect(x.success).toBeVisible();
});

// typography 13 / 15 / 18.
test('typography: labels and errors 13, fields and buttons 15', async ({ page }, info) => {
  await visit(page, sid(info));
  const x = w(page);
  await x.launcher.click();
  expect(await style(x.form.locator('label').first(), 'font-size')).toBe('13px');
  expect(await style(x.errors.first(), 'font-size')).toBe('13px');
  expect(await style(x.email, 'font-size')).toBe('15px');
  expect(await style(x.message, 'font-size')).toBe('15px');
  expect(await style(x.submit, 'font-size')).toBe('15px');
  expect(await style(x.submit, 'font-weight')).toBe('700');
  expect((await box(x.email)).height).toBe(44);
  expect((await box(x.message)).height).toBeGreaterThanOrEqual(170);
  expect(await style(x.email, 'border-radius')).toBe('12px');
  const sizes = await x.host.evaluate((host) => {
    const set = new Set<string>();
    for (const el of Array.from(host.shadowRoot!.querySelectorAll<HTMLElement>('.p *')))
      if (el.offsetParent && el.textContent?.trim()) set.add(getComputedStyle(el).fontSize);
    return [...set].sort();
  });
  expect(sizes.every((s) => ['13px', '15px', '18px'].includes(s))).toBe(true);
});
