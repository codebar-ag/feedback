import { describe, expect, it } from 'vitest';
import { History } from '../../src/annotate/history';
import { drawMarks, extendMark, finishMark, startMark } from '../../src/annotate/tools';
import { maskRects, paintMasks, pickScale } from '../../src/capture/mask';
import type { Mark } from '../../src/types';

/** Records the drawing calls of a 2D context. */
function recorder() {
  const calls: [string, ...unknown[]][] = [];
  const ctx = new Proxy({ lineWidth: 0, strokeStyle: '', fillStyle: '', lineCap: '', lineJoin: '' } as Record<string, unknown>, {
    get(target, prop: string) {
      if (prop in target) return target[prop];
      return (...args: unknown[]) => calls.push([prop, ...args]);
    },
    set(target, prop: string, value) {
      target[prop] = value;
      calls.push([`set:${prop}`, value]);
      return true;
    },
  });
  return { ctx: ctx as unknown as CanvasRenderingContext2D, calls };
}

describe('tools', () => {
  it('normalizes rectangles drawn in any direction', () => {
    const r = startMark('rect', 300, 200);
    extendMark(r, 100, 50);
    expect(finishMark(r)).toEqual({ type: 'rect', x: 100, y: 50, w: 200, h: 150 });
  });

  it('drops rectangles below 6 × 6', () => {
    const r = startMark('rect', 10, 10);
    extendMark(r, 15, 100);
    expect(finishMark(r)).toBeNull();
    extendMark(r, 16, 16);
    expect(finishMark(r)).toEqual({ type: 'rect', x: 10, y: 10, w: 6, h: 6 });
  });

  it('needs at least 3 marker points and skips jitter', () => {
    const m = startMark('marker', 0, 0);
    extendMark(m, 0.5, 0.5);
    extendMark(m, 10, 0);
    expect(finishMark(m)).toBeNull();
    extendMark(m, 20, 5);
    expect(finishMark(m)).toEqual({
      type: 'marker',
      points: [
        [0, 0],
        [10, 0],
        [20, 5],
      ],
    });
  });
});

describe('history', () => {
  it('undoes, clears and undoes the clear', () => {
    const h = new History();
    const a: Mark = { type: 'rect', x: 0, y: 0, w: 10, h: 10 };
    const b: Mark = {
      type: 'marker',
      points: [
        [0, 0],
        [1, 1],
        [2, 2],
      ],
    };
    h.add(a);
    h.add(b);
    expect(h.canUndo).toBe(true);
    h.clear();
    expect(h.marks).toEqual([]);
    h.undo();
    expect(h.marks).toEqual([a, b]);
    h.undo();
    expect(h.marks).toEqual([a]);
    h.undo();
    expect(h.marks).toEqual([]);
    expect(h.canUndo).toBe(false);
    h.clear();
    expect(h.canUndo).toBe(false);
  });

  it('can remove restored marks one by one', () => {
    const h = new History([
      { type: 'rect', x: 0, y: 0, w: 10, h: 10 },
      { type: 'rect', x: 5, y: 5, w: 10, h: 10 },
    ]);
    h.undo();
    expect(h.marks).toHaveLength(1);
  });
});

describe('coordinates', () => {
  it('draws document coordinates relative to the scroll offset (live layer)', () => {
    const { ctx, calls } = recorder();
    drawMarks(ctx, [{ type: 'rect', x: 100, y: 1200, w: 50, h: 40 }], 2, 0, 1000);
    expect(calls).toContainEqual(['strokeRect', 200, 400, 100, 80]);
    expect(calls).toContainEqual(['set:lineWidth', 6]);
    expect(calls).toContainEqual(['set:strokeStyle', '#E02B20']);
  });

  it('burns marks with the screenshot scale', () => {
    const { ctx, calls } = recorder();
    drawMarks(
      ctx,
      [
        {
          type: 'marker',
          points: [
            [10, 20],
            [30, 40],
            [50, 60],
          ],
        },
      ],
      0.5,
    );
    expect(calls).toContainEqual(['moveTo', 5, 10]);
    expect(calls).toContainEqual(['lineTo', 25, 30]);
    expect(calls).toContainEqual(['set:lineWidth', 10]);
    expect(calls).toContainEqual(['set:strokeStyle', 'rgba(255,214,0,.5)']);
  });

  it('draws a single marker point as a dot', () => {
    const { ctx, calls } = recorder();
    drawMarks(ctx, [{ type: 'marker', points: [[10, 10]] }], 1);
    expect(calls.filter((c) => c[0] === 'lineTo')).toHaveLength(1);
  });
});

describe('screenshot budget and masks', () => {
  it.each([
    [1541, 6120, 25_000_000, 1],
    [1541, 17_000, 25_000_000, 0.75],
    [1541, 30_000, 25_000_000, 0.5],
    [1541, 60_000, 16_000_000, 0.41],
    [1541, 153_600, 16_000_000, 0.25],
    [3000, 200_000, 16_000_000, null],
  ])('picks the scale for %i × %i', (w, h, max, expected) => {
    expect(pickScale(w, h, max)).toBe(expected);
  });

  it('collects masked elements in document coordinates', () => {
    document.body.innerHTML = '<input type="password"><div data-feedback-mask>Kunde</div><input autocomplete="cc-number"><p>frei</p>';
    for (const el of Array.from(document.body.children)) {
      el.getBoundingClientRect = () => ({ left: 10, top: 20, width: 100, height: 30 }) as DOMRect;
    }
    (document.querySelector('p') as HTMLElement).getBoundingClientRect = () => ({ left: 0, top: 0, width: 0, height: 0 }) as DOMRect;
    window.scrollY = 500;
    const rects = maskRects(['[data-feedback-mask]', 'invalid[[']);
    expect(rects).toHaveLength(3);
    expect(rects[0]).toEqual({ x: 10, y: 520, w: 100, h: 30 });
    window.scrollY = 0;

    const { ctx, calls } = recorder();
    paintMasks(ctx, [{ x: 10.4, y: 20.6, w: 100, h: 30 }], 2, '#C9D1DC');
    expect(calls).toContainEqual(['fillRect', 20, 41, 200, 60]);
  });
});
