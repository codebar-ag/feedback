export interface LogEntry {
  type: string;
  message: string;
  source?: string;
  line?: number;
  column?: number;
  stack?: string;
  count: number;
  time: number;
}

const encoder = new TextEncoder();
export const bytes = (value: unknown): number => encoder.encode(JSON.stringify(value)).length;

function cut(value: string, max: number): string {
  return value.length > max ? value.slice(0, Math.max(0, max - 1)) + '…' : value;
}

/**
 * Ring buffer for diagnostics: keeps the newest entries, every entry at most
 * entryBytes, all together at most totalBytes. Identical consecutive
 * entries are counted instead of repeated.
 */
export class RingBuffer<T extends { count: number; time: number } = LogEntry> {
  private items: T[] = [];

  constructor(
    private readonly max = 50,
    private readonly entryBytes = 1024,
    private readonly totalBytes = 32 * 1024,
    private readonly key: (entry: T) => string = (e) => JSON.stringify({ ...e, count: 0, time: 0 }),
  ) {}

  push(entry: T): void {
    const fitted = this.fit(entry);
    const last = this.items[this.items.length - 1];
    if (last && this.key(last) === this.key(fitted)) {
      last.count += 1;
      last.time = fitted.time;
      return;
    }
    this.items.push(fitted);
    if (this.items.length > this.max) this.items.shift();
    while (this.items.length > 1 && bytes(this.items) > this.totalBytes) this.items.shift();
  }

  entries(): T[] {
    return this.items.map((e) => ({ ...e }));
  }

  clear(): void {
    this.items = [];
  }

  private fit(entry: T): T {
    const e: Record<string, unknown> = { ...entry };
    for (const field of ['stack', 'source', 'message']) {
      const overflow = bytes(e) - this.entryBytes;
      const value = e[field];
      if (overflow <= 0) break;
      if (typeof value !== 'string') continue;
      e[field] = cut(value, Math.max(16, value.length - overflow - 8));
      if (field === 'stack' && bytes(e) > this.entryBytes) delete e.stack;
    }
    while (bytes(e) > this.entryBytes && typeof e.message === 'string' && e.message.length > 1) {
      e.message = cut(e.message, Math.floor(e.message.length / 2));
    }
    return e as T;
  }
}
