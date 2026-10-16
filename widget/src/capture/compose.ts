import { drawMarks } from '../annotate/tools';
import type { Mark } from '../types';

async function decode(blob: Blob): Promise<CanvasImageSource & { width: number; height: number }> {
  if (typeof createImageBitmap === 'function') return createImageBitmap(blob);
  const url = URL.createObjectURL(blob);
  try {
    const img = new Image();
    img.src = url;
    await img.decode();
    return img;
  } finally {
    URL.revokeObjectURL(url);
  }
}

function encode(canvas: HTMLCanvasElement, quality: number): Promise<Blob> {
  return new Promise((resolve, reject) =>
    canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('encode failed'))), 'image/jpeg', quality),
  );
}

/**
 * Burns the marks into the screenshot (only when sending, the draft keeps
 * the clean image). factor < 1 additionally downsizes (413 retry).
 */
export async function composeShot(blob: Blob, marks: Mark[], scale: number, quality: number, factor = 1): Promise<Blob> {
  if (marks.length === 0 && factor === 1) return blob;
  const image = await decode(blob);
  const canvas = document.createElement('canvas');
  canvas.width = Math.max(1, Math.round(image.width * factor));
  canvas.height = Math.max(1, Math.round(image.height * factor));
  const ctx = canvas.getContext('2d');
  if (!ctx) return blob;
  ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
  drawMarks(ctx, marks, scale * factor);
  return encode(canvas, quality);
}
