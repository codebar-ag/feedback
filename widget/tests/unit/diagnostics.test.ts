import { afterEach, describe, expect, it, vi } from 'vitest';
import { RingBuffer } from '../../src/diagnostics/buffer';
import { captureConsole } from '../../src/diagnostics/console';
import { collectClient, prefetchHints } from '../../src/diagnostics/meta';
import { captureNetwork, nativeFetch, networkBuffer } from '../../src/diagnostics/network';

let off: (() => void) | null = null;
afterEach(() => {
  off?.();
  off = null;
});

describe('console capture', () => {
  it('records errors and warnings and calls the original unchanged', () => {
    const original = vi.fn();
    console.error = original;
    const buffer = new RingBuffer();
    off = captureConsole(buffer, { levels: ['error', 'warn'], redact: [] });

    console.error('Boom', { token: 'x' }, new Error('inner'));
    console.log('not captured');

    expect(original).toHaveBeenCalledWith('Boom', { token: 'x' }, expect.any(Error));
    const [e] = buffer.entries();
    expect(e).toMatchObject({ type: 'error', message: 'Boom {"token":"[redacted]"} Error: inner', count: 1 });
    expect(e!.stack).toContain('inner');
    expect(buffer.entries()).toHaveLength(1);
  });

  it('records configured levels only and redacts', () => {
    const buffer = new RingBuffer();
    off = captureConsole(buffer, { levels: ['info'], redact: [/geheim/] });
    console.info('Bearer abcdefghijk geheim');
    console.error('ignored');
    expect(buffer.entries().map((e) => e.message)).toEqual(['Bearer [redacted] [redacted]']);
  });

  it('records uncaught errors, rejections, failed resources and CSP violations', () => {
    const buffer = new RingBuffer();
    off = captureConsole(buffer, { levels: [], redact: [] });

    window.dispatchEvent(
      new ErrorEvent('error', { message: 'Uncaught TypeError: x', filename: 'https://a.ch/app.js?v=1', lineno: 412, colno: 18 }),
    );
    const rejection = new Event('unhandledrejection') as PromiseRejectionEvent;
    Object.defineProperty(rejection, 'reason', { value: new Error('nope') });
    window.dispatchEvent(rejection);
    const img = document.createElement('img');
    img.setAttribute('src', '/img/hero.webp?sig=secret');
    document.body.append(img);
    img.dispatchEvent(new Event('error'));
    const csp = new Event('securitypolicyviolation') as SecurityPolicyViolationEvent;
    Object.defineProperties(csp, { violatedDirective: { value: 'img-src' }, blockedURI: { value: 'https://cdn.x/y?z' } });
    document.dispatchEvent(csp);

    expect(buffer.entries().map((e) => [e.type, e.message])).toEqual([
      ['error', 'Uncaught TypeError: x'],
      ['unhandledrejection', 'Unhandled rejection: Error: nope'],
      ['resource', `Failed to load <img> ${location.origin}/img/hero.webp`],
      ['csp', 'CSP violation: img-src https://cdn.x/y'],
    ]);
    expect(buffer.entries()[0]).toMatchObject({ source: 'https://a.ch/app.js', line: 412, column: 18 });
  });

  it('installs once and restores everything', () => {
    const before = console.error;
    const buffer = new RingBuffer();
    const first = captureConsole(buffer, { levels: ['error'], redact: [] });
    const second = captureConsole(buffer, { levels: ['error'], redact: [] });
    console.error('once');
    expect(buffer.entries()).toHaveLength(1);
    second();
    first();
    expect(console.error).toBe(before);
    console.error('after');
    window.dispatchEvent(new ErrorEvent('error', { message: 'late' }));
    expect(buffer.entries()).toHaveLength(1);
  });
});

describe('network capture', () => {
  it('records failed fetch requests without query, ignores successful ones', async () => {
    const responses = [new Response('', { status: 500 }), new Response('', { status: 200 })];
    window.fetch = vi.fn(async () => responses.shift()!) as typeof fetch;
    const buffer = networkBuffer(50);
    off = captureNetwork(buffer);

    await fetch('https://api.beispiel.ch/items?token=abc', { method: 'post' });
    await fetch('https://api.beispiel.ch/ok');
    window.fetch = window.fetch as typeof fetch;

    expect(buffer.entries()).toEqual([expect.objectContaining({ method: 'POST', url: 'https://api.beispiel.ch/items', status: 500 })]);
  });

  it('records network errors and keeps the widget fetch unrecorded', async () => {
    const base = vi.fn(async () => {
      throw new TypeError('Failed to fetch');
    }) as unknown as typeof fetch;
    window.fetch = base;
    const buffer = networkBuffer(50);
    off = captureNetwork(buffer);

    await expect(fetch(new Request('https://x.ch/a?b=1'))).rejects.toThrow('Failed to fetch');
    await expect(nativeFetch('https://x.ch/own')).rejects.toThrow();

    expect(buffer.entries()).toEqual([expect.objectContaining({ method: 'GET', url: 'https://x.ch/a', status: 0 })]);
    expect(base).toHaveBeenCalledTimes(2);
    off();
    off = null;
    expect(window.fetch).toBe(base);
  });

  it('records failed XHR requests', () => {
    const buffer = new RingBuffer(10, 1024, 16384, () => 'k') as ReturnType<typeof networkBuffer>;
    off = captureNetwork(buffer);
    const xhr = new XMLHttpRequest();
    xhr.open('DELETE', 'https://x.ch/item/1?secret=1');
    Object.defineProperty(xhr, 'status', { value: 404 });
    const send = vi.spyOn(XMLHttpRequest.prototype, 'send');
    xhr.send();
    xhr.dispatchEvent(new Event('loadend'));
    expect(send).toBeDefined();
    expect(buffer.entries()[0]).toMatchObject({ method: 'DELETE', url: 'https://x.ch/item/1', status: 404 });
  });
});

describe('client meta', () => {
  it('collects browser and system data', async () => {
    Object.defineProperty(navigator, 'userAgentData', {
      configurable: true,
      value: {
        brands: [{ brand: 'Chromium', version: '154' }],
        mobile: false,
        platform: 'macOS',
        getHighEntropyValues: async () => ({ platformVersion: '26.6.2', fullVersionList: [{ brand: 'Chromium', version: '154.0.1' }] }),
      },
    });
    await prefetchHints();
    const client = collectClient();
    expect(client).toMatchObject({
      userAgent: navigator.userAgent,
      viewport: { w: innerWidth, h: innerHeight },
      dpr: devicePixelRatio,
      language: navigator.language,
      online: true,
      uaData: { platform: 'macOS', platformVersion: '26.6.2' },
    });
    expect(typeof client.timezone).toBe('string');
  });
});
