import type { Tool } from '../annotate/tools';
import type { Translator } from '../i18n/index';
import { h, setAttrs, text } from './dom';
import { icon } from './icons';

export interface Toolbar {
  el: HTMLElement;
  done: HTMLButtonElement;
  update(t: Translator, tool: Tool, canUndo: boolean, hasMarks: boolean): void;
}

/** Floating toolbar: rectangle, marker | undo, clear all | done. */
export function createToolbar(handlers: { tool(t: Tool): void; undo(): void; clear(): void; done(): void }): Toolbar {
  const rect = h('button', { type: 'button', 'data-tool': 'rect' }, icon('rect'));
  const marker = h('button', { type: 'button', 'data-tool': 'marker' }, icon('marker'));
  const undo = h('button', { type: 'button', 'data-action': 'undo' }, icon('undo'));
  const clear = h('button', { type: 'button', 'data-action': 'clear' }, icon('clear'));
  const done = h('button', { type: 'button', class: 'fin', 'data-action': 'done' });
  const el = h(
    'div',
    { class: 'tb', role: 'toolbar' },
    rect,
    marker,
    h('span', { class: 'dv', 'aria-hidden': 'true' }),
    undo,
    clear,
    h('span', { class: 'dv', 'aria-hidden': 'true' }),
    done,
  );

  rect.addEventListener('click', () => handlers.tool('rect'));
  marker.addEventListener('click', () => handlers.tool('marker'));
  undo.addEventListener('click', handlers.undo);
  clear.addEventListener('click', handlers.clear);
  done.addEventListener('click', handlers.done);

  return {
    el,
    done,
    update(t, tool, canUndo, hasMarks) {
      setAttrs(el, { 'aria-label': t('tools_label') });
      setAttrs(rect, { 'aria-label': t('t_rect'), title: t('t_rect'), 'aria-pressed': String(tool === 'rect') });
      setAttrs(marker, { 'aria-label': t('t_marker'), title: t('t_marker'), 'aria-pressed': String(tool === 'marker') });
      setAttrs(undo, { 'aria-label': t('t_undo'), title: t('t_undo'), 'aria-disabled': canUndo || hasMarks ? false : 'true' });
      setAttrs(clear, { 'aria-label': t('t_clear'), title: t('t_clear'), 'aria-disabled': hasMarks ? false : 'true' });
      text(done, t('done'));
    },
  };
}
