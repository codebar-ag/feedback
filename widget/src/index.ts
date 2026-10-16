import { initWith } from './init';
import type { FeedbackInstance, FeedbackOptions } from './types';
import { VERSION } from './version';

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
} from './types';
export type { Messages } from './i18n/index';

/** Initializes the feedback widget. Call it as early as possible (diagnostics start at init). */
export function initFeedback(options: FeedbackOptions): Promise<FeedbackInstance> {
  return initWith(options, () => import('./capture/snapshot'));
}

export const version: string = VERSION;
