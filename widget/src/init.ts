import { setCaptureLoader, type CaptureLoader } from './capture/load';
import type { FeedbackInstance, FeedbackOptions } from './types';
import { Widget } from './widget';

let instance: Widget | null = null;
let ready: Promise<FeedbackInstance> | null = null;

/**
 * Creates the widget once. A second call returns the same instance
 * (singleton), destroy() allows a new init.
 */
export function initWith(options: FeedbackOptions, loader: CaptureLoader): Promise<FeedbackInstance> {
  if (ready) return ready;
  setCaptureLoader(loader);
  try {
    instance = new Widget(options, () => {
      instance = null;
      ready = null;
    });
  } catch (error) {
    console.error('[codebar feedback] init failed:', error);
    return Promise.reject(error instanceof Error ? error : new Error(String(error)));
  }
  const created = instance;
  ready = created.start().then(
    () => created,
    () => created,
  );
  return ready;
}
