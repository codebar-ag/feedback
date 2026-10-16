import { describe, expect, it } from 'vitest';
import { LOCALES, bcp47, resolveLocale, translator } from '../../src/i18n/index';

// Core texts from concept 4.5 (keys as in the code).
const CONCEPT: Record<string, [string, string, string, string]> = {
  launcher: ['Feedback geben', 'Donner un avis', 'Give feedback', 'Invia feedback'],
  email: ['E-Mail', 'E-mail', 'Email', 'E-mail'],
  message: ['Dein Feedback', 'Ton avis', 'Your feedback', 'Il tuo feedback'],
  message_ph: [
    'Bitte beschreibe dein Anliegen …',
    'Décris ta demande …',
    'Please describe your feedback …',
    'Descrivi qui la tua richiesta …',
  ],
  done: ['Fertig', 'Terminé', 'Done', 'Fatto'],
  t_rect: ['Rechteck', 'Rectangle', 'Rectangle', 'Rettangolo'],
  t_marker: ['Marker', 'Surligneur', 'Highlighter', 'Evidenziatore'],
  t_undo: ['Rückgängig', 'Annuler', 'Undo', 'Annulla'],
  t_clear: ['Alle Markierungen löschen', 'Effacer toutes les marques', 'Clear all marks', 'Cancella tutti i segni'],
  shot_add: ['Screenshot hinzufügen', 'Ajouter une capture', 'Add screenshot', 'Aggiungi screenshot'],
  edit: ['Bearbeiten', 'Modifier', 'Edit', 'Modifica'],
  remove: ['Screenshot entfernen', 'Supprimer la capture', 'Remove screenshot', 'Rimuovi screenshot'],
  tools: ['Tools', 'Outils', 'Tools', 'Strumenti'],
  send_as: ['Senden als', 'Envoyer en tant que', 'Sending as', 'Invia come'],
  submit: ['Senden', 'Envoyer', 'Send', 'Invia'],
  sending: ['Wird gesendet …', 'Envoi en cours …', 'Sending …', 'Invio in corso …'],
  success_title: ['Danke für dein Feedback!', 'Merci pour ton retour !', 'Thanks for your feedback!', 'Grazie per il tuo feedback!'],
  success_text: [
    'Wir haben deine Nachricht erhalten.',
    'Nous avons bien reçu ton message.',
    'We have received your message.',
    'Abbiamo ricevuto il tuo messaggio.',
  ],
  close: ['Schliessen', 'Fermer', 'Close', 'Chiudi'],
  err_title: ['Senden fehlgeschlagen', 'Échec de l’envoi', 'Sending failed', 'Invio non riuscito'],
  err_text: [
    'Dein Feedback wurde nicht gesendet.',
    'Ton retour n’a pas été envoyé.',
    'Your feedback was not sent.',
    'Il tuo feedback non è stato inviato.',
  ],
  err_back: ['Zurück zum Formular', 'Retour au formulaire', 'Back to the form', 'Torna al modulo'],
  error_email: [
    'Bitte gib eine gültige E-Mail ein.',
    'Saisis une adresse e-mail valide.',
    'Please enter a valid email.',
    'Inserisci un indirizzo e-mail valido.',
  ],
  error_message: [
    'Bitte gib mindestens 3 Zeichen ein.',
    'Saisis au moins 3 caractères.',
    'Please enter at least 3 characters.',
    'Inserisci almeno 3 caratteri.',
  ],
};

const ORDER = ['de_CH', 'fr_CH', 'en_CH', 'it_CH'] as const;

describe('i18n', () => {
  it('has the same complete key set in every locale', () => {
    const keys = Object.keys(LOCALES.de_CH).sort();
    for (const locale of ORDER) {
      expect(Object.keys(LOCALES[locale]).sort()).toEqual(keys);
      for (const value of Object.values(LOCALES[locale])) expect(value.trim()).not.toBe('');
    }
  });

  it('matches the concept texts', () => {
    for (const [key, values] of Object.entries(CONCEPT)) {
      ORDER.forEach((locale, i) => expect(LOCALES[locale][key as keyof (typeof LOCALES)['de_CH']], `${locale}.${key}`).toBe(values[i]));
    }
  });

  it('uses Swiss spelling (no ß)', () => {
    for (const value of Object.values(LOCALES.de_CH)) expect(value).not.toContain('ß');
  });

  it('resolves locales with the fallback chain', () => {
    expect(resolveLocale('de_CH')).toBe('de_CH');
    expect(resolveLocale('fr-CH')).toBe('fr_CH');
    expect(resolveLocale('IT_ch')).toBe('it_CH');
    expect(resolveLocale('de')).toBe('de_CH');
    expect(resolveLocale('de-DE')).toBe('de_CH');
    expect(resolveLocale('fr_FR')).toBe('fr_CH');
    expect(resolveLocale('es_ES')).toBe('en_CH');
    expect(resolveLocale(undefined)).toBe('en_CH');
    expect(bcp47('de_CH')).toBe('de-CH');
  });

  it('applies message overrides per locale', () => {
    const t = translator('de_CH', { de_CH: { submit: 'Abschicken' }, fr_CH: { submit: 'X' } });
    expect(t('submit')).toBe('Abschicken');
    expect(t('close')).toBe('Schliessen');
    expect(translator('fr_CH')('submit')).toBe('Envoyer');
  });
});
