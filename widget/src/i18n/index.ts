import de_CH from './de_CH.js';
import en_CH from './en_CH.js';
import fr_CH from './fr_CH.js';
import it_CH from './it_CH.js';
import type { Locale } from '../types.js';

export type Messages = typeof de_CH;
export type MessageKey = keyof Messages;

export const LOCALES: Record<Locale, Messages> = { de_CH, fr_CH, en_CH, it_CH };

const FALLBACK: Locale = 'en_CH';

/**
 * Resolves a requested locale: exact (de_CH, de-CH) → base language (de) → en_CH.
 */
export function resolveLocale(input: string | undefined | null): Locale {
  const value = String(input ?? '').replace('-', '_');
  const exact = Object.keys(LOCALES).find((l) => l.toLowerCase() === value.toLowerCase());
  if (exact) return exact as Locale;
  const base = value.slice(0, 2).toLowerCase();
  const byBase = Object.keys(LOCALES).find((l) => l.startsWith(base + '_'));
  return (byBase as Locale | undefined) ?? FALLBACK;
}

/** BCP-47 tag for Intl and the lang attribute. */
export function bcp47(locale: Locale): string {
  return locale.replace('_', '-');
}

export type Translator = (key: MessageKey) => string;

export function translator(locale: Locale, overrides: Partial<Record<Locale, Partial<Messages>>> = {}): Translator {
  const own = LOCALES[locale];
  const custom = overrides[locale] ?? {};
  return (key) => custom[key] ?? own[key] ?? LOCALES[FALLBACK][key] ?? key;
}
