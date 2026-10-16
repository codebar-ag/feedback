import { h } from '../ui/dom.js';
import type { Mark, ShotMeta } from '../types.js';
import type { History } from './history.js';
import { drawMarks, extendMark, finishMark, startMark, type Tool } from './tools.js';

/**
 * Annotation surface: the screenshot is shown in
 * document coordinates (it scrolls with the page), a viewport sized canvas
 * draws the marks. The mouse wheel scrolls the page, two fingers pan on
 * touch devices, one pointer draws.
 */
export class AnnotationLayer {
  readonly el: HTMLDivElement;
  private readonly image: HTMLDivElement;
  private readonly canvas: HTMLCanvasElement;
  private readonly ctx: CanvasRenderingContext2D | null;
  private current: Mark | null = null;
  private pointers = new Map<number, { x: number; y: number }>();
  private readonly offs: (() => void)[] = [];
  tool: Tool = 'rect';

  constructor(
    meta: ShotMeta,
    imageUrl: string,
    private readonly history: History,
    private readonly onChange: () => void,
    label: string,
  ) {
    const img = h('img', { src: imageUrl, alt: '', draggable: 'false' });
    this.image = h('div', { class: 'an' }, img);
    this.image.style.width = `${meta.w}px`;
    this.image.style.height = `${meta.h}px`;
    this.canvas = h('canvas', { class: 'cv', role: 'img', 'aria-label': label });
    this.ctx = this.canvas.getContext('2d');
    this.el = h('div', { class: 'ly' }, this.image, h('div', { class: 'sc' }), this.canvas);

    const c = this.canvas;
    const on = <K extends keyof HTMLElementEventMap>(
      t: EventTarget,
      type: K | string,
      fn: (e: never) => void,
      opts?: AddEventListenerOptions,
    ): void => {
      t.addEventListener(type, fn as EventListener, opts);
      this.offs.push(() => t.removeEventListener(type, fn as EventListener, opts));
    };
    on(c, 'pointerdown', (e: PointerEvent) => this.down(e));
    on(c, 'pointermove', (e: PointerEvent) => this.move(e));
    on(c, 'pointerup', (e: PointerEvent) => this.up(e));
    on(c, 'pointercancel', (e: PointerEvent) => this.cancel(e));
    on(window, 'scroll', () => this.redraw(), { passive: true });
    on(window, 'resize', () => this.resize());
    this.resize();
  }

  /** Document coordinates of a pointer event. */
  private point(e: PointerEvent): [number, number] {
    return [e.clientX + window.scrollX, e.clientY + window.scrollY];
  }

  private down(e: PointerEvent): void {
    if (e.button !== 0 && e.pointerType === 'mouse') return;
    this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    try {
      this.canvas.setPointerCapture(e.pointerId);
    } catch {
      // ignore
    }
    if (this.pointers.size > 1) {
      // Second finger: pan instead of drawing.
      this.current = null;
      this.redraw();
      return;
    }
    const [x, y] = this.point(e);
    this.current = startMark(this.tool, x, y);
    e.preventDefault();
  }

  private move(e: PointerEvent): void {
    const prev = this.pointers.get(e.pointerId);
    if (!prev) return;
    if (this.pointers.size > 1) {
      window.scrollBy(prev.x - e.clientX, prev.y - e.clientY);
      this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
      return;
    }
    this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (!this.current) return;
    const [x, y] = this.point(e);
    extendMark(this.current, x, y);
    this.redraw();
  }

  private up(e: PointerEvent): void {
    this.pointers.delete(e.pointerId);
    const mark = this.current && finishMark(this.current);
    this.current = null;
    if (mark) {
      this.history.add(mark);
      this.onChange();
    }
    this.redraw();
  }

  private cancel(e: PointerEvent): void {
    this.pointers.delete(e.pointerId);
    this.current = null;
    this.redraw();
  }

  resize(): void {
    const dpr = window.devicePixelRatio || 1;
    this.canvas.width = Math.round(window.innerWidth * dpr);
    this.canvas.height = Math.round(window.innerHeight * dpr);
    this.redraw();
  }

  redraw(): void {
    const ctx = this.ctx;
    if (!ctx) return;
    const dpr = window.devicePixelRatio || 1;
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
    const marks = this.current ? [...this.history.marks, this.current] : this.history.marks;
    drawMarks(ctx, marks, dpr, window.scrollX, window.scrollY);
  }

  destroy(): void {
    for (const off of this.offs) off();
    this.el.remove();
  }
}
