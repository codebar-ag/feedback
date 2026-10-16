import { RingBuffer } from './buffer';
import { stripQuery } from './redact';

export interface NetworkEntry {
  method: string;
  url: string;
  status: number;
  duration: number;
  count: number;
  time: number;
}

let unwrapped: typeof fetch | null = null;

/** The unwrapped fetch, used by the widget's own requests (never recorded). */
export const nativeFetch: typeof fetch = (...args) => (unwrapped ?? window.fetch).apply(window, args);

const key = (e: NetworkEntry): string => `${e.method} ${e.url} ${e.status}`;

export function networkBuffer(max: number): RingBuffer<NetworkEntry> {
  return new RingBuffer<NetworkEntry>(max, 1024, 16 * 1024, key);
}

/**
 * Records failed fetch/XHR requests (status >= 400 or network error): only
 * method, URL without query, status and duration. Never headers or bodies.
 */
export function captureNetwork(buffer: RingBuffer<NetworkEntry>): () => void {
  const record = (method: string, url: string, status: number, started: number): void => {
    if (status > 0 && status < 400) return;
    try {
      buffer.push({
        method: method.toUpperCase(),
        url: stripQuery(url),
        status,
        duration: Math.round(performance.now() - started),
        count: 1,
        time: Date.now(),
      });
    } catch {
      // ignore
    }
  };

  const originalFetch = window.fetch;
  unwrapped ??= originalFetch;
  const wrappedFetch: typeof fetch = async (input, init) => {
    const started = performance.now();
    const method = init?.method ?? (input instanceof Request ? input.method : 'GET');
    const url = input instanceof Request ? input.url : String(input);
    try {
      const response = await originalFetch.call(window, input, init);
      record(method, url, response.status, started);
      return response;
    } catch (error) {
      record(method, url, 0, started);
      throw error;
    }
  };
  window.fetch = wrappedFetch;

  const proto = XMLHttpRequest.prototype;
  const open = proto.open;
  const send = proto.send;
  const meta = new WeakMap<XMLHttpRequest, { method: string; url: string }>();
  proto.open = function (this: XMLHttpRequest, method: string, url: string | URL, ...rest: unknown[]) {
    meta.set(this, { method, url: String(url) });
    return (open as (...a: unknown[]) => void).call(this, method, url, ...rest);
  } as typeof proto.open;
  proto.send = function (this: XMLHttpRequest, body?: Document | XMLHttpRequestBodyInit | null) {
    const info = meta.get(this);
    const started = performance.now();
    if (info) this.addEventListener('loadend', () => record(info.method, info.url, this.status, started), { once: true });
    return send.call(this, body);
  };

  return () => {
    if (window.fetch === wrappedFetch) window.fetch = originalFetch;
    if (unwrapped === originalFetch) unwrapped = null;
    proto.open = open;
    proto.send = send;
  };
}
