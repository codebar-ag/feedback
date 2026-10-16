/** Minimal IndexedDB wrapper. Every failure resolves to null/false, never throws. */
const STORE = 'shots';

function request<T>(req: IDBRequest<T>): Promise<T> {
  return new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

export class ShotDb {
  private db: Promise<IDBDatabase | null> | null = null;

  constructor(private readonly name: string) {}

  private open(): Promise<IDBDatabase | null> {
    this.db ??= new Promise<IDBDatabase | null>((resolve) => {
      try {
        const req = indexedDB.open(this.name, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(STORE);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => resolve(null);
        req.onblocked = () => resolve(null);
      } catch {
        resolve(null);
      }
    });
    return this.db;
  }

  private async run<T>(mode: IDBTransactionMode, fn: (store: IDBObjectStore) => IDBRequest<T>): Promise<T | null> {
    try {
      const db = await this.open();
      if (!db) return null;
      return await request(fn(db.transaction(STORE, mode).objectStore(STORE)));
    } catch {
      return null;
    }
  }

  async get<T>(key: string): Promise<T | null> {
    return ((await this.run('readonly', (s) => s.get(key))) as T | undefined) ?? null;
  }

  async put(key: string, value: unknown): Promise<boolean> {
    return (await this.run('readwrite', (s) => s.put(value, key))) !== null;
  }

  async delete(key: string): Promise<void> {
    await this.run('readwrite', (s) => s.delete(key));
  }

  close(): void {
    void this.db?.then((db) => db?.close());
    this.db = null;
  }
}
