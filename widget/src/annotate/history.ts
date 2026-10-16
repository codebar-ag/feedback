import type { Mark } from '../types';

type Step = { kind: 'add' } | { kind: 'clear'; previous: Mark[] };

/** Marks plus undo stack. "Clear all" is itself undoable (concept 4.11.6). */
export class History {
  private steps: Step[] = [];
  private items: Mark[];

  constructor(initial: Mark[] = []) {
    this.items = [...initial];
  }

  get marks(): Mark[] {
    return [...this.items];
  }

  get canUndo(): boolean {
    return this.steps.length > 0;
  }

  add(mark: Mark): void {
    this.items.push(mark);
    this.steps.push({ kind: 'add' });
    if (this.steps.length > 200) this.steps.shift();
  }

  clear(): void {
    if (this.items.length === 0) return;
    this.steps.push({ kind: 'clear', previous: this.items });
    this.items = [];
  }

  undo(): void {
    const step = this.steps.pop();
    if (!step) {
      // Marks restored from a draft can still be removed one by one.
      this.items.pop();
      return;
    }
    if (step.kind === 'add') this.items.pop();
    else this.items = step.previous;
  }
}
