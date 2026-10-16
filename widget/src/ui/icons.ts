const NS = 'http://www.w3.org/2000/svg';

type Shape = [tag: string, attrs: Record<string, string>];

const SPARKLE: Shape[] = [
  ['path', { d: 'M11 3l1.9 5.1L18 10l-5.1 1.9L11 17l-1.9-5.1L4 10l5.1-1.9z', fill: 'currentColor' }],
  ['path', { d: 'M19 3v4M17 5h4M5 18v3M3.5 19.5h3' }],
];

const TRASH: Shape[] = [['path', { d: 'M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13' }]];

export const ICONS = {
  sparkle: SPARKLE,
  close: [['path', { d: 'M6 6l12 12M18 6L6 18' }]],
  camera: [
    ['path', { d: 'M4 8h3l1.5-2h7L17 8h3v11H4z' }],
    ['circle', { cx: '12', cy: '13.5', r: '3.5' }],
  ],
  edit: [['path', { d: 'M4 20h6M14.5 5.5l4 4L9 19l-5 1 1-5z' }]],
  trash: TRASH,
  undo: [['path', { d: 'M9 14L4 9l5-5M4 9h10a6 6 0 010 12h-4' }]],
  rect: [['rect', { x: '4', y: '6', width: '16', height: '12', rx: '1.5' }]],
  marker: [['path', { d: 'M4 20h6M14.5 5.5l4 4L9 19l-5 1 1-5z' }]],
  clear: TRASH,
  check: [['path', { d: 'M5 12l5 5 9-10' }]],
  alert: [
    ['circle', { cx: '12', cy: '12', r: '9' }],
    ['path', { d: 'M12 7v6M12 16.5h.01' }],
  ],
} satisfies Record<string, Shape[]>;

export type IconName = keyof typeof ICONS;

/** Builds an inline SVG icon with DOM APIs (no innerHTML). */
export function icon(name: IconName): SVGSVGElement {
  const svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  for (const [tag, attrs] of ICONS[name]) {
    const el = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
    svg.append(el);
  }
  return svg;
}
