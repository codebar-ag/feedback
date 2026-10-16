/**
 * Redaction before anything leaves the browser (concept 4.7). Replaces
 * tokens, JWTs, secrets in key=value pairs, email addresses, card-like
 * numbers and long hex/base64 strings with [redacted].
 */
export const REDACTED = '[redacted]';

const RULES: [RegExp, string][] = [
  [/\b(Bearer|Basic|Token)\s+[A-Za-z0-9._~+/=-]{6,}/gi, `$1 ${REDACTED}`],
  [/\beyJ[A-Za-z0-9_-]{4,}\.[A-Za-z0-9_-]{4,}\.[A-Za-z0-9_-]*/g, REDACTED],
  [
    /\b((?:password|passwd|pwd|pass|token|secret|api[_-]?key|access[_-]?token|refresh[_-]?token|client[_-]?secret|session|authorization|auth)["']?\s*[=:]\s*["']?)(?!Bearer\b|Basic\b|Token\b|\[redacted\])[^\s&"',;}]+/gi,
    `$1${REDACTED}`,
  ],
  [/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, REDACTED],
  [/\b(?:\d[ -]?){12,18}\d\b/g, REDACTED],
  [/\b[0-9a-f]{32,}\b/gi, REDACTED],
  [/[A-Za-z0-9+/_-]{40,}={0,2}/g, REDACTED],
];

function global(re: RegExp): RegExp {
  return re.flags.includes('g') ? re : new RegExp(re.source, re.flags + 'g');
}

export function redact(value: string, extra: RegExp[] = []): string {
  let out = value;
  for (const [re, by] of RULES) out = out.replace(re, by);
  for (const re of extra) out = out.replace(global(re), REDACTED);
  return out;
}

/** URL without query string and fragment (they may carry tokens). */
export function stripQuery(url: string): string {
  try {
    const u = new URL(url, location.href);
    return u.origin + u.pathname;
  } catch {
    return String(url).split(/[?#]/)[0] ?? '';
  }
}
