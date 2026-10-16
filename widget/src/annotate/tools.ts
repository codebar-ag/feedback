import { MARKER_COLOR, TOKENS } from '../ui/tokens.css';
import type { Mark, MarkerMark, RectMark } from '../types';

export type Tool = 'rect' | 'marker';

export const MIN_RECT = 6;
export const MIN_POINTS = 3;
export const RECT_WIDTH = 3;
export const MARKER_WIDTH = 20;

const round = (n: number): number => Math.round(n * 10) / 10;

export function startMark(tool: Tool, x: number, y: number): Mark {
  return tool === 'rect' ? { type: 'rect', x, y, w: 0, h: 0 } : { type: 'marker', points: [[x, y]] };
}

export function extendMark(mark: Mark, x: number, y: number): void {
  if (mark.type === 'rect') {
    mark.w = x - mark.x;
    mark.h = y - mark.y;
  } else {
    const last = mark.points[mark.points.length - 1];
    if (!last || Math.abs(last[0] - x) + Math.abs(last[1] - y) >= 2) mark.points.push([x, y]);
  }
}

/** Normalizes a finished mark, returns null when it is below the minimum size. */
export function finishMark(mark: Mark): Mark | null {
  if (mark.type === 'rect') {
    const r: RectMark = {
      type: 'rect',
      x: round(Math.min(mark.x, mark.x + mark.w)),
      y: round(Math.min(mark.y, mark.y + mark.h)),
      w: round(Math.abs(mark.w)),
      h: round(Math.abs(mark.h)),
    };
    return r.w >= MIN_RECT && r.h >= MIN_RECT ? r : null;
  }
  if (mark.points.length < MIN_POINTS) return null;
  const m: MarkerMark = { type: 'marker', points: mark.points.map(([x, y]) => [round(x), round(y)]) };
  return m;
}

/**
 * Draws marks given in document coordinates. scale converts to target
 * pixels, (ox, oy) is the document offset of the target's origin.
 */
export function drawMarks(ctx: CanvasRenderingContext2D, marks: Mark[], scale: number, ox = 0, oy = 0): void {
  ctx.save();
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
  for (const m of marks) {
    ctx.beginPath();
    if (m.type === 'rect') {
      const w = m.w ?? 0;
      const hgt = m.h ?? 0;
      ctx.lineWidth = RECT_WIDTH * scale;
      ctx.strokeStyle = TOKENS['--fb-annotation'];
      ctx.strokeRect((m.x - ox) * scale, (m.y - oy) * scale, w * scale, hgt * scale);
    } else {
      ctx.lineWidth = MARKER_WIDTH * scale;
      ctx.strokeStyle = MARKER_COLOR;
      m.points.forEach(([x, y], i) => {
        const px = (x - ox) * scale;
        const py = (y - oy) * scale;
        if (i === 0) ctx.moveTo(px, py);
        else ctx.lineTo(px, py);
      });
      if (m.points.length === 1) ctx.lineTo((m.points[0]![0] - ox) * scale + 0.01, (m.points[0]![1] - oy) * scale);
      ctx.stroke();
    }
  }
  ctx.restore();
}
