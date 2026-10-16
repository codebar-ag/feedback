import { resolveLocale } from './i18n/index.js';
import type { FeedbackOptions, Locale, ScreenshotMode } from './types.js';

export interface ResolvedOptions {
  projectKey: string;
  endpoint: string;
  locale: Locale;
  position: 'bottom-right' | 'bottom-left';
  launcherStyle: 'icon' | 'text';
  launcherLabel: string | null;
  screenshotMode: ScreenshotMode;
  quality: number;
  maxPixels: number;
  console: boolean;
  network: boolean;
  maxEntries: number;
  levels: NonNullable<NonNullable<FeedbackOptions['diagnostics']>['levels']>;
  redact: RegExp[];
  maskSelectors: string[];
  ignoreSelectors: string[];
  persist: boolean;
  ttlHours: number;
  zIndex: number;
}

const isSafariOrMobile = (): boolean =>
  /iPhone|iPad|Android|Mobile/i.test(navigator.userAgent) ||
  (/Safari\//.test(navigator.userAgent) && !/Chrome|Chromium|Edg|Firefox/.test(navigator.userAgent));

/** Validates the init options and applies the defaults. */
export function resolveOptions(o: FeedbackOptions): ResolvedOptions {
  if (!o || typeof o.projectKey !== 'string' || !o.projectKey.trim()) throw new Error('projectKey is required');
  if (typeof o.endpoint !== 'string' || !/^https?:\/\//.test(o.endpoint)) throw new Error('endpoint must be an http(s) URL');
  const shot = o.screenshot ?? {};
  const diag = o.diagnostics ?? {};
  return {
    projectKey: o.projectKey.trim(),
    endpoint: o.endpoint.replace(/\/+$/, ''),
    locale: resolveLocale(o.locale ?? document.documentElement.lang ?? 'de_CH'),
    position: o.position === 'bottom-left' ? 'bottom-left' : 'bottom-right',
    launcherStyle: o.launcher?.style === 'text' ? 'text' : 'icon',
    launcherLabel: o.launcher?.label ?? null,
    screenshotMode: shot.mode === 'required' || shot.mode === 'off' ? shot.mode : 'optional',
    quality: typeof shot.quality === 'number' ? Math.min(1, Math.max(0.1, shot.quality)) : 0.82,
    maxPixels: shot.maxPixels ?? (isSafariOrMobile() ? 16_000_000 : 25_000_000),
    console: diag.console !== false,
    network: diag.network !== false,
    maxEntries: Math.max(1, Math.min(50, diag.maxEntries ?? 50)),
    levels: diag.levels ?? ['error', 'warn'],
    redact: diag.redact ?? [],
    maskSelectors: ['[data-feedback-mask]', ...(o.mask?.selectors ?? [])],
    ignoreSelectors: ['[data-feedback-ignore]', ...(o.mask?.ignore ?? [])],
    persist: o.draft?.persist !== false,
    ttlHours: o.draft?.ttlHours ?? 24,
    zIndex: o.zIndex ?? 2147483000,
  };
}

const CONFIG_TTL = 10 * 60 * 1000;

/**
 * GET /v1/projects/{key}/config with 5 s timeout, cached for 10 minutes in
 * sessionStorage. Returns the project color or null (widget stays black).
 */
export async function loadProjectColor(endpoint: string, projectKey: string, fetchFn: typeof fetch): Promise<string | null> {
  const cacheKey = `cbf:${projectKey}:config`;
  try {
    const cached = JSON.parse(sessionStorage.getItem(cacheKey) ?? 'null') as { t: number; primary: string | null } | null;
    if (cached && Date.now() - cached.t < CONFIG_TTL) return cached.primary;
  } catch {
    // no cache
  }
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), 5_000);
  try {
    const res = await fetchFn(`${endpoint}/v1/projects/${encodeURIComponent(projectKey)}/config`, {
      credentials: 'omit',
      mode: 'cors',
      signal: controller.signal,
    });
    if (!res.ok) return null;
    const data = (await res.json()) as { theme?: { primary?: string } };
    const primary = typeof data.theme?.primary === 'string' ? data.theme.primary : null;
    try {
      sessionStorage.setItem(cacheKey, JSON.stringify({ t: Date.now(), primary }));
    } catch {
      // ignore
    }
    return primary;
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}
