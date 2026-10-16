import type { FeedbackEventName, FeedbackEvents } from './types';

type Handler = (...args: never[]) => unknown;

export class Emitter {
  private handlers = new Map<FeedbackEventName, Set<Handler>>();

  on<E extends FeedbackEventName>(name: E, fn: FeedbackEvents[E]): () => void {
    let set = this.handlers.get(name);
    if (!set) this.handlers.set(name, (set = new Set()));
    set.add(fn as Handler);
    return () => this.off(name, fn);
  }

  off<E extends FeedbackEventName>(name: E, fn: FeedbackEvents[E]): void {
    this.handlers.get(name)?.delete(fn as Handler);
  }

  /** Calls every handler. Returns false if one of them returned false. Handler errors are isolated. */
  emit<E extends FeedbackEventName>(name: E, ...args: Parameters<FeedbackEvents[E]>): boolean {
    let result = true;
    for (const fn of this.handlers.get(name) ?? []) {
      try {
        if ((fn as (...a: unknown[]) => unknown)(...args) === false) result = false;
      } catch (error) {
        console.warn('[codebar feedback] event handler failed', error);
      }
    }
    return result;
  }

  clear(): void {
    this.handlers.clear();
  }
}
