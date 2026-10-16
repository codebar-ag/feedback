import { h } from './dom';

/** Panel frame: role=dialog with aria-label (there is no visible title). */
export function createPanel(header: HTMLElement, body: HTMLElement): HTMLElement {
  return h('section', { class: 'p', role: 'dialog', 'aria-modal': 'false', hidden: true }, header, body);
}
