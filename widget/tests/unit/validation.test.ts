import { describe, expect, it } from 'vitest';
import { translator } from '../../src/i18n/index';
import { validate } from '../../src/widget';

const t = translator('de_CH');
const run = (email: string, message: string, needsEmail = true, shotRequired = false, hasShot = false) =>
  validate({ email, message, hasShot }, { needsEmail, shotRequired }, t);

describe('form validation (concept 4.11.5)', () => {
  it('accepts a valid form', () => {
    expect(run(' anna@beispiel.ch ', ' Hallo ')).toEqual({});
  });

  it.each(['', 'anna', 'anna@', 'anna@beispiel', 'an na@beispiel.ch', `${'a'.repeat(250)}@b.ch`])('rejects email %j', (email) => {
    expect(run(email, 'Hallo').email).toBe('Bitte gib eine gültige E-Mail ein.');
  });

  it('does not need an email with identity', () => {
    expect(run('', 'Hallo', false)).toEqual({});
  });

  it.each(['', '  ', 'ab', '  ab  ', 'a'.repeat(5001)])('rejects message %j', (message) => {
    expect(run('a@b.ch', message).message).toBe('Bitte gib mindestens 3 Zeichen ein.');
  });

  it('accepts exactly 3 and 5000 characters', () => {
    expect(run('a@b.ch', 'abc')).toEqual({});
    expect(run('a@b.ch', 'a'.repeat(5000))).toEqual({});
  });

  it('requires a screenshot in mode "required"', () => {
    expect(run('a@b.ch', 'abc', true, true).shot).toBe('Bitte füge einen Screenshot hinzu.');
    expect(run('a@b.ch', 'abc', true, true, true)).toEqual({});
  });
});
