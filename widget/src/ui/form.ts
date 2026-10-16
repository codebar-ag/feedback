import type { Identity } from '../types.js';
import type { Translator } from '../i18n/index.js';
import { h, setAttrs, text } from './dom.js';

export interface FormErrors {
  email?: string;
  message?: string;
  shot?: string;
}

export interface FormView {
  el: HTMLFormElement;
  email: HTMLInputElement;
  message: HTMLTextAreaElement;
  honeypot: HTMLInputElement;
  submit: HTMLButtonElement;
  tools: HTMLElement;
  update(t: Translator, identity: Identity | null, errors: FormErrors, sending: boolean): void;
}

let seq = 0;

/**
 * Form: with identity "Senden als" + badge, otherwise only
 * the required email field. No name field. Every field has a reserved
 * 16 px error line, so messages never shift the layout.
 */
export function createForm(
  tools: HTMLElement,
  onSubmit: () => void,
  onInput: (field: 'email' | 'message', blur: boolean) => void,
): FormView {
  const id = `cbf${++seq}`;
  const ids = { email: `${id}-e`, emailErr: `${id}-ee`, message: `${id}-m`, messageErr: `${id}-me`, sendAs: `${id}-s` };

  const sendAsLabel = h('span', { class: 'lb', id: ids.sendAs });
  const name = h('b');
  const mail = h('span');
  const identityRow = h(
    'div',
    { class: 'f', hidden: true },
    sendAsLabel,
    h('div', { class: 'id', role: 'group', 'aria-labelledby': ids.sendAs }, name, mail),
  );

  const emailLabel = h('label', { class: 'lb rq', for: ids.email });
  const email = h('input', {
    id: ids.email,
    type: 'email',
    name: 'email',
    autocomplete: 'email',
    inputmode: 'email',
    required: true,
    'aria-required': 'true',
    'aria-describedby': ids.emailErr,
    maxlength: 254,
  });
  const emailErr = h('p', { class: 'e', id: ids.emailErr, role: 'alert' });
  const emailRow = h('div', { class: 'f' }, emailLabel, email, emailErr);

  const messageLabel = h('label', { class: 'lb rq', for: ids.message });
  const message = h('textarea', {
    id: ids.message,
    name: 'message',
    required: true,
    'aria-required': 'true',
    'aria-describedby': ids.messageErr,
    maxlength: 5000,
    rows: 7,
  });
  const messageErr = h('p', { class: 'e', id: ids.messageErr, role: 'alert' });

  const honeypot = h('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', 'aria-hidden': 'true', class: 'hp' });
  const submit = h('button', { type: 'submit', class: 's' });

  const el = h(
    'form',
    { class: 'fm', novalidate: true },
    identityRow,
    emailRow,
    h('div', { class: 'f' }, messageLabel, message, messageErr),
    tools,
    honeypot,
    submit,
  );

  el.addEventListener('submit', (e) => {
    e.preventDefault();
    onSubmit();
  });
  email.addEventListener('input', () => onInput('email', false));
  email.addEventListener('blur', () => onInput('email', true));
  message.addEventListener('input', () => onInput('message', false));
  message.addEventListener('blur', () => onInput('message', true));

  return {
    el,
    email,
    message,
    honeypot,
    submit,
    tools,
    update(t, identity, errors, sending) {
      text(sendAsLabel, t('send_as'));
      text(emailLabel, t('email'));
      text(messageLabel, t('message'));
      setAttrs(message, { placeholder: t('message_ph') });
      identityRow.hidden = !identity;
      emailRow.hidden = !!identity;
      if (identity) {
        text(name, identity.name ?? identity.email);
        text(mail, identity.name ? identity.email : '');
      }
      text(emailErr, errors.email ?? '');
      text(messageErr, errors.message ?? '');
      setAttrs(email, { 'aria-invalid': errors.email ? 'true' : false, disabled: sending });
      setAttrs(message, { 'aria-invalid': errors.message ? 'true' : false, disabled: sending });
      setAttrs(submit, { disabled: sending, 'aria-busy': sending ? 'true' : false });
      const label = t(sending ? 'sending' : 'submit');
      if (submit.dataset.label !== label) {
        submit.dataset.label = label;
        submit.replaceChildren(...(sending ? [h('span', { class: 'sp', 'aria-hidden': 'true' })] : []), h('span', null, label));
      }
    },
  };
}
