import { h, setAttrs } from './dom';
import { icon } from './icons';

/** Slim header in the project color: sparkle left, X right, no title. */
export function createHeader(onClose: () => void): { el: HTMLElement; close: HTMLButtonElement; update(label: string): void } {
  const close = h('button', { type: 'button', class: 'x' }, icon('close'));
  close.addEventListener('click', onClose);
  const el = h('header', { class: 'hd' }, icon('sparkle'), close);
  return { el, close, update: (label) => setAttrs(close, { 'aria-label': label, title: label }) };
}
