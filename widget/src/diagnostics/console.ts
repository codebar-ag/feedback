import type { ConsoleLevel } from '../types.js';
import type { RingBuffer } from './buffer.js';
import { redact, stripQuery } from './redact.js';

type Off = () => void;

function stringify(arg: unknown): string {
  if (typeof arg === 'string') return arg;
  if (arg instanceof Error) return `${arg.name}: ${arg.message}`;
  try {
    return JSON.stringify(arg) ?? String(arg);
  } catch {
    return String(arg);
  }
}

function stackOf(args: unknown[]): string | undefined {
  const error = args.find((a): a is Error => a instanceof Error);
  return error?.stack;
}

export interface ConsoleCaptureOptions {
  levels: ConsoleLevel[];
  redact: RegExp[];
}

/**
 * Records console calls, uncaught errors, unhandled rejections, failed
 * resources and CSP violations. The original console methods are always
 * called first and unchanged. Installing twice is a no-op, the returned
 * function restores everything.
 */
let installed = false;

export function captureConsole(buffer: RingBuffer, options: ConsoleCaptureOptions): Off {
  if (installed) return () => {};
  installed = true;
  const offs: Off[] = [];
  const add = (type: string, message: string, extra: Partial<Parameters<RingBuffer['push']>[0]> = {}): void => {
    try {
      buffer.push({
        type,
        message: redact(message, options.redact),
        ...extra,
        ...(extra.stack ? { stack: redact(extra.stack, options.redact) } : {}),
        ...(extra.source ? { source: stripQuery(extra.source) } : {}),
        count: 1,
        time: Date.now(),
      });
    } catch {
      // Diagnostics must never break the host page.
    }
  };

  const target = console as unknown as Record<string, (...args: unknown[]) => void>;
  for (const level of options.levels) {
    const original = target[level];
    if (typeof original !== 'function') continue;
    const wrapped = function (this: unknown, ...args: unknown[]): void {
      original.apply(this, args);
      add(level, args.map(stringify).join(' '), { stack: stackOf(args) });
    };
    target[level] = wrapped;
    offs.push(() => {
      if (target[level] === wrapped) target[level] = original;
    });
  }

  const onError = (event: Event): void => {
    const el = event.target;
    if (el instanceof HTMLElement && el !== (document.documentElement as HTMLElement)) {
      const url = (el as HTMLImageElement).currentSrc || el.getAttribute('src') || el.getAttribute('href') || '';
      add('resource', `Failed to load <${el.tagName.toLowerCase()}> ${stripQuery(url)}`);
      return;
    }
    if (event instanceof ErrorEvent) {
      add('error', event.message || 'Script error', {
        source: event.filename || undefined,
        line: event.lineno || undefined,
        column: event.colno || undefined,
        stack: event.error instanceof Error ? event.error.stack : undefined,
      });
    }
  };
  const onRejection = (event: PromiseRejectionEvent): void => {
    const reason: unknown = event.reason;
    add('unhandledrejection', `Unhandled rejection: ${stringify(reason)}`, {
      stack: reason instanceof Error ? reason.stack : undefined,
    });
  };
  const onCsp = (event: SecurityPolicyViolationEvent): void => {
    add('csp', `CSP violation: ${event.violatedDirective} ${stripQuery(event.blockedURI || '')}`.trim(), {
      source: event.sourceFile || undefined,
      line: event.lineNumber || undefined,
    });
  };

  window.addEventListener('error', onError, true);
  window.addEventListener('unhandledrejection', onRejection);
  document.addEventListener('securitypolicyviolation', onCsp);
  offs.push(() => {
    window.removeEventListener('error', onError, true);
    window.removeEventListener('unhandledrejection', onRejection);
    document.removeEventListener('securitypolicyviolation', onCsp);
  });

  return () => {
    for (const off of offs.reverse()) off();
    installed = false;
  };
}
