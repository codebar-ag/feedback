import type { MessageKey, Translator } from '../i18n/index';
import { h, text } from './dom';
import { icon } from './icons';

export interface StatusPage {
  el: HTMLElement;
  button: HTMLButtonElement;
  update(t: Translator, minHeight: number): void;
}

/**
 * Success and error page, one component with two variants. Same container
 * and at least the last measured form height, content centered.
 */
export function createStatusPage(kind: 'success' | 'error', onButton: () => void): StatusPage {
  const keys: [MessageKey, MessageKey, MessageKey] =
    kind === 'success' ? ['success_title', 'success_text', 'close'] : ['err_title', 'err_text', 'err_back'];
  const title = h('h2');
  const body = h('p');
  const button = h('button', { type: 'button', class: 'pl' });
  button.addEventListener('click', onButton);
  const el = h(
    'div',
    { class: kind === 'success' ? 'st ok' : 'st err', role: kind === 'success' ? 'status' : 'alert', hidden: true },
    h('div', { class: 'ic' }, icon(kind === 'success' ? 'check' : 'alert')),
    title,
    body,
    button,
  );
  return {
    el,
    button,
    update(t, minHeight) {
      text(title, t(keys[0]));
      text(body, t(keys[1]));
      text(button, t(keys[2]));
      el.style.minHeight = minHeight > 0 ? `${minHeight}px` : '';
    },
  };
}
