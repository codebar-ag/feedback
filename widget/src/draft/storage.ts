import type { ShotMeta } from '../types.js';
import { ShotDb } from './idb.js';

export interface Draft {
  v: 1;
  savedAt: number;
  open: boolean;
  email: string;
  message: string;
  shot: ShotMeta | null;
  idempotencyKey: string;
}

export interface LoadedDraft {
  draft: Draft;
  blob: Blob | null;
}

function session(): Storage | null {
  try {
    return window.sessionStorage;
  } catch {
    return null;
  }
}

export function uuid(): string {
  if (typeof crypto.randomUUID === 'function') return crypto.randomUUID();
  const b = crypto.getRandomValues(new Uint8Array(16));
  b[6] = ((b[6] as number) & 0x0f) | 0x40;
  b[8] = ((b[8] as number) & 0x3f) | 0x80;
  const hex = Array.from(b, (x) => x.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Draft persistence (concept 4.11.3): text and state in sessionStorage
 * (cbf:{key}:draft), the screenshot without burnt-in marks in IndexedDB
 * (cbf-{key}, store "shots", one record per tab). Storage errors (private
 * mode, quota) are swallowed, the widget then works without a draft.
 */
export class DraftStore {
  private readonly key: string;
  private readonly tabKey: string;
  private readonly db: ShotDb;
  private memory: { draft: Draft | null; blob: Blob | null } = { draft: null, blob: null };

  constructor(
    projectKey: string,
    private readonly persist: boolean,
    private readonly ttlMs: number,
  ) {
    this.key = `cbf:${projectKey}:draft`;
    this.tabKey = `cbf:${projectKey}:tab`;
    this.db = new ShotDb(`cbf-${projectKey}`);
  }

  private tabId(): string {
    const s = session();
    try {
      let id = s?.getItem(this.tabKey);
      if (!id) {
        id = uuid();
        s?.setItem(this.tabKey, id);
      }
      return id;
    } catch {
      return 'tab';
    }
  }

  async load(): Promise<LoadedDraft | null> {
    if (!this.persist) return this.memory.draft ? { draft: this.memory.draft, blob: this.memory.blob } : null;
    let draft: Draft | null;
    try {
      const raw = session()?.getItem(this.key);
      draft = raw ? (JSON.parse(raw) as Draft) : null;
    } catch {
      draft = null;
    }
    if (!draft || draft.v !== 1) return null;
    if (Date.now() - draft.savedAt > this.ttlMs) {
      await this.clear();
      return null;
    }
    let blob: Blob | null = null;
    if (draft.shot) {
      const record = await this.db.get<{ data: ArrayBuffer; type: string }>(this.tabId());
      blob = record?.data ? new Blob([record.data], { type: record.type || 'image/jpeg' }) : null;
      if (!blob) draft.shot = null;
    }
    return { draft, blob };
  }

  save(draft: Omit<Draft, 'v' | 'savedAt'>): void {
    const full: Draft = { v: 1, savedAt: Date.now(), ...draft };
    if (!this.persist) {
      this.memory.draft = full;
      return;
    }
    try {
      session()?.setItem(this.key, JSON.stringify(full));
    } catch {
      // Quota or blocked storage: no draft.
    }
  }

  async saveShot(blob: Blob): Promise<boolean> {
    if (!this.persist) {
      this.memory.blob = blob;
      return true;
    }
    // Stored as ArrayBuffer: Blobs in IndexedDB are unreliable in some Safari versions.
    try {
      return await this.db.put(this.tabId(), { data: await blob.arrayBuffer(), type: blob.type });
    } catch {
      return false;
    }
  }

  async clearShot(): Promise<void> {
    if (!this.persist) {
      this.memory.blob = null;
      return;
    }
    await this.db.delete(this.tabId());
  }

  async clear(): Promise<void> {
    this.memory = { draft: null, blob: null };
    if (!this.persist) return;
    try {
      session()?.removeItem(this.key);
    } catch {
      // ignore
    }
    await this.db.delete(this.tabId());
  }

  close(): void {
    this.db.close();
  }
}
