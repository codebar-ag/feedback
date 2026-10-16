import { describe, expect, it, vi } from 'vitest';
import { sendFeedback, toFormData } from '../../src/api/client';
import type { FeedbackPayload } from '../../src/types';

const payload: FeedbackPayload = {
  message: 'Hallo Welt',
  email: 'a@b.ch',
  locale: 'de_CH',
  page_url: 'https://www.beispiel.ch/',
  client: { viewport: { w: 1, h: 2 } },
  console_log: [{ type: 'error', message: 'x' }],
  widget_version: '1.0.0',
  elapsed_ms: 1200,
  website: '',
};

const json = (status: number, body: unknown, headers: Record<string, string> = {}) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });

const URL_ = 'https://api.example/v1/projects/k/feedback';

describe('API client', () => {
  it('builds multipart form data as in concept 5.2', () => {
    const form = toFormData({ ...payload, page_title: undefined }, new Blob(['x'], { type: 'image/jpeg' }));
    expect(form.get('message')).toBe('Hallo Welt');
    expect(form.get('client')).toBe('{"viewport":{"w":1,"h":2}}');
    expect(form.get('console_log')).toBe('[{"type":"error","message":"x"}]');
    expect(form.get('elapsed_ms')).toBe('1200');
    expect(form.get('website')).toBe('');
    expect(form.has('page_title')).toBe(false);
    expect(form.get('screenshot')).toBeInstanceOf(File);
  });

  it('sends with Idempotency-Key and without credentials', async () => {
    const fetch = vi.fn(async () => json(201, { id: '01J', status: 'received' }));
    const result = await sendFeedback(URL_, 'key-1', payload, null, { fetch });
    expect(result).toEqual({ ok: true, id: '01J', status: 201 });
    expect(fetch).toHaveBeenCalledWith(
      URL_,
      expect.objectContaining({ method: 'POST', credentials: 'omit', mode: 'cors', headers: { 'Idempotency-Key': 'key-1' } }),
    );
  });

  it('retries once after a network error with the same key', async () => {
    const fetch = vi
      .fn()
      .mockRejectedValueOnce(new TypeError('Failed to fetch'))
      .mockResolvedValueOnce(json(200, { id: '01J' }));
    const result = await sendFeedback(URL_, 'key-2', payload, null, { fetch, retryDelayMs: 1 });
    expect(result).toMatchObject({ ok: true, status: 200 });
    expect(fetch).toHaveBeenCalledTimes(2);
    expect(fetch.mock.calls.map((c) => (c[1] as RequestInit).headers)).toEqual([
      { 'Idempotency-Key': 'key-2' },
      { 'Idempotency-Key': 'key-2' },
    ]);
  });

  it('gives up after one retry on 5xx', async () => {
    const fetch = vi.fn(async () => json(503, {}));
    expect(await sendFeedback(URL_, 'k', payload, null, { fetch, retryDelayMs: 1 })).toEqual({
      ok: false,
      code: 'server_error',
      status: 503,
    });
    expect(fetch).toHaveBeenCalledTimes(2);
  });

  it('reports timeouts', async () => {
    const fetch = vi.fn(
      (_url: RequestInfo | URL, init?: RequestInit) =>
        new Promise<Response>((_, reject) =>
          init?.signal?.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError'))),
        ),
    );
    expect(await sendFeedback(URL_, 'k', payload, null, { fetch, timeoutMs: 5, retryDelayMs: 1 })).toEqual({
      ok: false,
      code: 'timeout',
      status: 0,
    });
    expect(fetch).toHaveBeenCalledTimes(2);
  });

  it('shrinks the screenshot once on 413, then sends without it', async () => {
    const big = new Blob(['big']);
    const small = new Blob(['s']);
    const shrink = vi.fn(async () => small);
    const fetch = vi.fn(async (_url: RequestInfo | URL, _init?: RequestInit) => json(413, { error: { code: 'payload_too_large' } }));
    fetch
      .mockResolvedValueOnce(json(413, {}))
      .mockResolvedValueOnce(json(413, {}))
      .mockResolvedValueOnce(json(201, { id: 'x' }));

    const result = await sendFeedback(URL_, 'k', payload, big, { fetch, shrink });

    expect(result.ok).toBe(true);
    expect(shrink).toHaveBeenCalledExactlyOnceWith(big);
    const bodies = fetch.mock.calls.map((c) => (c[1] as RequestInit).body as FormData);
    expect(bodies[1]!.get('screenshot')).toBeInstanceOf(File);
    expect(bodies[2]!.has('screenshot')).toBe(false);
    expect(bodies[2]!.get('screenshot_error')).toBe('too_large');
  });

  it('maps 422 field errors', async () => {
    const fetch = vi.fn(async () => json(422, { error: { code: 'validation_failed', fields: { email: ['invalid'] } } }));
    expect(await sendFeedback(URL_, 'k', payload, null, { fetch })).toEqual({
      ok: false,
      code: 'validation_failed',
      status: 422,
      fields: { email: ['invalid'] },
    });
  });

  it.each([
    [403, { error: { code: 'origin_not_allowed' } }, 'origin_not_allowed'],
    [404, { error: { code: 'project_not_found' } }, 'project_not_found'],
    [404, 'not json', 'project_not_found'],
    [418, {}, 'http_418'],
  ])('maps %s', async (status, body, code) => {
    const fetch = vi.fn(async () => (typeof body === 'string' ? new Response(body, { status }) : json(status, body)));
    expect(await sendFeedback(URL_, 'k', payload, null, { fetch })).toEqual({ ok: false, code, status });
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('passes Retry-After on 429', async () => {
    const fetch = vi.fn(async () => json(429, { error: { code: 'rate_limited' } }, { 'Retry-After': '42' }));
    expect(await sendFeedback(URL_, 'k', payload, null, { fetch })).toEqual({
      ok: false,
      code: 'rate_limited',
      status: 429,
      retryAfter: 42,
    });
  });
});
