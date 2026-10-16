import { describe, expect, it } from 'vitest';
import { contrast, deriveTheme, parseColor, textOn, themeVars } from '../../src/theme/derive';
import { TOKENS } from '../../src/ui/tokens.css.js';

describe('theme', () => {
  // Same values as tests/Unit/ProjectColorTest.php.
  it.each([
    ['black', '#000000', '#FFFFFF', 21],
    ['white', '#FFFFFF', '#101F3C', 16.36],
    ['#ED813A', '#ED813A', '#101F3C', 6.08],
    ['#0A7C66', '#0A7C66', '#FFFFFF', 5.14],
  ])('derives %s', (input, primary, on, ratio) => {
    const t = deriveTheme(parseColor(input)!);
    expect(t.primary).toBe(primary);
    expect(t.onPrimary).toBe(on);
    expect(t.contrast).toBe(ratio);
  });

  it('rejects white on orange (2.69:1)', () => {
    expect(Math.round(contrast('#FFFFFF', '#ED813A') * 100) / 100).toBe(2.69);
    expect(textOn('#ED813A')).toBe('#101F3C');
  });

  it('gives white a divider and a dark button', () => {
    const t = deriveTheme('#FFFFFF');
    expect(t.divider).toBe(true);
    expect(t.strong).toBe('#101F3C');
    expect(themeVars(t)['--fb-head-line']).toBe('var(--fb-border)');
    expect(themeVars(deriveTheme('#000000'))['--fb-head-line']).toBe('transparent');
  });

  it.each(['#808080', '#E0218A', '#2F80ED'])('darkens or lightens mid tone %s to 4.5:1', (hex) => {
    const t = deriveTheme(hex);
    expect(t.strongContrast).toBeGreaterThanOrEqual(4.5);
    expect(t.strong).not.toBe(hex);
  });

  it.each([
    ['#0a7c66', '#0A7C66'],
    ['0A7C66', '#0A7C66'],
    ['#abc', '#AABBCC'],
    [' WHITE ', '#FFFFFF'],
    ['#12345', null],
    ['red', null],
    [null, null],
  ])('parses %s', (input, expected) => {
    expect(parseColor(input)).toBe(expected);
  });

  it('keeps the fixed tokens with their contrasts on white', () => {
    expect(TOKENS).toMatchObject({
      '--fb-ink': '#101F3C',
      '--fb-ink-muted': '#5B6B85',
      '--fb-annotation': '#E02B20',
      '--fb-focus': '#1D4ED8',
      '--fb-danger': '#B42318',
      '--fb-success': '#157F3B',
    });
    const r = (a: string): number => Math.round(contrast(a, '#FFFFFF') * 100) / 100;
    expect(r(TOKENS['--fb-ink'])).toBe(16.36);
    expect(r(TOKENS['--fb-ink-muted'])).toBe(5.4);
    expect(r(TOKENS['--fb-annotation'])).toBe(4.63);
    expect(r(TOKENS['--fb-focus'])).toBe(6.7);
    expect(r(TOKENS['--fb-danger'])).toBe(6.57);
    expect(r(TOKENS['--fb-success'])).toBe(5.08);
  });
});
