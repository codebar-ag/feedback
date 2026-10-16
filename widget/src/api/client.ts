import type { FeedbackPayload } from '../types.js';

export type SendResult =
  | { ok: true; id: string; status: number }
  | { ok: false; code: string; status: number; fields?: Record<string, string[]>; retryAfter?: number };

export interface SendOptions {
  fetch: typeof fetch;
  timeoutMs?: number;
  retryDelayMs?: number;
  /** Called once on 413 to get a smaller screenshot (or null to drop it). */
  shrink?: (blob: Blob) => Promise<Blob | null>;
}

const CODES: Record<number, string> = {
  403: 'origin_not_allowed',
  404: 'project_not_found',
  413: 'payload_too_large',
  422: 'validation_failed',
  429: 'rate_limited',
};

const sleep = (ms: number): Promise<void> => new Promise((r) => setTimeout(r, ms));

export function toFormData(payload: FeedbackPayload, screenshot: Blob | null): FormData {
  const form = new FormData();
  for (const [k, v] of Object.entries(payload)) {
    if (v === undefined || v === null) continue;
    form.append(k, typeof v === 'object' ? JSON.stringify(v) : String(v));
  }
  if (screenshot) form.append('screenshot', screenshot, 'screenshot.jpg');
  return form;
}

async function errorBody(res: Response): Promise<{ code?: string; fields?: Record<string, string[]> }> {
  try {
    const body = (await res.json()) as { error?: { code?: string; fields?: Record<string, string[]> } };
    return body.error ?? {};
  } catch {
    return {};
  }
}

/**
 * POST /v1/projects/{key}/feedback: credentials omitted,
 * 20 s timeout, one retry after 1.5 s on network errors, timeouts and 5xx
 * with the same Idempotency-Key, one smaller screenshot on 413.
 */
export async function sendFeedback(
  url: string,
  idempotencyKey: string,
  payload: FeedbackPayload,
  screenshot: Blob | null,
  options: SendOptions,
): Promise<SendResult> {
  const timeoutMs = options.timeoutMs ?? 20_000;
  const retryDelayMs = options.retryDelayMs ?? 1_500;
  let shot = screenshot;
  let body = payload;
  let shrunk = false;
  let retried = false;

  for (;;) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    let res: Response | null = null;
    let failure = 'network_error';
    try {
      res = await options.fetch(url, {
        method: 'POST',
        body: toFormData(body, shot),
        credentials: 'omit',
        mode: 'cors',
        headers: { 'Idempotency-Key': idempotencyKey },
        signal: controller.signal,
      });
    } catch (error) {
      failure = (error as Error)?.name === 'AbortError' ? 'timeout' : 'network_error';
    } finally {
      clearTimeout(timer);
    }

    if (res && (res.status === 200 || res.status === 201)) {
      const data = (await res.json().catch(() => ({}))) as { id?: string };
      return { ok: true, id: String(data.id ?? ''), status: res.status };
    }

    if (!res || res.status >= 500) {
      if (!retried) {
        retried = true;
        await sleep(retryDelayMs);
        continue;
      }
      return { ok: false, code: res ? 'server_error' : failure, status: res?.status ?? 0 };
    }

    if (res.status === 413 && shot) {
      if (!shrunk && options.shrink) {
        shrunk = true;
        shot = await options.shrink(shot).catch(() => null);
      } else {
        shot = null;
      }
      if (!shot) body = { ...body, screenshot_error: 'too_large' };
      continue;
    }

    const err = await errorBody(res);
    const retryAfter = Number(res.headers.get('Retry-After'));
    return {
      ok: false,
      code: err.code ?? CODES[res.status] ?? 'http_' + res.status,
      status: res.status,
      ...(err.fields ? { fields: err.fields } : {}),
      ...(Number.isFinite(retryAfter) && retryAfter > 0 ? { retryAfter } : {}),
    };
  }
}
