/**
 * State machine. Exactly one state is active, transitions
 * not listed are ignored (e.g. open()/close() while capturing).
 */
export type State = 'closed' | 'form' | 'capturing' | 'annotating' | 'sending' | 'success' | 'error';

export type Action =
  | 'OPEN'
  | 'CLOSE'
  | 'CAPTURE'
  | 'CAPTURED'
  | 'CAPTURE_FAILED'
  | 'EDIT'
  | 'DONE'
  | 'CANCEL'
  | 'SUBMIT'
  | 'SENT'
  | 'FAILED'
  | 'INVALID'
  | 'BACK';

export const TRANSITIONS: Record<State, Partial<Record<Action, State>>> = {
  closed: { OPEN: 'form' },
  form: { CLOSE: 'closed', CAPTURE: 'capturing', EDIT: 'annotating', SUBMIT: 'sending' },
  capturing: { CAPTURED: 'annotating', CAPTURE_FAILED: 'form' },
  annotating: { DONE: 'form', CANCEL: 'form' },
  sending: { SENT: 'success', FAILED: 'error', INVALID: 'form' },
  success: { CLOSE: 'closed' },
  error: { BACK: 'form', CLOSE: 'closed' },
};

export function transition(state: State, action: Action): State | null {
  return TRANSITIONS[state][action] ?? null;
}

type Listener = (state: State, previous: State) => void;

export class Store {
  private listeners = new Set<Listener>();

  constructor(private current: State = 'closed') {}

  get state(): State {
    return this.current;
  }

  dispatch(action: Action): boolean {
    const next = transition(this.current, action);
    if (!next) return false;
    const previous = this.current;
    this.current = next;
    for (const fn of this.listeners) fn(next, previous);
    return true;
  }

  subscribe(fn: Listener): () => void {
    this.listeners.add(fn);
    return () => this.listeners.delete(fn);
  }
}
