import { describe, expect, it, vi } from 'vitest';
import { Store, TRANSITIONS, transition, type Action, type State } from '../../src/store';

const ALL_ACTIONS: Action[] = [
  'OPEN',
  'CLOSE',
  'CAPTURE',
  'CAPTURED',
  'CAPTURE_FAILED',
  'EDIT',
  'DONE',
  'CANCEL',
  'SUBMIT',
  'SENT',
  'FAILED',
  'INVALID',
  'BACK',
];

// Concept 4.11.2: every transition, one test each.
const EXPECTED: [State, Action, State][] = [
  ['closed', 'OPEN', 'form'],
  ['form', 'CLOSE', 'closed'],
  ['form', 'CAPTURE', 'capturing'],
  ['form', 'EDIT', 'annotating'],
  ['form', 'SUBMIT', 'sending'],
  ['capturing', 'CAPTURED', 'annotating'],
  ['capturing', 'CAPTURE_FAILED', 'form'],
  ['annotating', 'DONE', 'form'],
  ['annotating', 'CANCEL', 'form'],
  ['sending', 'SENT', 'success'],
  ['sending', 'FAILED', 'error'],
  ['sending', 'INVALID', 'form'],
  ['success', 'CLOSE', 'closed'],
  ['error', 'BACK', 'form'],
  ['error', 'CLOSE', 'closed'],
];

describe('state machine', () => {
  it.each(EXPECTED)('%s --%s--> %s', (from, action, to) => {
    expect(transition(from, action)).toBe(to);
  });

  it('ignores every other transition', () => {
    const allowed = new Set(EXPECTED.map(([f, a]) => `${f}:${a}`));
    for (const state of Object.keys(TRANSITIONS) as State[]) {
      for (const action of ALL_ACTIONS) {
        if (!allowed.has(`${state}:${action}`)) expect(transition(state, action), `${state}:${action}`).toBeNull();
      }
    }
  });

  it('ignores open/close from the host while capturing, annotating and sending', () => {
    for (const state of ['capturing', 'annotating', 'sending'] as State[]) {
      expect(transition(state, 'OPEN')).toBeNull();
      expect(transition(state, 'CLOSE')).toBeNull();
    }
  });

  it('notifies subscribers', () => {
    const store = new Store();
    const fn = vi.fn();
    const off = store.subscribe(fn);
    expect(store.dispatch('OPEN')).toBe(true);
    expect(store.dispatch('OPEN')).toBe(false);
    expect(fn).toHaveBeenCalledExactlyOnceWith('form', 'closed');
    off();
    store.dispatch('CLOSE');
    expect(fn).toHaveBeenCalledTimes(1);
    expect(store.state).toBe('closed');
  });
});
