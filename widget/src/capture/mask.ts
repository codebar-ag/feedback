export interface Rect {
  x: number;
  y: number;
  w: number;
  h: number;
}

/** Opaque block painted over masked elements. */
export const MASK_COLOR = '#C9D1DC';

/** Elements that are always masked: password fields and card data. */
export const ALWAYS_MASKED = ['input[type="password"]', '[autocomplete^="cc-"]'];

/** Document rectangles of all masked elements (concept 4.8). */
export function maskRects(selectors: string[], root: ParentNode = document): Rect[] {
  const rects: Rect[] = [];
  const elements = new Set<Element>();
  for (const selector of [...ALWAYS_MASKED, ...selectors]) {
    try {
      for (const el of Array.from(root.querySelectorAll(selector))) elements.add(el);
    } catch {
      // Invalid selector from the options: skip it.
    }
  }
  for (const el of elements) {
    const r = el.getBoundingClientRect();
    if (r.width <= 0 || r.height <= 0) continue;
    rects.push({ x: r.left + window.scrollX, y: r.top + window.scrollY, w: r.width, h: r.height });
  }
  return rects;
}

export function paintMasks(ctx: CanvasRenderingContext2D, rects: Rect[], scale: number, color: string): void {
  ctx.save();
  ctx.fillStyle = color;
  for (const r of rects) ctx.fillRect(Math.floor(r.x * scale), Math.floor(r.y * scale), Math.ceil(r.w * scale), Math.ceil(r.h * scale));
  ctx.restore();
}

/** Largest scale (1, 0.75, 0.5, then fitted down to 0.25) within the pixel budget. */
export function pickScale(w: number, h: number, maxPixels: number): number | null {
  for (const s of [1, 0.75, 0.5]) if (w * s * (h * s) <= maxPixels) return s;
  const fit = Math.floor(Math.sqrt(maxPixels / (w * h)) * 100) / 100;
  return fit >= 0.25 ? fit : null;
}
