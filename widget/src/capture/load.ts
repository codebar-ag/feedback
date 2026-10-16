import type * as Snapshot from './snapshot.js';

export type CaptureModule = typeof Snapshot;
export type CaptureLoader = () => Promise<CaptureModule>;

let loader: CaptureLoader | null = null;
let pending: Promise<CaptureModule> | null = null;

/** The entry point decides how the lazy chunk is loaded (bundler import or URL for the IIFE). */
export function setCaptureLoader(fn: CaptureLoader): void {
  loader = fn;
  pending = null;
}

export function loadCapture(): Promise<CaptureModule> {
  if (!loader) return Promise.reject(Object.assign(new Error('unsupported'), { code: 'unsupported' }));
  pending ??= loader().catch((error: unknown) => {
    pending = null;
    throw Object.assign(new Error('chunk failed'), { code: 'render_failed', cause: error });
  });
  return pending;
}

/** Called on first hover/focus of the launcher. */
export function preloadCapture(): void {
  loadCapture().catch(() => {});
}
