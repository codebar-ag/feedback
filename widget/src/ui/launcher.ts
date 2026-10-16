import { h, setAttrs, text } from './dom';
import { icon } from './icons';

export interface Launcher {
  el: HTMLButtonElement;
  update(open: boolean, label: string, showText: boolean): void;
}

/** Round icon button, sparkle when closed, cross when open (concept 4.11.4). */
export function createLauncher(onClick: () => void, onIntent: () => void): Launcher {
  const el = h('button', { type: 'button', class: 'l', 'aria-haspopup': 'dialog', 'aria-expanded': 'false' });
  const label = h('span');
  el.addEventListener('click', onClick);
  el.addEventListener('pointerenter', onIntent, { once: true });
  el.addEventListener('focus', onIntent, { once: true });
  let last = '';
  return {
    el,
    update(open, value, showText) {
      setAttrs(el, { 'aria-expanded': String(open), 'aria-label': value, title: value, 'data-open': open });
      const key = `${open}|${showText}|${value}`;
      if (key === last) return;
      last = key;
      el.classList.toggle('tx', showText && !open);
      text(label, value);
      el.replaceChildren(icon(open ? 'close' : 'sparkle'), ...(showText && !open ? [label] : []));
    },
  };
}
