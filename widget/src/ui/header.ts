import { h, setAttrs } from './dom.js';
import { icon } from './icons.js';

/** Slim header in the project color: sparkle left, X right, no title. */
export function createHeader(onClose: () => void): { el: HTMLElement; close: HTMLButtonElement; update(label: string): void } {
  const close = h('button', { type: 'button', class: 'x' }, icon('close'));
  close.addEventListener('click', onClose);
  // A div, not <header>: inside the dialog it must not become a second banner landmark.
  const el = h('div', { class: 'hd' }, icon('sparkle'), close);
  return { el, close, update: (label) => setAttrs(close, { 'aria-label': label, title: label }) };
}
