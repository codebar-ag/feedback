import { describe, expect, it } from 'vitest';
import { REDACTED, redact, stripQuery } from '../../src/diagnostics/redact';

describe('redact', () => {
  it.each([
    ['Authorization: Bearer abcdef123456.xyz', 'Authorization: Bearer [redacted]'],
    ['token eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.c2lnbmF0dXJl here', 'token [redacted] here'],
    ['login?password=hunter2&user=a', 'login?password=[redacted]&user=a'],
    ['{"token":"abc123"}', '{"token":"[redacted]"}'],
    ['secret: s3cr3t', 'secret: [redacted]'],
    ['apikey=AKIA12345', 'apikey=[redacted]'],
    ['api_key=AKIA12345', 'api_key=[redacted]'],
    ['mail anna.muster@beispiel.ch failed', 'mail [redacted] failed'],
    ['card 4111 1111 1111 1111 declined', 'card [redacted] declined'],
    ['hash 0123456789abcdef0123456789abcdef01', 'hash [redacted]'],
    ['blob QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVphYmNkZWZnaGlqa2xtbm9w', 'blob [redacted]'],
  ])('redacts %s', (input, expected) => {
    expect(redact(input)).toBe(expected);
  });

  it('keeps harmless text', () => {
    expect(redact("TypeError: Cannot read properties of undefined (reading 'map') at app.js:412:18")).toBe(
      "TypeError: Cannot read properties of undefined (reading 'map') at app.js:412:18",
    );
    expect(redact('GET /api/items 500 (312 ms)')).toBe('GET /api/items 500 (312 ms)');
  });

  it('applies custom rules', () => {
    expect(redact('customer CH-12345 failed', [/CH-\d+/])).toBe(`customer ${REDACTED} failed`);
    expect(redact('a X1 b X2', [/X\d/g])).toBe(`a ${REDACTED} b ${REDACTED}`);
  });

  it('strips query strings and fragments', () => {
    expect(stripQuery('https://api.beispiel.ch/items?token=abc#x')).toBe('https://api.beispiel.ch/items');
    expect(stripQuery('/relative?x=1')).toBe(`${location.origin}/relative`);
    expect(stripQuery('http://[invalid')).toBe('http://[invalid');
  });
});
