import { initWith } from './init.js';
import type { FeedbackInstance, FeedbackOptions } from './types.js';
import { VERSION } from './version.js';

export type {
  ConsoleLevel,
  FeedbackError,
  FeedbackEventName,
  FeedbackEvents,
  FeedbackInstance,
  FeedbackOptions,
  FeedbackPayload,
  Identity,
  Locale,
  Mark,
  MarkerMark,
  RectMark,
  ScreenshotMode,
} from './types.js';
export type { Messages } from './i18n/index.js';

/** Initializes the feedback widget. Call it as early as possible (diagnostics start at init). */
export function initFeedback(options: FeedbackOptions): Promise<FeedbackInstance> {
  return initWith(options, () => import('./capture/snapshot.js'));
}

export const version: string = VERSION;
