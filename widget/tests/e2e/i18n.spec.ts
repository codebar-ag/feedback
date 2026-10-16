import { expect, test } from '@playwright/test';
import { LOCALES } from '../../src/i18n/index.js';
import { sid, takeShot, visit, w } from './helpers';

const KEYS = Object.keys(LOCALES.de_CH);

for (const locale of ['de_CH', 'fr_CH', 'en_CH', 'it_CH'] as const) {
  test(`${locale} is complete in every state, no raw keys`, async ({ page }, info) => {
    await visit(page, sid(info), { locale });
    const t = LOCALES[locale];
    const x = w(page);
    await expect(x.launcher).toHaveAttribute('aria-label', t.launcher);
    await x.launcher.click();
    await expect(x.root).toHaveAttribute('lang', locale.replace('_', '-'));
    await expect(x.form).toContainText(t.email);
    await expect(x.form).toContainText(t.message);
    await expect(x.form).toContainText(t.tools);
    await expect(x.message).toHaveAttribute('placeholder', t.message_ph);
    await expect(x.submit).toHaveText(t.submit);
    await expect(x.camera).toHaveAttribute('aria-label', t.shot_add);
    await x.submit.click();
    await expect(x.errors.first()).toHaveText(t.error_email);
    await expect(x.errors.nth(1)).toHaveText(t.error_message);
    await x.email.fill('a@b.ch');
    await x.message.fill('abc');
    await takeShot(page);
    await expect(x.done).toHaveText(t.done);
    await expect(x.tool('rect')).toHaveAttribute('aria-label', t.t_rect);
    await expect(x.tool('marker')).toHaveAttribute('aria-label', t.t_marker);
    await expect(x.undo).toHaveAttribute('aria-label', t.t_undo);
    await expect(x.clear).toHaveAttribute('aria-label', t.t_clear);
    await x.done.click();
    await expect(x.edit).toHaveAttribute('aria-label', t.edit);
    await expect(x.remove).toHaveAttribute('aria-label', t.remove);
    const values = await x.host.evaluate((h) => {
      const parts: string[] = [];
      for (const el of Array.from(h.shadowRoot!.querySelectorAll('*'))) {
        if (el.children.length === 0) parts.push((el.textContent ?? '').trim());
        for (const a of ['aria-label', 'title', 'placeholder']) parts.push(el.getAttribute(a) ?? '');
      }
      return parts.filter(Boolean);
    });
    // No text or label is a raw message key.
    for (const key of KEYS) expect(values).not.toContain(key);
    expect(values.filter((v) => /^[a-z]+_[a-z_]+$/.test(v))).toEqual([]);
    await x.submit.click();
    await expect(x.success).toContainText(t.success_title);
    await expect(x.success).toContainText(t.success_text);
  });
}

test('falls back to the base language and to en_CH', async ({ page }, info) => {
  await visit(page, sid(info), { locale: 'fr' });
  await expect(w(page).launcher).toHaveAttribute('aria-label', 'Donner un avis');
  await visit(page, sid(info), { locale: 'es_ES' });
  await expect(w(page).launcher).toHaveAttribute('aria-label', 'Give feedback');
});

test('messages override single texts and setLocale switches live', async ({ page }, info) => {
  await visit(page, sid(info), { messages: JSON.stringify({ de_CH: { submit: 'Abschicken' } }) });
  const x = w(page);
  await x.launcher.click();
  await expect(x.submit).toHaveText('Abschicken');
  await page.evaluate(() => (window as unknown as { feedback: { setLocale(l: string): void } }).feedback.setLocale('it_CH'));
  await expect(x.submit).toHaveText('Invia');
  await expect(x.form).toContainText('Il tuo feedback');
});
