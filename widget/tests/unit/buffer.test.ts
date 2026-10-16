import { describe, expect, it } from 'vitest';
import { RingBuffer, bytes, type LogEntry } from '../../src/diagnostics/buffer';

const entry = (message: string, extra: Partial<LogEntry> = {}): LogEntry => ({ type: 'error', message, count: 1, time: 1, ...extra });

describe('RingBuffer', () => {
  it('keeps the last 50 entries', () => {
    const b = new RingBuffer();
    for (let i = 0; i < 60; i++) b.push(entry(`e${i}`));
    const items = b.entries();
    expect(items).toHaveLength(50);
    expect(items[0]!.message).toBe('e10');
    expect(items[49]!.message).toBe('e59');
  });

  it('limits every entry to 1 KB', () => {
    const b = new RingBuffer();
    b.push(entry('x'.repeat(5000), { stack: 'y'.repeat(5000), source: 'https://a.ch/' + 'z'.repeat(3000) }));
    expect(bytes(b.entries()[0])).toBeLessThanOrEqual(1024);
    expect(b.entries()[0]!.message.endsWith('…')).toBe(true);
  });

  it('limits the total to 32 KB', () => {
    const b = new RingBuffer();
    for (let i = 0; i < 50; i++) b.push(entry(`${i} ` + 'x'.repeat(900)));
    expect(bytes(b.entries())).toBeLessThanOrEqual(32 * 1024);
    expect(b.entries().length).toBeLessThan(50);
    expect(b.entries().at(-1)!.message.startsWith('49 ')).toBe(true);
  });

  it('counts identical consecutive entries', () => {
    const b = new RingBuffer();
    b.push(entry('same', { time: 1 }));
    b.push(entry('same', { time: 2 }));
    b.push(entry('same', { time: 3 }));
    b.push(entry('other'));
    b.push(entry('same'));
    expect(b.entries().map((e) => [e.message, e.count])).toEqual([
      ['same', 3],
      ['other', 1],
      ['same', 1],
    ]);
    expect(b.entries()[0]!.time).toBe(3);
  });

  it('respects a smaller maximum and can be cleared', () => {
    const b = new RingBuffer(3);
    for (let i = 0; i < 5; i++) b.push(entry(`e${i}`));
    expect(b.entries().map((e) => e.message)).toEqual(['e2', 'e3', 'e4']);
    b.clear();
    expect(b.entries()).toEqual([]);
  });
});
