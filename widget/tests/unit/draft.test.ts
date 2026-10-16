import { describe, expect, it, vi } from 'vitest';
import { DraftStore, uuid } from '../../src/draft/storage';
import { ShotDb } from '../../src/draft/idb';

const meta = {
  w: 1536,
  h: 4210,
  scale: 1,
  url: 'https://a.ch/seite',
  title: 'Seite',
  annotations: [{ type: 'rect' as const, x: 60, y: 112, w: 300, h: 150 }],
};
const base = { open: true, email: 'anna@beispiel.ch', message: 'Die Schaltfläche …', shot: null, idempotencyKey: '7d2b' };
let n = 0;
const key = () => `fbk_live_test${++n}`;

describe('draft', () => {
  it('saves text and state in sessionStorage in the documented shape', async () => {
    const k = key();
    const store = new DraftStore(k, true, 24 * 3_600_000);
    store.save(base);
    const raw = JSON.parse(sessionStorage.getItem(`cbf:${k}:draft`)!);
    expect(raw).toMatchObject({
      v: 1,
      open: true,
      email: 'anna@beispiel.ch',
      message: 'Die Schaltfläche …',
      shot: null,
      idempotencyKey: '7d2b',
    });
    expect(typeof raw.savedAt).toBe('number');
    expect((await store.load())!.draft).toMatchObject(base);
  });

  it('stores the screenshot in IndexedDB and restores it with the marks', async () => {
    const k = key();
    const store = new DraftStore(k, true, 3_600_000);
    const blob = new Blob(['jpeg'], { type: 'image/jpeg' });
    expect(await store.saveShot(blob)).toBe(true);
    store.save({ ...base, shot: meta });

    const loaded = await new DraftStore(k, true, 3_600_000).load();
    expect(loaded!.draft.shot).toEqual(meta);
    expect(loaded!.blob).toBeInstanceOf(Blob);
    expect(await loaded!.blob!.text()).toBe('jpeg');
    expect(sessionStorage.getItem(`cbf:${k}:tab`)).toMatch(/^[0-9a-f-]{36}$/);
  });

  it('drops the shot metadata when the image is missing', async () => {
    const k = key();
    const store = new DraftStore(k, true, 3_600_000);
    store.save({ ...base, shot: meta });
    expect((await store.load())!.draft.shot).toBeNull();
  });

  it('expires after the TTL', async () => {
    const k = key();
    const store = new DraftStore(k, true, 1000);
    store.save(base);
    vi.spyOn(Date, 'now').mockReturnValue(Date.now() + 2000);
    expect(await store.load()).toBeNull();
    expect(sessionStorage.getItem(`cbf:${k}:draft`)).toBeNull();
  });

  it('clears text and screenshot together', async () => {
    const k = key();
    const store = new DraftStore(k, true, 3_600_000);
    await store.saveShot(new Blob(['x']));
    store.save({ ...base, shot: meta });
    await store.clear();
    expect(await store.load()).toBeNull();
    expect(await new ShotDb(`cbf-${k}`).get(sessionStorage.getItem(`cbf:${k}:tab`)!)).toBeNull();
  });

  it('removes only the screenshot', async () => {
    const k = key();
    const store = new DraftStore(k, true, 3_600_000);
    await store.saveShot(new Blob(['x']));
    await store.clearShot();
    store.save({ ...base, shot: meta });
    const loaded = await store.load();
    expect(loaded!.draft.message).toBe(base.message);
    expect(loaded!.draft.shot).toBeNull();
  });

  it('works without storage (private mode)', async () => {
    const get = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('denied', 'SecurityError');
    });
    const set = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('quota', 'QuotaExceededError');
    });
    const open = vi.spyOn(indexedDB, 'open').mockImplementation(() => {
      throw new DOMException('denied', 'SecurityError');
    });
    const store = new DraftStore(key(), true, 3_600_000);
    expect(() => store.save(base)).not.toThrow();
    expect(await store.saveShot(new Blob(['x']))).toBe(false);
    expect(await store.load()).toBeNull();
    await expect(store.clear()).resolves.toBeUndefined();
    expect(get).toHaveBeenCalled();
    expect(set).toHaveBeenCalled();
    expect(open).toHaveBeenCalled();
  });

  it('keeps the draft in memory only with persist: false', async () => {
    const k = key();
    const store = new DraftStore(k, false, 3_600_000);
    store.save(base);
    await store.saveShot(new Blob(['m']));
    expect(sessionStorage.getItem(`cbf:${k}:draft`)).toBeNull();
    const loaded = await store.load();
    expect(loaded!.draft.message).toBe(base.message);
    expect(loaded!.blob).toBeInstanceOf(Blob);
    await store.clearShot();
    expect((await store.load())!.blob).toBeNull();
    await store.clear();
    expect(await store.load()).toBeNull();
  });

  it('creates RFC 4122 v4 ids', () => {
    expect(uuid()).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    const random = vi.spyOn(crypto, 'randomUUID').mockImplementation(undefined as never);
    Object.defineProperty(crypto, 'randomUUID', { value: undefined, configurable: true });
    expect(uuid()).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    random.mockRestore();
  });
});
