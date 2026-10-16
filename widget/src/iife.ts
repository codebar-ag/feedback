/**
 * Script tag build: window.CodebarFeedback.init({...}). The screenshot chunk
 * is loaded relative to this script's URL.
 */
import { initWith } from './init.js';
import type { FeedbackInstance, FeedbackOptions } from './types.js';
import { VERSION } from './version.js';

const base = (document.currentScript as HTMLScriptElement | null)?.src || location.href;

export function init(options: FeedbackOptions): Promise<FeedbackInstance> {
  return initWith(options, () => import(/* @vite-ignore */ new URL(__CAPTURE_CHUNK__, base).href));
}

export const initFeedback = init;
export const version: string = VERSION;
