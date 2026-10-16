/**
 * Project color → tokens. Mirrors app/Support/ProjectColor.php,
 * both are tested against the same contrast values.
 */
export const WHITE = '#FFFFFF';
export const BLACK = '#000000';
export const INK = '#101F3C';
const MIN = 4.5;

export interface Theme {
  primary: string;
  onPrimary: string;
  contrast: number;
  strong: string;
  onStrong: string;
  strongContrast: number;
  divider: boolean;
}

export function parseColor(input: string | null | undefined): string | null {
  let v = String(input ?? '')
    .trim()
    .toLowerCase();
  if (v === 'black') return BLACK;
  if (v === 'white') return WHITE;
  v = v.replace(/^#/, '');
  if (/^[0-9a-f]{3}$/.test(v)) v = v.replace(/./g, '$&$&');
  return /^[0-9a-f]{6}$/.test(v) ? '#' + v.toUpperCase() : null;
}

function rgb(hex: string): [number, number, number] {
  const n = parseInt(hex.slice(1), 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

export function luminance(hex: string): number {
  const [r, g, b] = rgb(hex).map((c) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  }) as [number, number, number];
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrast(a: string, b: string): number {
  const x = luminance(a);
  const y = luminance(b);
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

export function textOn(hex: string): string {
  return contrast(WHITE, hex) >= contrast(INK, hex) ? WHITE : INK;
}

function mix(from: string, to: string, percent: number): string {
  const a = rgb(from);
  const b = rgb(to);
  return (
    '#' +
    a
      .map((v, i) =>
        Math.max(0, Math.min(255, Math.round(v + ((b[i] as number) - v) * (percent / 100))))
          .toString(16)
          .padStart(2, '0'),
      )
      .join('')
      .toUpperCase()
  );
}

const round2 = (n: number): number => Math.round(n * 100) / 100;

export function deriveTheme(hex: string): Theme {
  const primary = hex.toUpperCase();
  const onPrimary = textOn(primary);
  let strong = primary;
  let onStrong = onPrimary;
  let step = 0;
  while (contrast(onStrong, strong) < MIN && step < 100) {
    step += 2;
    strong = mix(primary, onStrong === WHITE ? BLACK : WHITE, step);
    onStrong = textOn(strong);
  }
  const divider = luminance(primary) > 0.85;
  if (divider) {
    strong = INK;
    onStrong = WHITE;
  }
  return {
    primary,
    onPrimary,
    contrast: round2(contrast(onPrimary, primary)),
    strong,
    onStrong,
    strongContrast: round2(contrast(onStrong, strong)),
    divider,
  };
}

/** CSS custom properties for the widget root. */
export function themeVars(t: Theme): Record<string, string> {
  return {
    '--fb-primary': t.primary,
    '--fb-on-primary': t.onPrimary,
    '--fb-strong': t.strong,
    '--fb-on-strong': t.onStrong,
    '--fb-head-line': t.divider ? 'var(--fb-border)' : 'transparent',
    '--fb-ring': t.divider ? '0 0 0 1px var(--fb-border)' : '0 0 0 0 transparent',
  };
}
