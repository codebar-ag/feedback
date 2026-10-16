type Attrs = Record<string, string | number | boolean | null | undefined>;
type Child = Node | string | null | undefined | false;

/** Creates an element. Text children are always inserted as text, never as HTML. */
export function h<K extends keyof HTMLElementTagNameMap>(tag: K, attrs?: Attrs | null, ...children: Child[]): HTMLElementTagNameMap[K] {
  const el = document.createElement(tag);
  setAttrs(el, attrs);
  for (const c of children) if (c || c === '') el.append(c);
  return el;
}

export function setAttrs(el: Element, attrs?: Attrs | null): void {
  if (!attrs) return;
  for (const [k, v] of Object.entries(attrs)) {
    if (v === false || v === null || v === undefined) el.removeAttribute(k);
    else el.setAttribute(k, v === true ? '' : String(v));
  }
}

export function text(el: Element, value: string): void {
  if (el.textContent !== value) el.textContent = value;
}

export const raf = (): Promise<void> => new Promise((resolve) => requestAnimationFrame(() => resolve()));
