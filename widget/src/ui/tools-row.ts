import type { Translator } from '../i18n/index.js';
import { h, setAttrs, text } from './dom.js';
import { icon } from './icons.js';

export interface ToolsRow {
  el: HTMLElement;
  camera: HTMLButtonElement;
  edit: HTMLButtonElement;
  remove: HTMLButtonElement;
  update(t: Translator, view: { busy: boolean; thumb: string | null; failed: boolean; error: string }): void;
}

/**
 * "Tools" row: only the camera icon. With a screenshot: preview (72 × 44),
 * pen (edit) and trash (remove). No texts, labels live in aria-label/title.
 */
export function createToolsRow(onCamera: () => void, onEdit: () => void, onRemove: () => void): ToolsRow {
  const label = h('span', { class: 'lb', id: `cbft${Math.random().toString(36).slice(2, 8)}` });
  const camera = h('button', { type: 'button', class: 'ib' }, icon('camera'));
  const spinner = h('span', { class: 'ib', role: 'img', hidden: true }, h('span', { class: 'sp', 'aria-hidden': 'true' }));
  const thumb = h('img', { class: 'th', alt: '', hidden: true });
  const edit = h('button', { type: 'button', class: 'ib', hidden: true }, icon('edit'));
  const remove = h('button', { type: 'button', class: 'ib', hidden: true }, icon('trash'));
  const error = h('p', { class: 'e', role: 'alert' });
  const row = h('div', { class: 'tr', role: 'group', 'aria-labelledby': label.id }, camera, spinner, thumb, edit, remove);
  const el = h('div', { class: 'f' }, label, row, error);

  camera.addEventListener('click', onCamera);
  edit.addEventListener('click', onEdit);
  remove.addEventListener('click', onRemove);

  return {
    el,
    camera,
    edit,
    remove,
    update(t, view) {
      text(label, t('tools'));
      const has = view.thumb !== null;
      camera.hidden = has || view.busy;
      spinner.hidden = !view.busy;
      thumb.hidden = edit.hidden = remove.hidden = !has || view.busy;
      if (has && thumb.getAttribute('src') !== view.thumb) thumb.src = view.thumb as string;
      camera.classList.toggle('bad', view.failed);
      setAttrs(camera, { 'aria-label': t('shot_add'), title: t('shot_add'), 'aria-invalid': view.failed ? 'true' : false });
      setAttrs(spinner, { 'aria-label': t('shot_busy') });
      setAttrs(edit, { 'aria-label': t('edit'), title: t('edit') });
      setAttrs(remove, { 'aria-label': t('remove'), title: t('remove') });
      text(error, view.error);
    },
  };
}
