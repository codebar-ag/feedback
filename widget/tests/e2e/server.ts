/**
 * Mock server for the widget E2E tests (node --experimental-strip-types):
 * serves fixtures/ and dist/, and a mock of the feedback API per test
 * session under /s/{sid}/… so parallel tests do not share state.
 *
 *   POST /s/{sid}/__mock       {"responses":[{status,body,headers,delayMs,drop}],"color":"#0A7C66"}
 *   GET  /s/{sid}/__requests   recorded feedback requests (fields, screenshot meta)
 *   GET  /s/{sid}/__shot/{n}   screenshot of request n
 */
import { createServer, type IncomingMessage, type ServerResponse } from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';

interface MockResponse {
  status: number;
  body?: unknown;
  headers?: Record<string, string>;
  delayMs?: number;
  drop?: boolean;
}

interface Recorded {
  idempotencyKey: string | null;
  origin: string | null;
  contentType: string | null;
  fields: Record<string, string>;
  screenshot: { size: number; type: string; name: string } | null;
  duplicate: boolean;
  status: number;
}

interface Session {
  responses: MockResponse[];
  color: string | null;
  configStatus: number;
  requests: Recorded[];
  shots: Buffer[];
  ids: Map<string, string>;
}

const root = fileURLToPath(new URL('../..', import.meta.url));
const port = Number(process.env.PORT ?? 4173);
const sessions = new Map<string, Session>();
const TYPES: Record<string, string> = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript',
  '.css': 'text/css',
  '.json': 'application/json',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
};

function session(sid: string): Session {
  let s = sessions.get(sid);
  if (!s) sessions.set(sid, (s = { responses: [], color: null, configStatus: 200, requests: [], shots: [], ids: new Map() }));
  return s;
}

async function body(req: IncomingMessage): Promise<Buffer> {
  const chunks: Buffer[] = [];
  for await (const c of req) chunks.push(c as Buffer);
  return Buffer.concat(chunks);
}

function send(res: ServerResponse, status: number, data: unknown, headers: Record<string, string> = {}): void {
  const isBuffer = Buffer.isBuffer(data);
  res.writeHead(status, {
    'Content-Type': isBuffer ? 'application/octet-stream' : 'application/json',
    'Cache-Control': 'no-store',
    ...headers,
  });
  res.end(isBuffer ? data : data === undefined ? '' : JSON.stringify(data));
}

let counter = 0;

async function api(req: IncomingMessage, res: ServerResponse, sid: string, rest: string): Promise<void> {
  const s = session(sid);
  const cors = {
    'Access-Control-Allow-Origin': req.headers.origin ?? '*',
    Vary: 'Origin',
    'Access-Control-Allow-Headers': 'Content-Type, Idempotency-Key',
    'Access-Control-Expose-Headers': 'Retry-After',
  };

  if (rest === '/__mock' && req.method === 'POST') {
    const cfg = JSON.parse((await body(req)).toString() || '{}') as Partial<Session>;
    if (cfg.responses) s.responses = cfg.responses;
    if (cfg.color !== undefined) s.color = cfg.color;
    if (cfg.configStatus) s.configStatus = cfg.configStatus;
    return send(res, 204, undefined);
  }
  if (rest === '/__requests') return send(res, 200, s.requests);
  const shot = /^\/__shot\/(\d+)$/.exec(rest);
  if (shot) {
    const data = s.shots[Number(shot[1])];
    return data ? send(res, 200, data, { 'Content-Type': 'image/jpeg', ...cors }) : send(res, 404, {});
  }

  if (req.method === 'OPTIONS') return send(res, 204, undefined, { ...cors, 'Access-Control-Allow-Methods': 'GET, POST, OPTIONS' });
  if (/^\/v1\/projects\/[^/]+\/config$/.test(rest)) {
    return send(
      res,
      s.configStatus,
      s.configStatus === 200 ? { theme: { primary: s.color ?? '#000000' } } : { error: { code: 'project_not_found' } },
      cors,
    );
  }
  if (/^\/v1\/projects\/[^/]+\/feedback$/.test(rest) && req.method === 'POST') {
    const raw = await body(req);
    const form = await new Request('http://x', {
      method: 'POST',
      headers: { 'content-type': String(req.headers['content-type']) },
      body: new Uint8Array(raw),
    }).formData();
    const fields: Record<string, string> = {};
    let screenshot: Recorded['screenshot'] = null;
    for (const [k, v] of form.entries()) {
      if (typeof v === 'string') fields[k] = v;
      else {
        screenshot = { size: v.size, type: v.type, name: v.name };
        s.shots[s.requests.length] = Buffer.from(await v.arrayBuffer());
      }
    }
    const key = (req.headers['idempotency-key'] as string | undefined) ?? null;
    const mock = s.responses.shift() ?? { status: 201 };
    const duplicate = key !== null && s.ids.has(key);
    s.requests.push({
      idempotencyKey: key,
      origin: req.headers.origin ?? null,
      contentType: req.headers['content-type'] ?? null,
      fields,
      screenshot,
      duplicate,
      status: mock.drop ? 0 : mock.status,
    });
    if (mock.delayMs) await new Promise((r) => setTimeout(r, mock.delayMs));
    if (mock.drop) {
      req.socket.destroy();
      return;
    }
    if (mock.status === 201 || mock.status === 200) {
      if (duplicate) return send(res, 200, { id: s.ids.get(key as string), status: 'received' }, cors);
      const id = `01JE2E${String(++counter).padStart(20, '0')}`;
      if (key) s.ids.set(key, id);
      return send(res, mock.status, mock.body ?? { id, status: 'received' }, cors);
    }
    return send(res, mock.status, mock.body ?? {}, { ...cors, ...(mock.headers ?? {}) });
  }
  return send(res, 404, { error: { code: 'not_found' } }, cors);
}

createServer((req, res) => {
  void (async () => {
    const url = new URL(req.url ?? '/', `http://localhost:${port}`);
    const m = /^\/s\/([\w-]+)(\/.*)$/.exec(url.pathname);
    if (m) return api(req, res, m[1] as string, m[2] as string);
    const path = url.pathname === '/' ? '/fixtures/index.html' : url.pathname;
    const file = normalize(join(root, path));
    if (!file.startsWith(root) || !(path.startsWith('/fixtures/') || path.startsWith('/dist/'))) return send(res, 404, {});
    try {
      const data = await readFile(file);
      res.writeHead(200, { 'Content-Type': TYPES[extname(file)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
      res.end(data);
    } catch {
      send(res, 404, {});
    }
  })().catch((error: unknown) => {
    console.error(error);
    if (!res.headersSent) send(res, 500, {});
  });
}).listen(port, () => console.log(`mock server on http://localhost:${port}`));
