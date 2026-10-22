import type { MessageKey, Translator } from '../i18n/index.js';
import { h, text } from './dom.js';
import { icon } from './icons.js';

export interface StatusPage {
  el: HTMLElement;
  button: HTMLButtonElement;
  copy: HTMLButtonElement;
  /** Share link of the sent feedback (success page only), null hides it. */
  setLink(url: string | null): void;
  update(t: Translator, minHeight: number): void;
}

const COPIED_MS = 2000;

async function copyText(input: HTMLInputElement): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(input.value);
    return true;
  } catch {
    // Without clipboard permission: select the field and use the legacy command.
    input.focus();
    input.select();
    try {
      return document.execCommand('copy');
    } catch {
      return false;
    }
  }
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

  const label = h('label', { class: 'sl', for: 'fb-share' });
  const input = h('input', { id: 'fb-share', type: 'text', readonly: true, spellcheck: 'false' });
  const copy = h('button', { type: 'button', class: 'cp' });
  const share = h('div', { class: 'sh', hidden: true }, label, h('div', { class: 'sw' }, input, copy));
  let copied = false;
  let timer: ReturnType<typeof setTimeout> | undefined;
  let t: Translator | null = null;
  const renderCopy = (): void => {
    if (t) text(copy, t(copied ? 'copied' : 'copy'));
  };
  input.addEventListener('focus', () => input.select());
  copy.addEventListener('click', () => {
    void copyText(input).then((ok) => {
      if (!ok) return;
      copied = true;
      renderCopy();
      clearTimeout(timer);
      timer = setTimeout(() => {
        copied = false;
        renderCopy();
      }, COPIED_MS);
    });
  });

  const el = h(
    'div',
    { class: kind === 'success' ? 'st ok' : 'st err', role: kind === 'success' ? 'status' : 'alert', hidden: true },
    h('div', { class: 'ic' }, icon(kind === 'success' ? 'check' : 'alert')),
    title,
    body,
    kind === 'success' && share,
    button,
  );
  return {
    el,
    button,
    copy,
    setLink(url) {
      share.hidden = url === null;
      input.value = url ?? '';
      copied = false;
      clearTimeout(timer);
      renderCopy();
    },
    update(translate, minHeight) {
      t = translate;
      text(title, t(keys[0]));
      text(body, t(keys[1]));
      text(button, t(keys[2]));
      text(label, t('share_label'));
      renderCopy();
      el.style.minHeight = minHeight > 0 ? `${minHeight}px` : '';
    },
  };
}
