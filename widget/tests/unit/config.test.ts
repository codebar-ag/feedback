import { describe, expect, it, vi } from 'vitest';
import { loadProjectColor, resolveOptions } from '../../src/config';

describe('options', () => {
  it('applies the defaults', () => {
    const o = resolveOptions({ projectKey: ' fbk_live_x ', endpoint: 'https://feedback.example/' });
    expect(o).toMatchObject({
      projectKey: 'fbk_live_x',
      endpoint: 'https://feedback.example',
      position: 'bottom-right',
      launcherStyle: 'icon',
      screenshotMode: 'optional',
      quality: 0.82,
      console: true,
      network: true,
      maxEntries: 50,
      levels: ['error', 'warn'],
      maskSelectors: ['[data-feedback-mask]'],
      ignoreSelectors: ['[data-feedback-ignore]'],
      persist: true,
      ttlHours: 24,
      zIndex: 2147483000,
    });
    expect([16_000_000, 25_000_000]).toContain(o.maxPixels);
  });

  it('takes the given options', () => {
    const o = resolveOptions({
      projectKey: 'k',
      endpoint: 'http://localhost:8000',
      locale: 'it-CH',
      position: 'bottom-left',
      launcher: { style: 'text', label: 'Hilfe' },
      screenshot: { mode: 'required', quality: 3, maxPixels: 1000 },
      diagnostics: { console: false, network: false, maxEntries: 500, levels: ['log'] },
      mask: { selectors: ['.secret'], ignore: ['.ad'] },
      draft: { persist: false, ttlHours: 1 },
      zIndex: 10,
    });
    expect(o).toMatchObject({
      locale: 'it_CH',
      position: 'bottom-left',
      launcherStyle: 'text',
      launcherLabel: 'Hilfe',
      screenshotMode: 'required',
      quality: 1,
      maxPixels: 1000,
      console: false,
      network: false,
      maxEntries: 50,
      levels: ['log'],
      maskSelectors: ['[data-feedback-mask]', '.secret'],
      ignoreSelectors: ['[data-feedback-ignore]', '.ad'],
      persist: false,
      ttlHours: 1,
      zIndex: 10,
    });
  });

  it('rejects missing key or endpoint', () => {
    expect(() => resolveOptions({ projectKey: '', endpoint: 'https://a' })).toThrow('projectKey');
    expect(() => resolveOptions({ projectKey: 'k', endpoint: 'ftp://a' })).toThrow('endpoint');
  });
});

describe('project color', () => {
  it('loads and caches the color for 10 minutes', async () => {
    const fetch = vi.fn(async () => new Response(JSON.stringify({ theme: { primary: '#0A7C66' } })));
    expect(await loadProjectColor('https://api', 'k1', fetch)).toBe('#0A7C66');
    expect(await loadProjectColor('https://api', 'k1', fetch)).toBe('#0A7C66');
    expect(fetch).toHaveBeenCalledExactlyOnceWith('https://api/v1/projects/k1/config', expect.objectContaining({ credentials: 'omit' }));
    vi.spyOn(Date, 'now').mockReturnValue(Date.now() + 11 * 60 * 1000);
    await loadProjectColor('https://api', 'k1', fetch);
    expect(fetch).toHaveBeenCalledTimes(2);
  });

  it('returns null when the API is not reachable', async () => {
    expect(
      await loadProjectColor(
        'https://api',
        'k2',
        vi.fn(async () => new Response('', { status: 403 })),
      ),
    ).toBeNull();
    expect(
      await loadProjectColor(
        'https://api',
        'k3',
        vi.fn(async () => Promise.reject(new TypeError('x'))),
      ),
    ).toBeNull();
    expect(
      await loadProjectColor(
        'https://api',
        'k4',
        vi.fn(async () => new Response('{}')),
      ),
    ).toBeNull();
  });
});
