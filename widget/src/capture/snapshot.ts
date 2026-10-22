/**
 * Lazy chunk: renders the full page with modern-screenshot. Never part of the core bundle.
 */
import { domToCanvas } from 'modern-screenshot';
import { MASK_COLOR, maskRects, paintMasks, pickScale } from './mask.js';

export interface CaptureOptions {
  exclude: Element[];
  ignore: string[];
  mask: string[];
  maxPixels: number;
  quality: number;
  timeoutMs?: number;
}

export interface CaptureResult {
  blob: Blob;
  w: number;
  h: number;
  scale: number;
}

export class CaptureError extends Error {
  constructor(public readonly code: 'render_failed' | 'canvas_limit' | 'timeout' | 'unsupported') {
    super(code);
  }
}

const MAX_BYTES = 3 * 1024 * 1024;

/** XML-safe attribute name without a namespace prefix. */
const XML_NAME = /^[A-Za-z_][\w.-]*$/;

/**
 * The clone is rendered as SVG, so it has to be valid XML. Framework attributes such as
 * Alpine's `:class`, `x-on:click` or Vue's `v-bind:href` are not, and a single one makes the
 * browser render the whole page as an empty image. They carry no styling, so drop them.
 */
export function stripInvalidAttributes(node: Node): void {
  if (!(node instanceof Element)) return;
  for (const attr of Array.from(node.attributes)) {
    if (attr.namespaceURI === null && !XML_NAME.test(attr.name)) node.removeAttribute(attr.name);
  }
}

export function toJpeg(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob((b) => (b ? resolve(b) : reject(new CaptureError('canvas_limit'))), 'image/jpeg', quality);
  });
}

function shrinkCanvas(source: HTMLCanvasElement, factor: number): HTMLCanvasElement {
  const c = document.createElement('canvas');
  c.width = Math.max(1, Math.round(source.width * factor));
  c.height = Math.max(1, Math.round(source.height * factor));
  c.getContext('2d')?.drawImage(source, 0, 0, c.width, c.height);
  return c;
}

export async function capturePage(options: CaptureOptions): Promise<CaptureResult> {
  const doc = document.documentElement;
  const body = document.body;
  const w = Math.max(doc.scrollWidth, body?.scrollWidth ?? 0, doc.clientWidth);
  const h = Math.max(doc.scrollHeight, body?.scrollHeight ?? 0, doc.clientHeight);
  const scale = pickScale(w, h, options.maxPixels);
  if (!scale) throw new CaptureError('canvas_limit');

  const rects = maskRects(options.mask);
  const ignore = options.ignore.join(',');
  const background = getComputedStyle(body ?? doc).backgroundColor;

  let timer: ReturnType<typeof setTimeout> | undefined;
  const timeout = new Promise<never>((_, reject) => {
    timer = setTimeout(() => reject(new CaptureError('timeout')), options.timeoutMs ?? 15_000);
  });

  let canvas: HTMLCanvasElement;
  try {
    canvas = await Promise.race([
      domToCanvas(doc, {
        width: w,
        height: h,
        scale,
        backgroundColor: background && background !== 'rgba(0, 0, 0, 0)' ? background : '#FFFFFF',
        filter: (node) => !(node instanceof Element && (options.exclude.includes(node) || (ignore !== '' && node.matches(ignore)))),
        onCloneEachNode: stripInvalidAttributes,
        timeout: options.timeoutMs ?? 15_000,
      }),
      timeout,
    ]);
  } catch (error) {
    throw error instanceof CaptureError ? error : new CaptureError('render_failed');
  } finally {
    clearTimeout(timer);
  }

  const ctx = canvas.getContext('2d');
  if (!ctx) throw new CaptureError('unsupported');
  paintMasks(ctx, rects, scale, MASK_COLOR);

  let blob = await toJpeg(canvas, options.quality);
  let finalScale = scale;
  if (blob.size > MAX_BYTES) blob = await toJpeg(canvas, 0.7);
  while (blob.size > MAX_BYTES && finalScale > 0.25) {
    canvas = shrinkCanvas(canvas, 0.75);
    finalScale *= 0.75;
    blob = await toJpeg(canvas, 0.7);
  }

  return { blob, w, h, scale: Math.round(finalScale * 10000) / 10000 };
}
