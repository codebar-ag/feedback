# Feedback Widget: Konzept (Enterprise-Level)

Stand: 15.10.2026 · Status: Entwurf zur Freigabe · Arbeitstitel: **codebar Feedback** · Code-Repository: <https://github.com/codebar-ag/feedback> (Paketnamen und Domains unten sind Platzhalter)

Ein eigenes, schlankes Feedback-Widget im Stil von Userback: Launcher-Icon, Formular, Full-Page-Screenshot mit einfacher Markierung, dazu automatisch erfasste technische Daten (Browser, System, Konsolenfehler). Das Widget läuft als npm-Paket auf unseren Websites und sendet an eine eigenständige Laravel-API (Laravel 13, PHP 8.5). Es gibt keine Verwaltungsoberfläche. Projekte werden per Artisan-Command angelegt, Feedbacks landen in der Datenbank.

Design-Unterlagen: `feedback-widget-design.html` (interaktives Mockup mit neutraler Beispielseite: Sprachen, Projektfarbe, Identität, Markieren, Mobile) und der Design-Canvas «Feedback Widget · Design» in der Claude-App (12 Boards, darunter «Alle Funktionen im Überblick», Markierwerkzeuge, Screenshot, Technische Angaben).

---

## 0. Entscheide auf einen Blick

| Thema | Entscheid |
|---|---|
| Screenshot | Clientseitiges DOM-Rendering der **ganzen Seite**, Markierung wird eingebrannt |
| Backend | Eigenständige Laravel-API (Laravel 13, PHP 8.5), die die Endpoints für das Widget hostet |
| Screenshot-Speicher | Direkt in der Datenbank (BLOB), kein S3 |
| Widget-Technik | Vanilla TypeScript + Shadow DOM, kein Framework |
| Verwaltung | Nur Artisan-Commands, keine Management-API, keine Issue-Oberfläche |
| Sprachen | `de_CH`, `fr_CH`, `en_CH`, `it_CH` |
| Anrede | **Du** (Deutsch), Tutoiement im Französischen, «tu» im Italienischen |
| Identität | Name und E-Mail aus dem eingeloggten Bereich injizierbar (Feld E-Mail entfällt), **ohne Signatur (kein HMAC)** |
| Markieren | **Nur zwei Werkzeuge**: Rechteck (einkreisen) und Marker (Textmarker). Kein Text, Pfeil, Stift oder Schwärzen im Bild |
| Kommentar | Wird im **Panel** (Textfeld) geschrieben, nicht ins Bild |
| Farben | **Pro Projekt** beim Anlegen wählbar: Schwarz, Weiss oder Custom-Hex. Alle anderen Farben sind feste neutrale Tokens |
| Entwurf | Bleibt bei Schliessen, Seitenwechsel und Reload erhalten, bis zum Senden (24 h) |
| Protokoll | Änderungen werden mit `spatie/laravel-activitylog` protokolliert |
| Aufbewahrung | Liegt bei euch, direkt in der Datenbank. Das Konzept definiert dazu nichts |
| Diagnose | Konsolenfehler, Browser-Metadaten, Standort aus der IP |

Was noch offen ist, steht in Abschnitt 11. Ich habe keine weiteren Standardwerte festgelegt.

---

## 1. Analyse von Userback

### 1.1 Was ich untersucht habe

- **npm-Paket `@userback/widget` (Version 0.3.13):** vollständig gelesen (Quelltext, Typdefinitionen, ESM/CJS/UMD-Build, rund 17 KB insgesamt).
- **Dokumentation:** JavaScript-SDK-Seite und Support-Artikel zum Native Screenshot.
- **Nicht lesbar:** Die eigentliche Widget-Datei `https://static.userback.io/widget/v1.js`. Dieser Host ist in der Netzwerkfreigabe dieser Sitzung nicht erlaubt. Das, was über das Web-Tool ankam, war nur ein Fragment (jQuery 3.7.1 und ein User-Agent-Parser), ohne Widget-Logik. Die Aussagen zu Screenshot-Technik und Payload unten stammen deshalb aus Typdefinitionen, Doku und deinen Screenshots, nicht aus dem Minified-Code. Wenn du die Datei lokal speicherst und in diesen Ordner legst, vertiefe ich die Analyse.

### 1.2 Wie Userback aufgebaut ist

Das npm-Paket ist ein **reiner Loader**. Es setzt `window.Userback = { request_url: 'https://api.userback.io' }`, hängt ein `<script>` mit `https://static.userback.io/widget/v1.js` an, ruft nach dem Laden `Userback.init(token, options)` auf und löst ein Promise aus `on_init` auf. Die gesamte UI wird also zur Laufzeit vom Anbieter nachgeladen, das Verhalten wird serverseitig pro Projekt gesteuert (Farben, Felder, Trigger).

Daraus folgt für uns: Wir können die **API-Form** (Init-Optionen, Methoden, Events) gut nachbauen, die Architektur aber bewusst anders wählen: UI **im Paket gebündelt** statt von einem Fremdhost geladen (Lieferkette, CSP, Datenschutz), Konfiguration **im Code** statt im Dashboard.

### 1.3 Funktionen und unser Umgang damit

| Userback | Beobachtung | Unser Entscheid |
|---|---|---|
| Launcher-Button (Icon/Text, Position, Farben) | `style: text/circle`, `position`, Button-Farben | **Übernehmen**: Icon-Launcher, Farbe pro Projekt (Schwarz, Weiss, Custom-Hex) |
| Formular (Name, E-Mail, Kommentar, Kategorie, Priorität, Rating) | Felder einzeln ein-/ausschaltbar und Pflicht | **Reduzieren**: E-Mail und Feedback, beide Pflicht (E-Mail entfällt mit Identität). Kategorie später optional |
| Screenshot, Video, Anhang | drei Buttons im Panel | **Nur Screenshot** |
| Annotation (Rechteck, Stift, Linie, Pfeil, Text, Farbe) | Toolbar oben mittig | **Reduzieren** auf Rechteck und Marker, kein Text im Bild |
| Screenshot-Engine | Standard serverseitig gerendert, setzt öffentlich erreichbare Seite voraus (`is_live`); Fallback clientseitig; `native_screenshot` via Screen Capture API mit Browser-Prompt | **Anders**: rein clientseitig, funktioniert auch hinter Login und im Intranet, keine Seitendaten an Dritte |
| `identify(id, info)` / `user_data` | Nutzer aus eingeloggtem Bereich übergeben | **Übernehmen** (ohne Signatur) |
| `setData`, `custom_data` | freie Zusatzdaten | **Übernehmen** (flach, begrenzt) |
| Events `on_open`, `on_close`, `before_send`, `after_send` | Callbacks | **Übernehmen** als Event-API |
| `language` (23 Sprachen) | Liste fest | **Anders**: `de_CH`, `fr_CH`, `en_CH`, `it_CH`, erweiterbar |
| `setTheme` light/dark/auto | vorhanden | **Weglassen**: kein Dark Mode, das Widget ist immer hell |
| Session Replay, Surveys, Portal, Roadmap, Umfragen | Zusatzprodukte | **Weglassen** |
| Metadaten im Dashboard (Quelle, Seite, System, Browser, Bildschirm, DPI, Farbtiefe, Standort) | siehe dein Screenshot | **Übernehmen** (Abschnitt 4.7) |
| Konsolen-/Netzwerkfehler | in Userback über Session Replay | **Eigenständig und leichtgewichtig** (Abschnitt 4.7) |
| Domain-Freigabe | im Dashboard | **Übernehmen und verschärfen**: Domains plus IP/CIDR pro Projekt (Abschnitt 5.4) |

### 1.4 Beobachtungen aus deinen Screenshots

- Orange Rund-Launcher unten rechts mit Funkel-Icon, beim Öffnen wird er zum Schliessen-Kreuz.
- Panel mit oranger Kopfzone (Titel, Minimieren, Schliessen), weissem Formular, Kamera/Video/Büroklammer-Leiste, grossem orangem Absenden-Button. Der Titel zeigt «Hi , how can we help you?» mit **verwaistem Leerzeichen**, weil der Name fehlt. Bei uns gibt es dafür eine saubere Variante ohne Namen.
- Im Annotationsmodus bleibt das Panel offen, oben mittig schwebt eine Toolbar (Rechteck, Marker, Rückgängig, Fertig), das Rechteck ist rot, der Marker gelb halbtransparent. Die Seite dahinter wird leicht abgedunkelt.
- Beschriftungen teils englisch, teils deutsch («Auswählen», «Absenden»). Bei uns kommt alles aus **einem** Sprachpaket.

---

## 2. Ziele und Nicht-Ziele

**Ziele**

1. Feedback in unter 30 Sekunden abgeben, mit Screenshot, ohne Pflichtfelder ausser der Nachricht (und E-Mail, wenn keine Identität übergeben wurde).
2. Immer ein **Full-Page-Screenshot** für den Kontext, Markierungen nur als Hinweis darauf.
3. Alles Nötige zur Reproduktion automatisch dabei: Seite, Browser, System, Fenstergrössen, Konsolenfehler.
4. Sicher auf öffentlichen Websites und in geschützten Bereichen einsetzbar.
5. Betrieb mit minimaler Komplexität: eine Laravel-App, eine Datenbank, keine weiteren Dienste.

**Nicht-Ziele (Version 1)**

Session-Recording, Video, Datei-Anhänge, Management-UI oder -API, Issue-Workflow, Benachrichtigungen (nur Event-Hook vorbereitet), Umfragen, Roadmap, Kategorien/Prioritäten.

---

## 3. Architektur

```
┌─────────────── Website / Web-App (beliebig viele) ───────────────┐
│  npm: @codebar/feedback-widget                                   │
│  ┌───────────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │ UI (Shadow    │  │ Annotation   │  │ Diagnostics           │ │
│  │ DOM, i18n)    │  │ Canvas-Layer │  │ Console/Errors/Meta   │ │
│  └──────┬────────┘  └──────┬───────┘  └──────────┬────────────┘ │
│         └─────────┬────────┴─────────────────────┘              │
│                   │  Lazy chunk: Screenshot-Engine              │
└───────────────────┼─────────────────────────────────────────────┘
                    │ HTTPS, multipart/form-data
                    ▼  POST /v1/projects/{publicKey}/feedback
┌──────────────────────── Feedback-API (Laravel 13) ───────────────┐
│ ResolveProject → OriginPolicy (CORS) → IpPolicy → Throttle       │
│ → FormRequest (Validierung) → StoreFeedback (Transaktion)        │
│ → Event FeedbackReceived                                         │
└───────────────┬──────────────────────────────────────────────────┘
                ▼
   feedback_projects · feedback_entries · feedback_screenshots (BLOB)

   Verwaltung: php artisan feedback:*   (keine HTTP-Verwaltung)
```

Der gesamte Code liegt in einem Repository auf GitHub: `codebar-ag/feedback` (aus dem Starter-Kit hervorgegangen, Ballast entfernt, siehe 12.1). Die **Laravel-API liegt im Wurzelverzeichnis**, das Widget unter `widget/` (eigenes npm-Paket), Konzept, Mockup und Anleitungen unter `docs/`, CI unter `.github/workflows/`. Die genaue Zielstruktur steht in 12.2. Das Widget kann optional als Datei (`/widget/v1/feedback.js` mit SRI-Hash) von der API selbst ausgeliefert werden, für Websites ohne Build-Pipeline.

---

## 4. Widget (npm-Paket)

### 4.1 Technik

- TypeScript (strict), Build mit Vite im Library-Modus: ESM für npm, IIFE für Script-Tag, Typdefinitionen.
- **Shadow DOM** (Modus `open`), Styles per `adoptedStyleSheets` (CSP-freundlich, keine Inline-`<style>`-Tags). Fallback mit `nonce`-Option für Browser ohne Unterstützung.
- Keine Laufzeit-Abhängigkeiten im Kern. Screenshot-Bibliothek (`modern-screenshot`, gepflegter Nachfolger-Ansatz zu html2canvas mit besserer SVG-, Font- und Shadow-DOM-Unterstützung) wird **lazy** per dynamischem Import geladen, beim ersten Hover/Fokus auf den Launcher vorgeladen.
- Zielwerte (Budget, wird in CI geprüft): Kern ≤ 20 KB gzip, Screenshot-Chunk separat. Das sind Planwerte, keine Messungen.
- Unterstützte Browser: aktuelle und vorherige Hauptversion von Chrome, Edge, Firefox, Safari ab 16.4. Mobile Safari und Chrome Android inklusive.
- Das Widget ist **fehlertolerant**: Ein interner Fehler darf die Host-Seite nie beeinträchtigen (alle Einstiegspunkte in try/catch, kein globales Überschreiben ausser der dokumentierten Konsolen-Erfassung).

### 4.2 Ablauf (UX)

1. **Launcher:** runder Icon-Button unten rechts (Position konfigurierbar, mit Safe-Area-Abstand auf Mobile). Tooltip beim Hover. Ein Klick öffnet das Panel.
2. **Panel:** Schmale Kopfzeile in der Projektfarbe mit dem **Funkel-Icon links und dem X rechts** (kein Titel, kein Minimieren). Das X schliesst das Panel, der **Entwurf bleibt erhalten**. Es startet **kein** Screenshot automatisch.
3. **Formular:** Wurde eine Identität übergeben, steht das Label «Senden als» mit einem **Badge** (Name, darunter E-Mail). Sonst gibt es **nur das Feld E-Mail**. **E-Mail (nur ohne Identität) und Feedback sind Pflichtfelder** und mit * markiert. Das Feedback-Feld ist gross (mindestens 3 Zeichen). Fehlermeldungen belegen eine reservierte Zeile, damit kein Layoutshift entsteht.
4. **Screenshot:** Unter dem Textfeld steht das Label «Tools» mit dem **Kamera-Icon**. Kein Text, keine Hilfetexte. Der Klick erfasst die ganze Seite (nur ein Ladesymbol, das Panel blendet aus) und schaltet in den **Markiermodus**: Seite abgedunkelt, Toolbar oben mittig, Standardwerkzeug ist das Rechteck. Die Person darf scrollen, Markierungen haften an der Seite. Mit «Fertig» kommt das Panel zurück, Esc bricht ab.
5. **Zurück im Panel:** Die Zeile zeigt nur die **Vorschau** des Screenshots, dazu die Icons **Bearbeiten** (Stift, zurück in den Markiermodus) und **Papierkorb** (entfernt den Screenshot). Keine Beschriftung.
6. **Entwurf bleibt erhalten:** Text, E-Mail, Screenshot mit Markierungen und der Zustand «Panel offen» werden laufend im Browser gespeichert (`sessionStorage`, Screenshot in `IndexedDB`, nur in diesem Tab, nur lokal). Beim Seitenwechsel (Klick, SPA-Navigation) und bei Reload öffnet das Widget wieder mit dem Entwurf. Der Entwurf wird nach erfolgreichem Senden, beim Entfernen des Screenshots und nach 24 Stunden gelöscht. Ohne Speicher (Privatmodus) arbeitet das Widget normal, nur ohne Entwurf. Init-Option: `draft: { persist: true, ttlHours: 24 }`.
7. **Senden:** Der Screenshot ist immer die **ganze Seite**, auch wenn nur ein Bereich markiert ist. Die Markierungen werden auf das Bild gezeichnet. Danach erscheint die **Erfolgsseite** («Danke für dein Feedback!», schliesst nach 3 Sekunden). Bei einem Fehler erscheint eine eigene **Fehlerseite** im gleichen Layout wie die Erfolgsseite (grosses Icon, Titel «Senden fehlgeschlagen», Text in Grau, ein kleiner Button «Zurück zum Formular»). Erneut gesendet wird im Formular, der Text bleibt dort erhalten. Der Fehlertext lautet nur «Dein Feedback wurde nicht gesendet.» Erfolgs- und Fehlerseite füllen den bestehenden Container und haben **dieselbe Höhe wie das Formular**, der Inhalt steht mittig. Ohne Screenshot (nie hinzugefügt oder entfernt) geht das Feedback ohne Bild raus. Die Option `screenshot.mode` steuert, ob er `optional`, `required` oder `off` ist.

Werkzeuge in Version 1: **nur zwei**. **Rechteck** zum Einkreisen (rot, Standard) und **Marker** (Textmarker, gelb halbtransparent, Freihand). Dazu Rückgängig und Alle löschen. Kein Text, kein Pfeil, kein Stift, kein Schwärzen im Bild. Den **Kommentar** schreibt die Person im Panel (Textfeld), nicht ins Bild. Tastatur: Esc beendet den Modus, Strg/Cmd+Z macht rückgängig. Gespeichert werden die Markierungen als Liste (`type: rect | marker`, Koordinaten in Seitenpixeln) und zusätzlich ins Bild eingebrannt.

**Funktionen im Überblick** (alle im Design-Canvas dargestellt)

| Nr. | Funktion | Verhalten |
|---|---|---|
| 1 | Launcher | Runder Icon-Button, Hover und Fokus sichtbar, wird beim Öffnen zum Kreuz |
| 2 | Panel | Öffnen per Launcher, Schliessen mit X oder Esc, Entwurf bleibt erhalten |
| 3 | Feedback | Grosses Textfeld im Panel, Pflicht (*), mindestens 3 Zeichen |
| 4 | Identität | Mit Identität «Senden als» mit Badge (Name, E-Mail). Sonst nur das Pflichtfeld E-Mail |
| 5 | Screenshot | Kamera-Icon unter dem Textfeld, startet Erfassung und Markiermodus. Nie automatisch. Danach Vorschau, Stift, Papierkorb |
| 6 | Markieren | Rechteck, Marker, Rückgängig, Alle löschen, Fertig. Einheitliche runde Buttons, keine Farbanzeige |
| 7 | Screenshot-Zeile | Label «Tools», Kamera-Icon. Nach der Aufnahme nur Vorschau (72×44), Stift (Bearbeiten) und Papierkorb (Entfernen). Keine Texte |
| 8 | Screenshot fehlgeschlagen | Kurzer Hinweis in der Tools-Zeile, Kamera bleibt für einen neuen Versuch bedienbar. Feedback kann sonst ohne Bild gesendet werden (`screenshot_error`) |
| 9 | Entwurf | Text, E-Mail und Screenshot bleiben bei Reload und Seitenwechsel erhalten |
| 10 | Validierung | Fehlermeldung am Feld, Fokus springt zum ersten Fehler. Unter jedem Feld ist eine feste Zeile für die Meldung reserviert, damit **kein Layoutshift** entsteht |
| 11 | Senden | Ladezustand, automatische Wiederholung bei Netzwerkfehler, eigene Fehlerseite mit erhaltenem Text, eigene Erfolgsseite |
| 12 | Erfolg und Fehler | Eigene Seiten im Container, gleiche Höhe wie das Formular. Erfolg schliesst nach 3 s. Fehler hat nur «Zurück zum Formular» |
| 13 | Sprache, Farbe | `de_CH`, `fr_CH`, `en_CH`, `it_CH`, Projektfarbe. Kein Dark Mode |
| 14 | Mobile | Bottom-Sheet, beim Markieren Panel und Launcher ausgeblendet |
| 15 | Datenschutz im Bild | Elemente mit `data-feedback-mask` werden verdeckt, Passwortfelder immer |

### 4.3 Init-API

```ts
import { initFeedback } from '@codebar/feedback-widget';

const feedback = await initFeedback({
  projectKey: 'fbk_live_8Xk2…',               // öffentlicher Schlüssel
  endpoint: 'https://feedback.codebar.ch',    // API-Basis-URL
  locale: 'de_CH',                            // de_CH | fr_CH | en_CH | it_CH

  // Optional: aus eingeloggtem Bereich injizieren
  identity: {
    id: '4711',
    name: 'Anna Muster',
    email: 'anna.muster@example.ch',
  },

  colors: { primary: '#0A7C66' },             // optional, überschreibt die Projektfarbe (siehe 4.6)
  position: 'bottom-right',
  launcher: { style: 'icon', label: null },   // 'icon' | 'text'
  screenshot: { mode: 'optional', format: 'jpeg', quality: 0.82, maxPixels: 25_000_000 },
  diagnostics: { console: true, network: true, maxEntries: 50 },
  mask: { selectors: ['[data-feedback-mask]'], ignore: ['[data-feedback-ignore]'] },
  customData: { app_version: '2.3.1', tenant: 'acme' },   // flach, max. 8 KB
  messages: { de_CH: { submit: 'Abschicken' } },          // einzelne Texte überschreiben
  nonce: undefined,                           // CSP-Nonce, falls nötig
  zIndex: 2147483000,
});
```

Methoden: `open()`, `close()`, `showLauncher()`, `hideLauncher()`, `identify(identity)`, `setLocale(locale)`, `setData(object)`, `isOpen()`, `destroy()`.
Events (`feedback.on(name, fn)`): `ready`, `open`, `close`, `before_send` (Payload ändern oder per `return false` abbrechen), `after_send`, `error`.

Script-Tag-Variante für Seiten ohne Build, analog zu eurem Userback-Snippet:

```html
<script async src="https://feedback.codebar.ch/widget/v1/feedback.js"
        integrity="sha384-…" crossorigin="anonymous"></script>
<script>
  window.addEventListener('load', function () {
    CodebarFeedback.init({ projectKey: 'fbk_live_8Xk2…', locale: 'de_CH' });
  });
</script>
```

### 4.4 Identität aus dem geschützten Bereich

Im eingeloggten Bereich übergibt die Host-App `identity: { id, name, email }`. Das Widget zeigt dann kein E-Mail-Feld, sondern «Senden als» mit einem Badge (Name, E-Mail). Ohne Identität erscheint nur das Pflichtfeld E-Mail (es gibt kein Namensfeld). Später lässt sich die Identität mit `feedback.identify({ … })` setzen oder ändern.

```blade
<script type="module">
  import { initFeedback } from '/build/feedback.js';
  initFeedback({
    projectKey: '{{ config('services.feedback.key') }}',
    endpoint: '{{ config('services.feedback.url') }}',
    locale: '{{ str(app()->getLocale())->replace('-', '_') }}',
    identity: { id: '{{ auth()->id() }}', name: @json(auth()->user()->name), email: @json(auth()->user()->email) },
  });
</script>
```

**Entscheid: keine Signatur (kein HMAC).** Die Identität ist damit eine Angabe der Host-Seite und wird vom Server nicht verifiziert. Ein Nutzer könnte sie im Browser ändern. Für private Projekte schützt stattdessen die IP-Freigabe (Abschnitt 5.4). Der Server speichert `identity_user_id`, `name` und `email` so, wie sie ankommen.

### 4.5 Mehrsprachigkeit

- Eingabewerte: `de_CH`, `fr_CH`, `en_CH`, `it_CH`. Intern wird auf BCP-47 (`de-CH`) für `Intl` abgebildet.
- Fallback-Kette: gewünschte Sprache → Basissprache (`de`) → `en_CH`.
- Schweizer Rechtschreibung (kein «ß», «Senden» statt «Absenden» wo sinnvoll). Alle Texte liegen in vier kleinen JSON-Paketen im Bundle (je ca. 2 KB), Texte lassen sich über `messages` einzeln überschreiben. `setLocale()` wirkt sofort, auch bei offenem Panel.
- Der Server speichert die gesendete `locale` mit (Auswertung, Antwortsprache).

Kerntexte (Schlüssel wie im Code, Mockup zeigt sie live):

| Schlüssel | de_CH | fr_CH | en_CH | it_CH |
|---|---|---|---|---|
| `launcher` | Feedback geben | Donner un avis | Give feedback | Invia feedback |
| `email` | E-Mail | E-mail | Email | E-mail |
| `message` | Dein Feedback | Ton avis | Your feedback | Il tuo feedback |
| `message_ph` | Bitte beschreibe dein Anliegen … | Décris ta demande … | Please describe your feedback … | Descrivi qui la tua richiesta … |
| `done` | Fertig | Terminé | Done | Fatto |
| `t_rect` | Rechteck | Rectangle | Rectangle | Rettangolo |
| `t_marker` | Marker | Surligneur | Highlighter | Evidenziatore |
| `t_undo` | Rückgängig | Annuler | Undo | Annulla |
| `t_clear` | Alle Markierungen löschen | Effacer toutes les marques | Clear all marks | Cancella tutti i segni |
| `shot_add` | Screenshot hinzufügen | Ajouter une capture | Add screenshot | Aggiungi screenshot |
| `edit` | Bearbeiten | Modifier | Edit | Modifica |
| `remove` | Screenshot entfernen | Supprimer la capture | Remove screenshot | Rimuovi screenshot |
| `tools` | Tools | Outils | Tools | Strumenti |
| `send_as` | Senden als | Envoyer en tant que | Sending as | Invia come |
| `submit` | Senden | Envoyer | Send | Invia |
| `sending` | Wird gesendet … | Envoi en cours … | Sending … | Invio in corso … |
| `success_title` | Danke für dein Feedback! | Merci pour ton retour ! | Thanks for your feedback! | Grazie per il tuo feedback! |
| `success_text` | Wir haben deine Nachricht erhalten. | Nous avons bien reçu ton message. | We have received your message. | Abbiamo ricevuto il tuo messaggio. |
| `close` | Schliessen | Fermer | Close | Chiudi |
| `err_title` | Senden fehlgeschlagen | Échec de l’envoi | Sending failed | Invio non riuscito |
| `err_text` | Dein Feedback wurde nicht gesendet. | Ton retour n’a pas été envoyé. | Your feedback was not sent. | Il tuo feedback non è stato inviato. |
| `err_back` | Zurück zum Formular | Retour au formulaire | Back to the form | Torna al modulo |
| `error_email` | Bitte gib eine gültige E-Mail ein. | Saisis une adresse e-mail valide. | Please enter a valid email. | Inserisci un indirizzo e-mail valido. |
| `error_message` | Bitte gib mindestens 3 Zeichen ein. | Saisis au moins 3 caractères. | Please enter at least 3 characters. | Inserisci almeno 3 caratteri. |

Anrede: **Du** (Entscheid). Im Französischen wird getutoyt, im Italienischen «tu», im Englischen bleibt es neutral. Einzelne Texte lassen sich über `messages` überschreiben.

### 4.6 Farben und Theming (pro Projekt)

Jedes Projekt hat **eine Primärfarbe**. Sie wird per Artisan-Command gewählt und in `feedback_projects.theme` gespeichert:

| Wahl | Wert |
|---|---|
| `black` | `#000000` |
| `white` | `#FFFFFF` |
| Custom | beliebiger Hex-Wert, z. B. `#0A7C66` |

Das Widget lädt die Farbe beim Start über `GET /v1/projects/{publicKey}/config` (Antwort `{ "theme": { "primary": "#0A7C66" } }`, öffentlich, ohne sensible Daten, kurz gecacht, gleiche Origin- und IP-Prüfung wie beim Senden). Die Init-Option `colors.primary` überschreibt die Projektfarbe, etwa für Tests. Ist die Abfrage nicht erreichbar, startet das Widget in neutralem Schwarz. Alle übrigen Farben sind fest und neutral (Tabelle unten).

**Automatisch abgeleitet aus der Primärfarbe**

- **Schrift und Icons auf der Primärfarbe:** Weiss (`#FFFFFF`) oder Dunkelblau (`#101F3C`), je nachdem, was den höheren Kontrast ergibt. Erreicht auch das beste Ergebnis keine 4.5:1 (selten, bei mittleren Tönen), wird der Ton für Buttons und Launcher in 2-%-Schritten abgedunkelt oder aufgehellt, bis 4.5:1 erreicht sind. Der Command zeigt Kontrast und den angepassten Ton an.
- **Schwarz:** Kopfzone, Launcher und Senden-Button schwarz mit weisser Schrift.
- **Weiss:** Kopfzone weiss mit dunkler Schrift und feiner Trennlinie, Launcher weiss mit dunklem Icon und dünnem Rand, Senden-Button dunkelblau (weiss auf weiss wäre unsichtbar).
- **Kein Dark Mode:** Das Widget ist immer hell. Das hält Kontraste und Projektfarben einfach.

Beispiele (berechnet):

| Primärfarbe | Schrift darauf | Kontrast |
|---|---|---|
| `#000000` (Schwarz) | Weiss | 21.00:1 |
| `#FFFFFF` (Weiss) | `#101F3C` | 16.36:1 |
| `#ED813A` (Orange) | `#101F3C` | 6.08:1 (Weiss käme nur auf 2.69:1) |
| `#0A7C66` (Grün) | Weiss | 5.14:1 |

**Feste Farben:**

| Token | Wert | Verwendung | Kontrast |
|---|---|---|---|
| `--fb-ink` | `#101F3C` | Haupttext | 16.36:1 |
| `--fb-ink-muted` | `#5B6B85` | Hilfstexte, Platzhalter | 5.40:1 |
| `--fb-surface` | `#FFFFFF` | Panel |  |
| `--fb-surface-sunken` | `#F5F7FA` | Felder, Badge |  |
| `--fb-border` | `#E2E8F0` | Rahmen |  |
| `--fb-scrim` | `rgba(16,31,60,.45)` | Abdunklung im Annotationsmodus |  |
| `--fb-annotation` | `#E02B20` | Markierungen (rot) | 4.63:1 |
| `--fb-focus` | `#1D4ED8` | Fokusring (3 px, 2 px Abstand) | 6.70:1 |
| `--fb-danger` | `#B42318` | Fehlermeldungen | 6.57:1 |
| `--fb-success` | `#157F3B` | Erfolgsmeldung | 5.08:1 |

Typografie: System-Font-Stack (`ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`), kein Webfont-Nachladen. Grundgrösse 15 px, Zeilenhöhe 1.45, Radius Panel 20 px, Felder 12 px, Buttons Pillenform.

### 4.7 Diagnose-Daten (Konsole und Browser-Metadaten)

Das Widget sammelt automatisch, was ein Entwickler zur Reproduktion braucht. Das Widget zeigt dazu bewusst keine Info-Seite. Die Transparenz liegt bei der Host-Seite (Datenschutzerklärung).

**Browser- und Systemdaten** (Vorbild deine Metadata-Ansicht)

| Feld | Quelle im Browser | Beispiel |
|---|---|---|
| Quelle | fest | `widget` |
| Seite (URL, Titel) | `location.href`, `document.title` | `https://www.beispiel.ch/` |
| Betriebssystem, Version | `navigator.userAgentData` mit `getHighEntropyValues(['platformVersion','fullVersionList'])`, Fallback User-Agent | macOS Tahoe 26.6.2 |
| Browser, Version | gleiche Quelle | Microsoft Edge 154.0 |
| Browserfenster (Viewport) | `innerWidth × innerHeight` | 1541 × 947 |
| Auflösung (Bildschirm) | `screen.width × screen.height` | 1800 × 1169 |
| Pixeldichte (DPI) | `devicePixelRatio` | 2 |
| Farbtiefe | `screen.colorDepth` | 30-bit |
| Seitengrösse, Scrollposition | `scrollWidth/Height`, `scrollX/Y` | 1541 × 6120 |
| Zeitzone, Browsersprache | `Intl.DateTimeFormat().resolvedOptions().timeZone`, `navigator.language` | Europe/Zurich, de-CH |
| Touch, Online-Status | `navigator.maxTouchPoints`, `navigator.onLine` | 0, true |
| Standort | **serverseitig aus der IP** (siehe unten) | Meilen, Schweiz, 8706 |

Wichtig zur Genauigkeit: Chromium-Browser liefern mit Client Hints die genaue OS- und Browserversion. **Safari und Firefox senden das nicht**, und der User-Agent auf macOS ist eingefroren («Mac OS X 10_15_7»). Dort zeigt die Auswertung nur «macOS» oder «Safari 18» mit Hinweis `version_precision: approximate`. Der rohe User-Agent wird immer mitgespeichert. Die Auswertung (Namen, Versionen) macht der Server mit `matomo/device-detector`, sodass sich Erkennungsfehler später ohne neues Widget korrigieren lassen.

**Standort** (Ort, Land, PLZ): kommt aus der IP-Adresse über eine **lokale GeoIP-Datenbank** (MaxMind GeoLite2, per `geoip2/geoip2`, monatlicher Update-Command). Kein Aufruf externer Dienste. Die Genauigkeit liegt auf Stadtebene, PLZ ist oft ungenau. Alternativ kann hinter Cloudflare auf Header zurückgegriffen werden. Standort ist optional und per `.env` abschaltbar (`FEEDBACK_GEO=off`).

**Konsolenfehler und Laufzeitprobleme**

| Was | Wie erfasst | Standard |
|---|---|---|
| `console.error`, `console.warn` | Wrapper um die Methoden, ruft das Original unverändert auf | an |
| Nicht abgefangene Fehler | `window` Event `error` (Meldung, Datei, Zeile, Stack) | an |
| Unhandled Promise Rejections | Event `unhandledrejection` | an |
| Fehlgeschlagene Ressourcen (Bild, Skript, CSS) | `error`-Event in der Capture-Phase | an |
| CSP-Verletzungen | Event `securitypolicyviolation` | an |
| Fehlgeschlagene Requests (fetch/XHR, Status ≥ 400 oder Netzwerkfehler) | Wrapper, nur **Methode, URL ohne Query, Status, Dauer**. Nie Header, Bodies oder Query-Strings | an |
| `console.log`, `info`, `debug` | Wrapper | aus (`levels` konfigurierbar) |

Regeln für Qualität und Datenschutz:

- Ringpuffer mit den **letzten 50 Einträgen**, je Eintrag max. 1 KB, gesamt max. 32 KB. Wiederholte identische Meldungen werden gezählt statt wiederholt.
- **Redaktion vor dem Speichern, im Browser:** Bearer-Tokens, JWTs, lange Hex-/Base64-Strings, `password=`/`token=`/`secret=`/`apikey=`-Muster, E-Mail-Adressen und Kreditkarten-ähnliche Zahlen werden durch `[redacted]` ersetzt. Eigene Regeln per `diagnostics.redact: [/regex/]`.
- Die Erfassung beginnt bei `init()`. **Früh initialisieren**, sonst fehlen frühere Fehler. Die Doku empfiehlt den Aufruf als erstes Skript.
- Der Wrapper ist idempotent (kein doppeltes Einhängen), wird bei `destroy()` zurückgebaut und bricht nie die Originalfunktion. Bekannter Nebeneffekt: In den Browser-DevTools zeigt die Quellenangabe einer Konsolenmeldung dann auf das Widget statt auf die Aufrufstelle. Der Stack im gesendeten Eintrag bleibt korrekt.
- **Transparenz:** Das Widget enthält keine Info-Seite und keine Hinweiszeile. Die Angaben zur Datenerfassung (Browserdaten, Konsolenfehler, IP) gehören in die Datenschutzerklärung der Host-Seite. Die Erfassung läuft ab `init()`, die Weitergabe beim Absenden.

### 4.8 Screenshot-Engine

- **Full-Page:** gerendert wird `document.documentElement` über die gesamte Scrollhöhe und -breite, das Widget selbst und `[data-feedback-ignore]` werden ausgefiltert.
- **Zeitpunkt:** Erfassung erst beim Klick auf das Kamera-Icon (siehe 4.2), nie automatisch beim Öffnen. Markierungen werden in **Dokumentkoordinaten** gespeichert, sodass sie beim Scrollen und bei Grössenänderung korrekt haften, und beim Senden auf das Bild gezeichnet.
- **Grössenbegrenzung:** Canvas-Limits der Browser (Safari ca. 16.7 Mio. Pixel, Chrome höhere Grenzen) werden respektiert. Standard: max. 25 Mio. Pixel bei Desktop, 16 Mio. bei Safari/Mobile. Bei sehr langen Seiten wird der Massstab schrittweise reduziert (1.0 → 0.75 → 0.5).
- **Format:** JPEG mit Qualität 0.82 als Standard (Kompatibilität: Safari kodiert kein WebP per Canvas). Ist das Ergebnis über 3 MB, Qualität und Massstab senken. Der Server akzeptiert bis 6 MB.
- **Datenschutz im Bild:** `[data-feedback-mask]` wird als deckender Block gerendert, `[data-feedback-ignore]` entfernt, Passwortfelder und `autocomplete=cc-*` werden immer maskiert.
- **Bekannte Grenzen** (gelten für jede DOM-Rendering-Lösung, auch für Userbacks Client-Engine): Cross-Origin-Bilder ohne CORS-Header und Cross-Origin-iframes werden als Platzhalter gerendert, WebGL-/Canvas-Inhalte nur wenn `preserveDrawingBuffer` gesetzt ist, Videos zeigen ggf. nur das Poster, Kartenkacheln (Google Maps etc.) fehlen häufig. Gegenmassnahmen: `crossorigin="anonymous"` auf eigenen Bildern, optionaler `assetProxy` der API (Phase 2), Hinweis in der Doku. Schlägt das Rendern fehl, wird **ohne Screenshot gesendet** und `screenshot_error` mitgegeben. Feedback geht nie verloren.
- **Frühe Qualitätsprüfung (Meilenstein 0):** Vor der Entwicklung wird die Engine an echten Seiten getestet (eine öffentliche Website, eine App hinter Login, eine Seite mit iframes und Karten), um die Erwartung abzusichern.

### 4.9 Barrierefreiheit und Mobile

- WCAG 2.2 AA: Panel als `role="dialog"` mit `aria-label` (es gibt keinen sichtbaren Titel), Fokus-Falle und Rückgabe des Fokus an den Launcher, Esc schliesst, alle Bedienelemente per Tastatur erreichbar, sichtbarer Fokusring, `prefers-reduced-motion` wird respektiert, Statusmeldungen über `aria-live`.
- Annotation ist von Natur aus zeigerbasiert. Die Alternative ist vollwertig: Feedback ohne Markierung (Screenshot trotzdem mitgesendet, oder `mode: optional` und abwählbar).
- Mobile (≤ 480 px): Panel als Bottom-Sheet, Markieren mit Touch (Pointer Events), 44 px Mindestgrösse für Bedienelemente, `visualViewport` berücksichtigt (Tastatur verdeckt das Feld nicht).

### 4.10 Sicherheit im Widget

- Alle Nutzereingaben werden nur als Text eingefügt (`textContent`), nie als HTML.
- Kein Zugriff auf Cookies oder Storage der Host-Seite. Der Entwurf (Text, E-Mail, Screenshot mit Markierungen) wird nur lokal in `sessionStorage` und `IndexedDB` des Widgets gehalten (Details 4.11.3), nie an die Host-Seite oder einen Dritten gesendet, nach 24 h, nach dem Senden und beim Entfernen gelöscht.
- Der öffentliche Schlüssel liegt im Code und gilt als **öffentlich**. Schutz entsteht serverseitig (Abschnitt 5.4).
- Netzwerk: nur ein Ziel (`endpoint`), `fetch` mit `credentials: 'omit'`, Timeout 20 s, ein automatischer Wiederholungsversuch bei Netzwerkfehler mit derselben `Idempotency-Key` (keine Duplikate).
- CSP-Hinweis für Host-Seiten: `connect-src` auf den API-Host, `img-src blob: data:`, bei Script-Tag-Variante `script-src` mit SRI-Hash oder Nonce.

---

### 4.11 Implementierungsspezifikation Widget

Dieser Abschnitt legt fest, **wie** das in 4.2 beschriebene Verhalten gebaut wird. Alle Masse stammen aus dem Design-Mockup (`feedback-widget-design.html`) und dem Canvas und sind verbindlich.

#### 4.11.1 Modulstruktur (`widget/src`)

| Pfad | Aufgabe |
|---|---|
| `index.ts`, `init.ts` | Einstieg `initFeedback()`, Script-Tag-Variante `CodebarFeedback.init()`, Singleton-Schutz (zweiter Aufruf gibt dieselbe Instanz zurück) |
| `config.ts` | Optionen validieren, Defaults setzen, Projektkonfiguration vom Server laden (`GET …/config`, 5 s Timeout, Cache 10 min in `sessionStorage`) |
| `store.ts` | Zentraler Zustand (siehe 4.11.2), kleine Pub/Sub-Implementierung ohne Framework |
| `ui/host.ts` | Host-Element, Shadow Root (`open`), `adoptedStyleSheets`, z-index, Position |
| `ui/launcher.ts`, `ui/panel.ts`, `ui/header.ts` | Launcher, Panel-Rahmen, Kopfzeile |
| `ui/form.ts`, `ui/tools-row.ts`, `ui/status-page.ts` | Formular, Zeile «Tools», Fehler- und Erfolgsseite (eine Komponente mit zwei Varianten) |
| `ui/toolbar.ts` | Markier-Toolbar |
| `ui/tokens.css.ts`, `ui/icons.ts` | Design-Tokens (4.11.4), SVG-Icons als Strings (Funkeln, Kreuz, Kamera, Stift, Papierkorb, Rückgängig, Rechteck, Haken, Ausrufezeichen) |
| `i18n/{de_CH,fr_CH,en_CH,it_CH}.ts`, `i18n/index.ts` | Texte, Fallback-Kette, `messages`-Overrides |
| `theme/derive.ts` | Projektfarbe → Tokens (4.6), Kontrastberechnung nach WCAG |
| `capture/snapshot.ts`, `capture/mask.ts`, `capture/compose.ts` | Seite rendern (lazy Chunk), Masken, Markierungen einbrennen, JPEG-Kodierung, Grössenreduktion |
| `annotate/layer.ts`, `annotate/tools.ts`, `annotate/history.ts` | Zeichenfläche, Rechteck- und Marker-Werkzeug, Undo-Stapel |
| `diagnostics/console.ts`, `network.ts`, `meta.ts`, `redact.ts` | Ringpuffer, Client Hints, Redaktion (4.7) |
| `draft/storage.ts`, `draft/idb.ts` | Entwurf in `sessionStorage` und `IndexedDB` (4.11.3) |
| `api/client.ts` | `FormData` bauen, `fetch`, Idempotency-Key, Timeout, Wiederholung |
| `a11y/focus.ts` | Fokus-Falle, Fokusrückgabe, `aria-live` |
| `events.ts` | `on`/`off`/`emit` für `ready`, `open`, `close`, `before_send`, `after_send`, `error` |

#### 4.11.2 Zustandsautomat

Es gibt genau einen aktiven Zustand. Jeder Übergang ist in einem Unit-Test abgedeckt.

| Zustand | Sichtbar | Eintritt | Austritt |
|---|---|---|---|
| `closed` | nur Launcher (Icon Funkeln) | Start ohne offenen Entwurf; X; Esc; `close()` | Klick Launcher; `open()`; Entwurf war offen |
| `form` | Panel mit Formular, Launcher als Kreuz | `open()`, «Zurück zum Formular», «Fertig» im Markiermodus | Kamera → `capturing`; Senden → `sending`; X → `closed` |
| `capturing` | Panel und Launcher unsichtbar (`visibility:hidden`, State bleibt), abgedunkelte Seite, Spinner mittig | Klick Kamera, Klick Stift | Erfolg → `annotating`; Fehler → `form` mit `screenshot_error` (Kamera rot) |
| `annotating` | Abgedunkelte Seite, Zeichenfläche, Toolbar oben mittig | Screenshot fertig | «Fertig» → `form`; Esc → `form` (Screenshot ohne neue Markierungen behalten, falls schon vorhanden, sonst entfernt) |
| `sending` | Formular, Button im Ladezustand (`aria-busy`), Felder `disabled` | gültiges Formular, Klick Senden | 201/200 → `success`; Fehler nach Wiederholung → `error`; 422 → `form` mit Feldfehlern |
| `success` | Erfolgsseite | Antwort 201/200 | nach 3 s oder «Schliessen» → `closed`, Entwurf gelöscht |
| `error` | Fehlerseite | Netzwerkfehler, Timeout, 5xx, 403, 404, 429 | «Zurück zum Formular» → `form` (Entwurf unverändert) |

Regeln: Während `capturing`, `annotating` und `sending` ignoriert der Automat `open()`/`close()`-Aufrufe der Host-Seite bis zum Austritt (verhindert Zwischenzustände). `destroy()` ist immer erlaubt und räumt alle Listener, den Konsolen-Hook, IndexedDB-Handles und das Host-Element auf.

#### 4.11.3 Entwurf (Persistenz)

- **Speicherort Text/Zustand:** `sessionStorage`, Schlüssel `cbf:{projectKey}:draft`. Ein Tab hat einen Entwurf. Ein neuer Tab startet leer (bewusst, kein Datenleck zwischen Tabs auf geteilten Rechnern).
- **Speicherort Screenshot:** `IndexedDB`, Datenbank `cbf-{projectKey}`, Store `shots`, ein Datensatz pro Tab (Schlüssel `tabId`, `crypto.randomUUID()` in `sessionStorage`). Gespeichert wird das JPEG als `Blob` **ohne** eingebrannte Markierungen, dazu die Markierungsliste. So bleiben Markierungen nach Reload editierbar.
- **Form des Entwurfs:**

```json
{
  "v": 1,
  "savedAt": 1760000000000,
  "open": true,
  "email": "anna@beispiel.ch",
  "message": "Die Schaltfläche …",
  "shot": { "w": 1536, "h": 4210, "scale": 1, "url": "https://…/seite", "title": "…", "annotations": [{ "type": "rect", "x": 60, "y": 112, "w": 300, "h": 150 }] },
  "idempotencyKey": "7d2b…"
}
```

- **Zeitpunkte:** Speichern 300 ms nach der letzten Eingabe (debounced) und sofort bei `visibilitychange: hidden`, `pagehide`, Statuswechsel, Markierungsänderung. Beim Start (`initFeedback`) wird der Entwurf geladen, Zustand `open: true` öffnet das Panel wieder (kein Autofokus, kein Scroll-Sprung).
- **Seitenwechsel:** Funktioniert für Mehrseiten-Navigation (Reload) und SPA-Navigation (das Widget bleibt im DOM, Zustand im Speicher). Der Screenshot gehört zur Seite, auf der er aufgenommen wurde. Beim Senden werden deshalb `page_url`/`page_title` der **Aufnahmeseite** gesendet, die aktuelle Seite zusätzlich als `submitted_from_url`.
- **Löschen:** nach 201/200, beim Klick auf Papierkorb (nur Screenshot), nach `ttlHours` (Standard 24, Prüfung beim Laden), bei `destroy({ clearDraft: true })`. Das Löschen betrifft `sessionStorage` und IndexedDB gemeinsam.
- **Fehlertoleranz:** Wirft `sessionStorage` oder IndexedDB (Privatmodus, Quota, Blockierung), läuft das Widget ohne Entwurf weiter, ohne Fehlermeldung. Quota-Fehler beim Screenshot verwerfen nur den Screenshot-Entwurf.
- **Datenschutz:** Der Entwurf bleibt lokal. Passwortfelder sind nicht Teil des Formulars. Maskierte Bereiche sind im gespeicherten Screenshot bereits maskiert (Masken werden beim Rendern angewendet, nicht erst beim Senden).
- **Option:** `draft: { persist: true, ttlHours: 24 }`. Mit `persist: false` gilt «Schliessen verwirft nichts bis zur Seitenneuladung», es wird nur im Speicher gehalten.

#### 4.11.4 Layout- und Typografie-Spezifikation

Alle Werte sind Pixel und gelten für Desktop. Es gibt **keine Hilfetexte** im Widget, Beschriftungen von Icons stehen nur in `aria-label` und `title`.

| Element | Spezifikation |
|---|---|
| Panel | Breite 380, Radius 20, Schatten `0 18px 50px rgba(16,31,60,.32)`, Position `right:20 / bottom:92` (über dem Launcher), `max-height: 100dvh - 112`, Inhalt scrollt bei Platzmangel |
| Launcher | 56 × 56, rund, `right:20 / bottom:20`, Icon 26. Geschlossen Funkeln, offen Kreuz |
| Kopfzeile | Projektfarbe, Padding `10 12 10 20`, Funkel-Icon 24 links, X-Button 34 × 34 rechts, kein Titel. Bei weisser Projektfarbe 1 px Trennlinie |
| Inhalt (Formular) | Padding `16 20 18`, vertikaler Abstand 8 |
| Label | 13 px, Gewicht 600, Zeilenhöhe 1.45. Pflichtfelder `*` per `::after` (`aria-hidden`) |
| Eingabefeld E-Mail | Höhe 44, Radius 12, Padding `10 12`, Schrift 15 |
| Textfeld Feedback | Mindesthöhe 170, Radius 12, Padding `10 12`, Schrift 15, `resize: vertical` |
| Fehlerzeile | feste Höhe 16 unter jedem Feld, 13 px, Farbe `--fb-danger`, bei leerer Meldung `visibility:hidden` (**kein Layoutshift**) |
| Badge «Senden als» | Label darüber, Box mit Hintergrund `--fb-surface-sunken`, Rahmen, Radius 12, Padding `8 12`, Name 15/600, E-Mail darunter 13 in `--fb-ink-muted` |
| Zeile «Tools» | Label 13/600, darunter Icon-Buttons 44 × 44, Radius 12, Abstand 8: Kamera. Mit Screenshot: Vorschau 72 × 44 (Radius 8), Stift, Papierkorb. Spinner 44 × 44 während der Erfassung. Fehler: Kamera mit rotem Rand |
| Senden-Button | volle Breite, Radius 999, Padding `13 18`, 15/700. Ladezustand: Spinner 18 + «Wird gesendet …», `aria-busy`, Button `disabled` |
| Status-Seiten | Padding `34 24 30`, Inhalt vertikal und horizontal mittig, Icon-Kreis 56, Titel 18/700, Text 15 grau, Buttons als Pille (Padding `10 24`, 15/600). **Mindesthöhe = zuletzt gemessene Höhe des Formulars**, damit der Container nicht springt |
| Markier-Toolbar | oben mittig, 20 vom Rand, Pille mit Schatten, Buttons 40 × 40 (Mobile 44), Rechteck, Marker, Trenner, Rückgängig, Alle löschen, Trenner, «Fertig» (Projektfarbe, 14/700). Aktives Werkzeug: Hintergrund `--fb-surface-sunken` und 2 px Ring in `--fb-ink` |
| Seite im Markiermodus | Abdunklung `--fb-scrim`, Markierungen oberhalb |
| Schrift | `ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif` (keine Webfont-Ladung). Skala: 13 (Label, Fehler), 15 (Text, Felder, Buttons), 18 (Titel Status). Es gibt keine weiteren Grössen im Widget |
| Fokus | 3 px Ring `--fb-focus`, Abstand 2 |
| Bewegung | Panel `pop` 180 ms (Opacity, translateY 8), Spinner 0.7 s. Bei `prefers-reduced-motion` ohne Animation |
| Mobile ≤ 480 | Panel `left/right:12`, `bottom:12`, Bottom-Sheet, Launcher bei offenem Panel ausgeblendet, Toolbar-Buttons 44, Safe-Area (`env(safe-area-inset-*)`) |

#### 4.11.5 Formularregeln

| Feld | Pflicht | Regel | Meldung (`i18n`) |
|---|---|---|---|
| E-Mail | ja, **nur ohne Identität** | trim, Regex `^[^\s@]+@[^\s@]+\.[^\s@]+$`, max. 254 | `error_email` |
| Feedback | ja | trim, 3–5000 Zeichen | `error_message` |
| Honeypot | nein | verstecktes Feld `website`, nicht fokussierbar, muss leer bleiben | still verworfen |

- Validierung beim Senden. Danach wird ein Feld bei `blur` und `input` neu geprüft, die Meldung verschwindet sofort bei gültiger Eingabe. Der Fokus springt auf das erste fehlerhafte Feld. Felder tragen `aria-required`, bei Fehler `aria-invalid` und `aria-describedby` auf die Fehlerzeile (`role="alert"`).
- Ohne Identität gibt es **kein Namensfeld**. Das Backend akzeptiert `name` nur aus der Identität.
- Server-422 werden den Feldern zugeordnet (`fields`).

#### 4.11.6 Screenshot und Markieren (Ablauf im Detail)

1. Klick Kamera: Zustand `capturing`. Panel und Launcher werden mit `visibility:hidden` ausgeblendet, zwei `requestAnimationFrame` abgewartet, damit nichts davon im Bild landet.
2. Der lazy Chunk (`modern-screenshot`) rendert `document.documentElement` in voller Scroll-Grösse. Filter: Widget-Host und `[data-feedback-ignore]` raus, `[data-feedback-mask]`, Passwortfelder und `autocomplete=cc-*` als deckende Blöcke (4.8). Skalierung nach Pixelbudget (1.0 → 0.75 → 0.5), JPEG Qualität 0.82, bei mehr als 3 MB nachreduzieren.
3. Nach dem Rendern entsteht die **Zeichenfläche**: ein Canvas in Dokumentgrösse (`position:absolute`, oberhalb der Seite, im Widget-Host), mit der Abdunklung. Mausrad und Touch-Scroll gehen an die Seite durch, Zeigerereignisse werden vom Canvas verarbeitet. Markierungen liegen in **Dokumentkoordinaten** und haften beim Scrollen.
4. Werkzeuge: Rechteck (Ziehen, Mindestgrösse 6 × 6, rot `--fb-annotation`, 3 px), Marker (Freihand, mindestens 3 Punkte, `rgba(255,214,0,.5)`, 20 px, runde Enden). Undo-Stapel unbegrenzt (praktisch ≤ 200), Rückgängig entfernt die letzte Markierung, «Alle löschen» leert die Liste (selbst rückgängig-bar bis «Fertig»).
5. «Fertig»: Markierungen werden mit dem Skalierungsfaktor in ein zweites Canvas **eingebrannt**, JPEG kodiert (nur beim Senden). Im Entwurf bleibt das Bild ohne Markierungen.
6. Fehler im Rendern: `screenshot_error` (Code, z. B. `render_failed`, `canvas_limit`, `timeout` nach 15 s) wird gesetzt, Kamera zeigt Fehlerzustand, Feedback bleibt sendbar.
7. Stift-Icon (Bearbeiten) öffnet den Markiermodus mit dem vorhandenen Bild und den Markierungen, **ohne** neu zu rendern. Soll die Seite neu aufgenommen werden, entfernt die Person den Screenshot (Papierkorb) und tippt die Kamera erneut.

#### 4.11.7 Fehlerzuordnung beim Senden

| Ereignis | Verhalten |
|---|---|
| Netzwerkfehler, Timeout (20 s), 5xx | 1 automatischer Wiederholungsversuch nach 1.5 s mit demselben `Idempotency-Key`, danach Fehlerseite |
| 200/201 | Erfolgsseite, Entwurf löschen, Event `after_send` |
| 403, 404 | Fehlerseite, `error`-Event mit Code (`origin_not_allowed`, `project_not_found`), Konsolen-Warnung für Entwickler |
| 413 | Screenshot wird einmal verkleinert (Qualität 0.6, Massstab 0.5) und erneut gesendet, sonst ohne Bild |
| 422 | zurück ins Formular, Fehler an den Feldern |
| 429 | Fehlerseite, `Retry-After` im `error`-Event |

Die Fehlerseite zeigt für alle Fälle denselben Text («Senden fehlgeschlagen», «Dein Feedback wurde nicht gesendet.»). Details gehen nur ins `error`-Event und in die Konsole.

#### 4.11.8 Paket und Build

- `widget/package.json`: Name `@codebar/feedback-widget` (Platzhalter, siehe Abschnitt 11), `type: module`, `exports` für ESM und Typen, `unpkg`/`jsdelivr` für die IIFE-Datei `dist/feedback.iife.js`, `files: ["dist"]`, `sideEffects: false`, Engines `node >= 22`.
- Vite Library-Modus: Einstieg `src/index.ts`, Ausgaben `dist/feedback.js` (ESM), `dist/feedback.iife.js` (IIFE, globale Variable `CodebarFeedback`), `dist/index.d.ts`. Der Screenshot-Chunk liegt als eigene Datei `dist/chunks/capture-[hash].js`.
- Tooling: TypeScript strict, ESLint (flat config), Prettier, Vitest (jsdom und `fake-indexeddb`), Playwright, `size-limit` (Kern ≤ 20 KB gzip), `publint`, `attw` (Typen-Prüfung).
- Versionierung nach SemVer, `widget_version` wird beim Build aus `package.json` eingesetzt und mit jedem Feedback gesendet.

---

## 5. Backend (Laravel 13, PHP 8.5)

### 5.1 Bausteine

- Eigenständige Laravel-App, ein Zweck: Feedback empfangen. Standard-Stack (php-fpm oder Octane, MySQL/MariaDB oder PostgreSQL).
- Typisierung strikt (`declare(strict_types=1)`, `readonly`-Klassen, Backed Enums für `Locale`). Aktuelle PHP-8.5-Funktionen wie `array_first()` und `array_last()` sind nutzbar, wo sie Code vereinfachen.
- Änderungen an Projekten und Einträgen werden mit `spatie/laravel-activitylog` protokolliert (siehe 5.6).
- Kein Queue-Worker nötig: Speichern ist eine kurze Transaktion. Der Event `FeedbackReceived` ist der Erweiterungspunkt für spätere Benachrichtigungen (E-Mail, Slack, Ticketsystem).

### 5.2 Endpoint-Vertrag

Ein einziger öffentlicher Endpoint plus Health-Check. Der öffentliche Schlüssel steckt im **Pfad**, nicht in einem Header. Grund: Der CORS-Preflight (`OPTIONS`) enthält keine eigenen Header, die API muss aber schon beim Preflight wissen, zu welchem Projekt (und damit zu welcher Domain-Freigabe) die Anfrage gehört.

```
POST   /v1/projects/{publicKey}/feedback     multipart/form-data
OPTIONS /v1/projects/{publicKey}/feedback    Preflight
GET    /v1/projects/{publicKey}/config        Projektfarbe fürs Widget (öffentlich)
GET    /up                                    Health (ohne Details)
```

Header: `Idempotency-Key: <uuid>` (vom Widget erzeugt, Pflicht).

Felder:

| Feld | Typ | Pflicht | Regeln |
|---|---|---|---|
| `message` | string | ja | 3–5000 Zeichen |
| `name` | string | nein | max. 120, nur aus der Identität |
| `email` | string | ja | gültige Adresse, max. 254 (aus Identität oder Feld) |
| `locale` | enum | ja | `de_CH`, `fr_CH`, `en_CH`, `it_CH` |
| `page_url` | string | ja | URL, max. 2048 |
| `page_title` | string | nein | max. 300 |
| `referrer` | string | nein | max. 2048 |
| `client` | JSON | ja | Browser-/Systemdaten (siehe 4.7), max. 4 KB |
| `console_log` | JSON | nein | Array, max. 32 KB |
| `network_log` | JSON | nein | Array, max. 16 KB |
| `annotations` | JSON | nein | Liste von Markierungen `{type: rect\|marker, ...}`, max. 16 KB |
| `custom_data` | JSON | nein | flaches Objekt, max. 8 KB |
| `identity` | JSON | nein | `id`, `name`, `email` (nicht verifiziert) |
| `screenshot` | Datei | nein | JPEG, PNG oder WebP, max. 6 MB, Inhalt per `finfo` geprüft |
| `submitted_from_url` | string | nein | URL im Moment des Sendens (kann von `page_url` abweichen, wenn der Entwurf über Seitenwechsel erhalten blieb), max. 2048 |
| `screenshot_error` | string | nein | Fehlercode, falls die Aufnahme scheiterte (`render_failed`, `too_large`, `unsupported`), max. 64 |
| `widget_version` | string | ja | z. B. `1.0.0` |
| `elapsed_ms` | int | ja | Zeit zwischen Öffnen und Senden |
| `website` | string | muss leer sein | Honeypot |

Antworten:

| Status | Bedeutung | Body |
|---|---|---|
| 201 | gespeichert | `{ "id": "01J…", "status": "received" }` |
| 200 | Wiederholung mit bekanntem `Idempotency-Key` | wie 201, kein neuer Eintrag |
| 403 | Origin oder IP nicht erlaubt | `{ "error": { "code": "origin_not_allowed" } }` |
| 404 | Projekt unbekannt oder deaktiviert | `{ "error": { "code": "project_not_found" } }` |
| 413 | zu gross | `{ "error": { "code": "payload_too_large" } }` |
| 422 | Validierungsfehler | `{ "error": { "code": "validation_failed", "fields": { … } } }` |
| 429 | Limit erreicht | `Retry-After`-Header |

Fehlertexte enthalten nie interne Details (Stacktraces, SQL).

### 5.3 Verarbeitungskette

1. **`ResolveProject`**: Schlüssel im Pfad → Projekt (aktiv). Unbekannt oder inaktiv → 404 (gleiche Antwort, damit sich Schlüssel nicht erraten lassen).
2. **`EnforceOriginPolicy`**: dynamisches CORS (siehe 5.4). Beantwortet auch den Preflight.
3. **`EnforceIpPolicy`**: prüft die Client-IP gegen die erlaubten IPs/CIDR-Bereiche.
4. **Throttle**: Laravel `RateLimiter`, Schlüssel aus Projekt und IP, plus Tageslimit pro Projekt.
5. **`StoreFeedbackRequest`**: Validierung (Tabelle oben), Entfernen von Steuerzeichen.
6. **`StoreFeedback`-Action**: Transaktion (Eintrag, Screenshot, Idempotenz), IP/Geo/Geräte-Auswertung, Event `FeedbackReceived`.

Wichtig für die IP: Hinter Load-Balancer oder CDN müssen **Trusted Proxies** korrekt konfiguriert sein, sonst ist `request->ip()` die IP des Proxys und die IP-Freigabe wirkungslos oder falsch. Das ist in der Deployment-Checkliste verankert.

### 5.4 Domain- und IP-Freigabe (Sicherheitskern)

Jedes Projekt hat zwei Listen. Eine Anfrage wird nur angenommen, wenn **beide** passen (UND-Verknüpfung). Eine leere IP-Liste bedeutet «keine IP-Einschränkung».

**Erlaubte Origins** (Pflicht, mindestens ein Eintrag)

| Muster | Bedeutung |
|---|---|
| `https://app.example.ch` | genau diese Origin (Schema, Host, Port) |
| `https://*.example.ch` | alle Subdomains, **nicht** `example.ch` selbst |
| `http://localhost:*` | beliebiger Port, für Entwicklung |
| `*` | jede Origin, nur mit ausdrücklicher Bestätigung beim Anlegen (öffentliche Seiten) |

**Erlaubte IPs** (optional): Einzeladressen und CIDR, IPv4 und IPv6 (`203.0.113.7`, `10.0.0.0/8`, `2001:db8::/32`). Geprüft mit `Symfony\Component\HttpFoundation\IpUtils::checkIp()`, das in Laravel bereits enthalten ist.

Anwendungsfälle:

- **Interne App:** Origin `https://intranet.firma.ch` plus IP-Bereich des Büros/VPN.
- **Öffentliche Website:** Origin `https://www.firma.ch`, keine IP-Einschränkung, dafür strengere Limits und Honeypot.
- **Mehrere Umgebungen:** getrennte Projekte (Produktion, Test) mit eigenem Schlüssel.

Ehrliche Einordnung: Die Origin-Prüfung ist eine **browserdurchgesetzte** Kontrolle. Ein Skript ausserhalb des Browsers kann den Header `Origin` fälschen. Sie schützt vor Missbrauch des Schlüssels **auf fremden Websites**, nicht vor gezielten Angriffen. Dagegen wirken die weiteren Schichten: IP-Freigabe bei privaten Projekten, Rate Limits, Honeypot und Mindestzeit, strikte Validierung und Grössenlimits. Anfragen **ohne** `Origin`-Header werden abgelehnt (Browser senden ihn bei jedem Cross-Origin-POST).

Skizze des Origin-Matchers:

```php
<?php
declare(strict_types=1);

namespace App\Feedback\Security;

final readonly class OriginPolicy
{
    /** @param list<string> $patterns */
    public function __construct(private array $patterns) {}

    public function allows(?string $origin): bool
    {
        $o = self::parse($origin);
        if ($o === null) {
            return false;
        }
        foreach ($this->patterns as $pattern) {
            if ($pattern === '*' || $this->matches($o, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function matches(array $o, string $pattern): bool
    {
        $p = self::parse($pattern, allowWildcards: true);
        if ($p === null || $p['scheme'] !== $o['scheme']) {
            return false;
        }
        $portOk = $p['port'] === '*' || $p['port'] === $o['port'];
        $hostOk = str_starts_with($p['host'], '*.')
            ? str_ends_with($o['host'], substr($p['host'], 1))
              && strlen($o['host']) > strlen($p['host']) - 1
            : $p['host'] === $o['host'];
        return $portOk && $hostOk;
    }

    /** @return array{scheme:string,host:string,port:string}|null */
    private static function parse(?string $value, bool $allowWildcards = false): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $re = '#^(https?)://((?:\*\.)?[a-z0-9.-]+)(?::(\d+|\*))?$#i';
        if (!preg_match($re, $value, $m) || (!$allowWildcards && ($m[3] ?? '') === '*')) {
            return null;
        }
        $scheme = strtolower($m[1]);
        $port = $m[3] ?? ($scheme === 'https' ? '443' : '80');
        return ['scheme' => $scheme, 'host' => strtolower($m[2]), 'port' => $port];
    }
}
```

Die CORS-Antwort setzt `Access-Control-Allow-Origin` auf genau die anfragende Origin (nie `*` zusammen mit Credentials), `Vary: Origin`, erlaubt `POST`/`OPTIONS`, die Header `Content-Type` und `Idempotency-Key`, und `Access-Control-Max-Age: 600`.

### 5.5 Datenmodell

Drei Tabellen. Der Screenshot liegt bewusst in einer **eigenen Tabelle**, damit Listen- und Detailabfragen keine BLOBs laden.

**`feedback_projects`**

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | ulid, PK | |
| `name` | string(120) | |
| `public_key` | string(48), unique | `fbk_live_…`, öffentlich |
| `theme` | json, nullable | `{"primary":"#0A7C66"}`, Projektfarbe (4.6) |
| `allowed_origins` | json | Liste von Mustern |
| `allowed_ips` | json | Liste, leer = unbeschränkt |
| `is_active` | bool | |
| `daily_limit` | unsigned int, nullable | Tageslimit pro Projekt, leer = kein Limit |
| `created_at`, `updated_at` | timestamps | |

**`feedback_entries`**

| Spalte | Typ | Hinweis |
|---|---|---|
| `id` | ulid, PK | |
| `project_id` | FK | Index `(project_id, created_at)` |
| `client_request_id` | uuid | unique mit `project_id` (Idempotenz) |
| `source` | string(20) | `widget` |
| `name`, `email` | string | |
| `message` | text | |
| `locale` | string(5) | |
| `identity_user_id` | string, nullable | |
| `page_url`, `page_title`, `referrer` | string/text | |
| `origin` | string | tatsächlicher Origin-Header |
| `ip` | string(45) | Modus `full` oder `anonymized` |
| `geo_country`, `geo_city`, `geo_postcode` | string, nullable | aus lokaler GeoIP |
| `os_name`, `os_version`, `browser_name`, `browser_version`, `device_type` | string | vom Server ausgewertet |
| `version_precision` | string(12) | `exact` oder `approximate` |
| `user_agent` | text | roh |
| `viewport_w`, `viewport_h`, `screen_w`, `screen_h`, `page_w`, `page_h` | unsigned int | |
| `scroll_x`, `scroll_y` | unsigned int | |
| `dpr` | decimal(4,2) | |
| `color_depth` | unsigned tinyint | |
| `timezone`, `browser_language` | string | |
| `console_log`, `network_log` | json, nullable | redigiert |
| `annotations` | json, nullable | Markierungen mit Koordinaten |
| `custom_data` | json, nullable | |
| `screenshot_error` | string, nullable | falls Rendern scheiterte |
| `submitted_from_url` | text, nullable | URL beim Senden |
| `widget_version` | string(20) | |
| `completed_at` | timestamp, nullable | **Erledigt-Markierung**, Index `(project_id, completed_at)` |
| `internal_note` | text, nullable | freie Notiz für die Bearbeitung |
| `created_at`, `updated_at` | timestamps | |

**`feedback_screenshots`**

| Spalte | Typ | Hinweis |
|---|---|---|
| `entry_id` | FK, unique | 1:1, `ON DELETE CASCADE` |
| `mime` | string(20) | |
| `width`, `height`, `bytes` | unsigned int | |
| `sha256` | char(64) | Integrität |
| `data` | **LONGBLOB** (MySQL) / `bytea` (PostgreSQL) | |

Migrations-Hinweis: Laravels `binary()` erzeugt in MySQL nur ein `BLOB` (max. 64 KB). Die Migration setzt deshalb auf MySQL/MariaDB per `ALTER TABLE … MODIFY data LONGBLOB NOT NULL`. In `my.cnf` muss `max_allowed_packet` mindestens 16 MB betragen. In PostgreSQL wird `bytea` mit Stream-Ressource geschrieben.

**Grössenplanung (Schätzung):** Ein Full-Page-JPEG liegt typischerweise bei 300–900 KB. 10 000 Feedbacks entsprechen etwa 3–9 GB. Aufbewahrung und Löschen alter Einträge regelt ihr direkt in der Datenbank, das Konzept definiert dazu nichts. Empfehlung: Datenbank-Backups mit Kompression. Bei starkem Wachstum ist der Wechsel auf ein Dateisystem-Disk nur eine Änderung in der `StoreFeedback`-Action, das Modell bleibt gleich.

### 5.6 Artisan-Commands

| Command | Zweck |
|---|---|
| `feedback:project:create {name} {--origin=*} {--ip=*} {--color=}` | Projekt anlegen, gibt den öffentlichen Schlüssel aus, dazu das fertige Init-Snippet |
| `feedback:project:list` | Tabelle: Name, Schlüssel, aktiv, Farbe, Origins, IPs, Anzahl offen/erledigt |
| `feedback:project:show {key}` | Details inkl. URLs/IPs, Farbe und Snippet |
| `feedback:project:update {key} {--add-origin=*} {--remove-origin=*} {--add-ip=*} {--remove-ip=*} {--color=} {--limit=} {--activate} {--deactivate}` | Freigaben, Farbe und Status ändern |
| `feedback:project:rotate-key {key}` | Schlüssel erneuern |
| `feedback:list {--project=} {--open} {--limit=20}` | Neueste Feedbacks, offen/erledigt |
| `feedback:show {id}` | Eintrag mit Metadaten, Konsole, Notiz |
| `feedback:export {id} {--path=}` | Schreibt `feedback-{id}.json` und `screenshot.jpg`, praktisch zur direkten Weiterbearbeitung |
| `feedback:complete {id…}` / `feedback:reopen {id…}` | `completed_at` setzen oder löschen |
| `geoip:update` | GeoIP-Datenbank aktualisieren (nur falls der Standort aus einer lokalen GeoIP-Datenbank kommt, siehe Abschnitt 11) |

`--color` akzeptiert `black`, `white` oder einen Hex-Wert wie `#0A7C66`. Der Command prüft den Wert und zeigt die abgeleitete Schriftfarbe samt Kontrast.

**Activity-Log (`spatie/laravel-activitylog`)**

- Log-Name `feedback`. Protokolliert werden: Projekt angelegt oder geändert (Origins, IPs, Farbe, Limit, aktiv/inaktiv), Schlüssel rotiert, Eintrag erledigt oder wieder geöffnet, Notiz geändert.
- Die Modelle `FeedbackProject` und `FeedbackEntry` nutzen `LogsActivity` mit `logOnly([...])` und `logOnlyDirty()`. Personendaten (`message`, `email`, `name`, `ip`) werden **nicht** ins Log kopiert.
- Auslöser ist die Konsole: Jeder Eintrag bekommt die Eigenschaft `via: artisan` und optional `by` (Option `--by="Name"` bei den Commands, die etwas ändern). Eingehende Feedbacks werden nicht zusätzlich protokolliert, sie sind selbst der Datensatz.
- Die Tabelle `activity_log` kommt aus dem Package. Ihre Aufbewahrung liegt wie beim Rest bei euch.

Beispiel:

```
$ php artisan feedback:project:create "Beispiel Website" --origin=https://www.beispiel.ch --origin="https://*.beispiel.ch" --color="#0A7C66"
Projekt angelegt.
  Public Key : fbk_live_8Xk2Qm4TnC0aBv7ZrLw1Hd
  Origins    : https://www.beispiel.ch, https://*.beispiel.ch
  IPs        : (keine Einschränkung)
  Farbe      : #0A7C66 · Schrift #FFFFFF · Kontrast 5.14:1
Init-Snippet:
  initFeedback({ projectKey: 'fbk_live_8Xk2Qm4TnC0aBv7ZrLw1Hd', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' })
```

Es gibt **keine** HTTP-Verwaltungs-API und keine Issue-Oberfläche, wie gewünscht.

---

## 6. Sicherheit und Datenschutz

| Risiko | Gegenmassnahme |
|---|---|
| Schlüssel auf fremder Website verwendet | Origin-Freigabe (browserdurchgesetzt), Domain-Muster ohne Wildcard-Überraschungen |
| Gefälschte Origin ausserhalb des Browsers | IP-Freigabe, Rate Limits, Honeypot, Mindestzeit, Validierung |
| Spam und Flut | Limits pro IP/Projekt/Tag, Honeypot, `elapsed_ms`-Prüfung, 413 bei Übergrösse |
| Gefälschte Identität im Browser | Identität ist bewusst nicht verifiziert (kein HMAC). Bei privaten Projekten schützt die IP-Freigabe |
| Schädliche Dateien statt Screenshot | Nur JPEG/PNG/WebP, `finfo`-Prüfung, Grössen-/Dimensionslimit, nie ausgeliefert oder ausgeführt, BLOB bleibt in der Datenbank |
| XSS über gespeicherte Texte | Rohtext in der Datenbank, Ausgabe nur escaped (CLI, spätere UIs) |
| SQL-Injection | Eloquent mit Bindings, keine String-Konkatenation |
| Geheimnisse in Konsolenlogs | Redaktion im Browser vor dem Senden, Grössenlimit, Transparenz-Vorschau |
| Personendaten im Screenshot | Maskier-Attribute, Passwortfelder immer maskiert |
| Personendaten allgemein (IP, E-Mail, Standort) | Zweckbindung, Hinweistext im Widget, Personendaten nicht im Activity-Log. Aufbewahrung und Löschen liegen bei euch in der Datenbank |
| Lieferkette des Widgets | UI im eigenen Paket statt Fremdhost, gepinnte Abhängigkeiten, npm-Provenance, SBOM, SRI-Hash bei CDN-Variante |
| Schlüssel-Leck | `rotate-key`, Deaktivieren per Command |

Hinweis zu Recht: Für Schweizer Projekte gilt das nDSG, bei EU-Nutzern zusätzlich die DSGVO. Das Konzept ist darauf ausgelegt (Transparenz, Datenminimierung), ersetzt aber keine juristische Prüfung. Ich bin kein Anwalt. Die Datenschutzerklärung der Host-Websites sollte das Feedback-Widget erwähnen.

---

## 7. Qualitätssicherung und Betrieb

**Tests**

Die vollständige Teststrategie (Pest 5, Vitest, Playwright, Vollstack-E2E, CI-Matrix) steht in 12.5 und 12.6, die Zuordnung Screen → Test in Kapitel 13. Grundsatz: **Alles muss grün sein**, bevor etwas gemerged wird. Zusätzlich gilt: Screenshot-Qualität wird mit einer Referenzseiten-Suite und visuellem Vergleich geprüft (Meilenstein 0 und bei jedem Upgrade der Render-Bibliothek).

**Release**

- Semantische Versionierung, Changelog. Widget-Auslieferung als `/widget/v1/` (Minor/Patch-Updates ohne Eingriff) mit SRI-Hash. Die API ist unter `/v1/` stabil, neue Felder sind optional und abwärtskompatibel.
- CI: Lint, Tests, Bundle-Budget, `npm audit`, `composer audit`, Veröffentlichung mit npm-Provenance.

**Betrieb**

- Strukturierte Logs **ohne** Personendaten (nur IDs, Statuscodes, Fehlercodes). Zähler für akzeptierte/abgelehnte Anfragen je Grund (`origin_not_allowed`, `rate_limited` …) zur Erkennung von Missbrauch.
- Deployment-Checkliste: HTTPS erzwungen, Trusted Proxies gesetzt, `post_max_size`/`upload_max_filesize` ≥ 8 MB, `max_allowed_packet` ≥ 16 MB, Scheduler für `geoip:update` (falls GeoIP genutzt wird), tägliche Datenbank-Sicherung mit Wiederherstellungstest.

---

## 8. Userback → codebar Feedback: Migrationstabelle

| Userback | Neu |
|---|---|
| `Userback('TOKEN', options)` | `initFeedback({ projectKey, endpoint, … })` |
| `user_data: { id, info: { name, email } }` / `identify(id, info)` | `identity: { id, name, email }` / `feedback.identify()` |
| `email`, `name` (Vorbefüllung) | in `identity` |
| `setData`, `custom_data` | `setData`, `customData` |
| `widget_settings.language` | `locale` (`de_CH` …) |
| `widget_settings.style`, `position` | `launcher.style`, `position` |
| `main_button_background_colour` | Projektfarbe per Command oder `colors.primary` |
| `on_open`, `on_close`, `before_send`, `after_send` | `feedback.on('open' | 'close' | 'before_send' | 'after_send')` |
| `openForm('general', 'screenshot')` | `feedback.open()` |
| `showLauncher()`, `hideLauncher()`, `destroy()` | gleich |
| `is_live`, `native_screenshot` | entfällt (immer clientseitig) |

---

## 9. Roadmap (grobe Schätzung, ein Entwickler)

| Meilenstein | Inhalt | Aufwand |
|---|---|---|
| M0 Spike | Screenshot-Qualität an 3–5 echten Seiten prüfen, Canvas-Limits, Entscheid zur Bibliothek | 2 Tage |
| M1 Backend | Migrations, Modelle, Middleware-Kette, Endpoint, Commands, Tests | 5 Tage |
| M2 Widget-Kern | Shadow-DOM-UI, i18n, Theming, Formular, Senden, Identität | 5 Tage |
| M3 Annotation + Screenshot | Full-Page-Snapshot, Rechteck und Marker, Komposition, Masken | 5 Tage |
| M4 Diagnose | Konsole, Fehler, Netzwerk, Metadaten, Redaktion, Geo | 3 Tage |
| M5 Härtung | A11y-Audit, Cross-Browser, Last-/Missbrauchstests, Doku, Release-Pipeline | 4 Tage |
| M6 Pilot | Einsatz auf einer Website und einer internen App, Feedback einarbeiten | 3 Tage |

Zusammen etwa 27 Arbeitstage. Die Zahlen sind Schätzwerte und hängen vor allem vom Ergebnis des Spikes (M0) ab.

---

## 10. Phase 2 (nicht in Version 1)

Einzelne Markierungen auswählen und mit Entf löschen, Ziel-Element zur Markierung (CSS-Selektor des markierten Elements, hilft Entwicklern direkt), optionaler `assetProxy` für Cross-Origin-Bilder, Kategorien, Turnstile-/Captcha-Option für öffentliche Seiten, Webhook/Mail bei neuem Feedback über den Event-Hook, minimale Lese-Ansicht, Hybrid-Screenshot mit nativer Bildschirmaufnahme als Notfall-Fallback.

---

## 11. Entscheide und offene Punkte

**Aus deinem Feedback übernommen:** Du-Anrede, kein HMAC, Farbe pro Projekt (Schwarz, Weiss, Custom-Hex), `spatie/laravel-activitylog`, Aufbewahrung in der Datenbank durch euch. Kein Dark Mode, keine Hilfetexte, keine Info-Seite, nur E-Mail-Feld ohne Identität, Pflichtfelder E-Mail und Feedback, Kamera-Icon mit Label «Tools», persistenter Entwurf, Erfolgs- und Fehlerseite im gleichen Layout und gleicher Höhe, Repo `codebar-ag/feedback` als Starter-Kit-Basis (Laravel im Wurzelverzeichnis), Tests mit Pest 5 und Playwright, CI mit GitHub Actions. Markieren nur mit Rechteck und Marker, Kommentar im Widget, neutrale Beispielseite im Design, Funktionen vollständig visualisiert, Projektfarben beim Anlegen.

**Bewusst nicht definiert:** Aufbewahrungs- und Löschfristen, Zahlenwerte für Rate Limits und Tageslimits (pro Projekt konfigurierbar, ohne Vorgabe).

**Noch offen** (ich habe dafür nichts festgelegt):

1. **Datenbank:** MySQL/MariaDB oder PostgreSQL. Beides ist vorgesehen.
2. **Standort aus der IP:** Quelle wählen (lokale GeoIP-Datenbank, Cloudflare-Header oder weglassen). Der Standort ist optional.
3. **Standardfarbe:** Welche Farbe gilt, wenn ein Projekt keine gesetzt hat? Im Konzept steht neutral Schwarz.
4. **Paketname und Domain:** Das Repository ist `github.com/codebar-ag/feedback`. npm-Scope und API-Domain (z. B. `feedback.codebar.ch`) fehlen noch.
5. **Userback-Code:** Die Widget-Datei `v1.js` war nicht abrufbar (Host nicht freigegeben). Falls ihr sie lokal speichert und ablegt, gleiche ich Details damit ab.

---

## 12. Umsetzung im Repository `codebar-ag/feedback`

Das Repository ist ein Starter-Kit. Alles, was dieses Konzept nicht braucht, wird **entfernt**. Es entsteht ein schlankes Monorepo mit der Laravel-API im Wurzelverzeichnis, dem Widget unter `widget/` und der Dokumentation unter `docs/`.

### 12.1 Aufräumen des Starter-Kits

Vor dem ersten Commit prüft die umsetzende Person das Kit (Laravel-Version, Frontend-Stack, Auth, Beispiele) und entfernt, was nicht gebraucht wird. Erwartet wird mindestens:

| Entfernen | Grund |
|---|---|
| Authentifizierung, Registrierung, Passwort-Reset, Profil, Sessions, Cookies-Middleware für Web | Es gibt keine Benutzeroberfläche und keine Anmeldung |
| `users`-Migration, `User`-Modell, Factory, Seeder, `password_reset_tokens`, `sessions` | nicht benötigt (Verwaltung nur per Artisan) |
| `resources/views`, `resources/js`, `resources/css`, Vite-/Tailwind-/Inertia-/Livewire-/Volt-/Flux-Konfiguration im Wurzelverzeichnis, `package.json` im Wurzelverzeichnis | Das Frontend-Tooling liegt ausschliesslich in `widget/` |
| `routes/web.php` ausser der Health-Route, Standard-Welcome-Seite | Die API hat nur `/v1/...` und `/up` |
| Beispiel-Tests (Auth, Profil, Dashboard) | werden durch die Tests aus 12.5 ersetzt |
| Broadcasting, Mail, Notifications, Queue-Tabellen, Cache-Tabellen, falls nicht gebraucht | Kein Queue-Worker nötig (5.1). Cache und Rate Limiting laufen auf dem konfigurierten Cache-Store |
| Docker-/Sail-/DevContainer-Dateien, wenn sie nicht genutzt werden | weniger Wartung |

Behalten: Laravel-Kern, Pest, Pint, PHPStan/Larastan (falls im Kit, sonst ergänzen), `.editorconfig`, `.gitattributes`, Lizenz. Das Ergebnis dokumentiert `docs/architecture.md` («Was wurde aus dem Starter-Kit entfernt und warum»).

### 12.2 Zielstruktur

```
.
├─ app/
│  ├─ Console/Commands/Feedback/        # feedback:* Commands (5.6)
│  ├─ Enums/                            # Locale, ColorPreset, PrecisionMode
│  ├─ Http/
│  │  ├─ Controllers/                   # StoreFeedbackController, ProjectConfigController
│  │  ├─ Middleware/                    # ResolveProject, EnforceOriginPolicy, EnforceIpPolicy, ApplyProjectCors
│  │  ├─ Requests/                      # StoreFeedbackRequest
│  │  └─ Resources/                     # FeedbackReceivedResource, ProjectConfigResource
│  ├─ Models/                           # FeedbackProject, FeedbackEntry, FeedbackScreenshot
│  ├─ Actions/                          # StoreFeedback (Transaktion), RotatePublicKey
│  ├─ Support/                          # OriginMatcher, IpMatcher, ProjectColor, ClientData, UserAgentParser
│  └─ Events/                           # FeedbackReceived
├─ config/feedback.php                  # Limits, Geo-Quelle, IP-Modus, Standardfarbe
├─ database/{migrations,factories}/
├─ routes/{api.php,console.php}
├─ tests/{Unit,Feature,Arch}/           # Pest 5
├─ widget/                              # npm-Paket (4.11)
│  ├─ src/ · tests/unit/ · tests/e2e/ · fixtures/
│  ├─ package.json · vite.config.ts · tsconfig.json · playwright.config.ts
├─ e2e/                                 # Vollstack-Tests (Widget + Laravel), Playwright
├─ docs/                                # Konzept, Mockup, Anleitungen (12.7)
├─ .github/workflows/                   # CI (12.6)
├─ .github/dependabot.yml
├─ composer.json · phpunit.xml · pint.json · phpstan.neon · README.md · CHANGELOG.md · LICENSE
```

`routes/api.php` ist mit dem Präfix `/` (ohne `/api`) registriert, die Routen heissen `/v1/projects/{publicKey}/feedback`, `/v1/projects/{publicKey}/config`, `/up`. Die Standard-`HandleCors`-Middleware von Laravel ist für diese Routen **deaktiviert**, die CORS-Header setzt `ApplyProjectCors` pro Projekt (5.4).

### 12.3 Backend: Bauplan

1. **Migrations** gemäss 5.5 (`feedback_projects`, `feedback_entries`, `feedback_screenshots`, dazu `activity_log` aus dem Package). Zusätzlich `feedback_entries.submitted_from_url` (text, nullable). LONGBLOB-Anpassung für MySQL/MariaDB per `DB::statement`, auf PostgreSQL `bytea`, auf SQLite `blob`.
2. **Modelle** mit `HasUlids`, Casts (`array` für JSON, `datetime`), `LogsActivity` (nur nicht personenbezogene Felder, `logOnlyDirty()`), Beziehungen, Scopes `open()`, `completed()`. Modelle sind nicht `final`, damit Pest-Fakes möglich bleiben, Klassen mit Logik (`OriginMatcher` etc.) sind `final readonly`.
3. **Middleware-Kette** in dieser Reihenfolge: `ResolveProject` (404 bei unbekannt/inaktiv) → `ApplyProjectCors` (beantwortet `OPTIONS`, setzt `Access-Control-Allow-Origin` nur bei erlaubtem Origin, `Vary: Origin`) → `EnforceOriginPolicy` (403 `origin_not_allowed`) → `EnforceIpPolicy` (403) → `throttle:feedback` (Limiter pro IP, Projekt und Tag, Werte aus `config/feedback.php`, ohne Standardwerte, siehe Abschnitt 11) → Controller.
4. **`StoreFeedbackRequest`**: Regeln aus 5.2. Honeypot und `elapsed_ms` werden vor der Validierung geprüft und bei Verdacht still mit 201 beantwortet (ohne Speichern). Der Screenshot wird per `finfo` geprüft (JPEG/PNG/WebP, Dimension, max. 6 MB).
5. **`StoreFeedback` (Action)**: Transaktion, Idempotenz über `(project_id, client_request_id)` (bei Treffer 200 ohne neuen Eintrag), IP je nach Modus `full`/`anonymized`, Geo optional, UA und Client Hints auswerten, Eintrag und Screenshot speichern, Event `FeedbackReceived` feuern.
6. **Commands** (5.6) mit `--json`-Ausgabe bei `list`, `show` und `project:list`, damit Tests und Skripte sie auswerten können. Jeder ändernde Command schreibt einen Activity-Log-Eintrag (`via: artisan`, `by`).
7. **`config/feedback.php`**: `ip_mode`, `geo` (`none` | `cloudflare` | `maxmind`), `default_color`, `limits` (alle Werte `null`, bis sie gesetzt werden), `max_screenshot_bytes` (6 MB).
8. **PHPStan/Larastan** Level 8 (mindestens), **Pint** mit `laravel`-Preset.

### 12.4 Widget: Bauplan

Umsetzung strikt nach 4.2 bis 4.11. Reihenfolge: (1) Tokens, i18n, Theme-Ableitung, (2) Store und Zustandsautomat, (3) UI-Komponenten, (4) Formular und Validierung, (5) Entwurf, (6) Screenshot und Markieren, (7) Diagnose, (8) API-Client, (9) Barrierefreiheit, (10) Build und Grössenbudget. Jede Stufe wird mit Unit-Tests abgeschlossen, bevor die nächste beginnt.

### 12.5 Tests (alles muss grün sein)

**Backend (Pest 5, PHP 8.5)**

| Typ | Abdeckung |
|---|---|
| Unit | `OriginMatcher` (exakt, Wildcard `*.beispiel.ch`, Port, Schema, Angriffsmuster `https://app.beispiel.ch.evil.com`, `null`-Origin), `IpMatcher` (IPv4, IPv6, CIDR, Grenzfälle), `ProjectColor` (Schwarz 21.00:1 Weiss, Weiss 16.36:1 Dunkelblau, `#ED813A` 6.08:1 Dunkelblau, `#0A7C66` 5.14:1 Weiss, ungültige Eingaben), `ClientData`-Parser (Client Hints, Fallback UA, `version_precision`), Public-Key-Generator (Format `fbk_live_…`, Entropie), `Locale`-Enum, Screenshot-Validator |
| Feature | `POST` 201 (Pflichtfelder, alle optionalen Felder), 200 bei wiederholtem `Idempotency-Key`, 403 (Origin, IP), 404 (unbekannt, inaktiv), 413, 422 (jede Regel einzeln als Dataset), 429, Honeypot, ungültiges Bild, `GET …/config`, CORS-Preflight (erlaubt/abgelehnt, Header, `Vary`), IP-Modus `anonymized`, Activity-Log-Einträge ohne Personendaten, alle `feedback:*` Commands (Ausgabe, Exit-Codes, Validierung von `--color`), `/up` |
| Arch | `declare(strict_types=1)` überall, keine `dd`/`dump`/`env()` ausserhalb Config, Controller greifen nicht direkt auf `DB` zu, Support-Klassen sind `final readonly`, keine Abhängigkeit von `Illuminate\Auth` (Starter-Kit ist entfernt) |
| Datenbank | Standard SQLite (in-memory) für Geschwindigkeit. CI-Matrix zusätzlich MySQL 8 und PostgreSQL 17 mit demselben Test-Set, inklusive BLOB-Roundtrip (Screenshot rein/raus, `sha256` stimmt) |

Befehle: `composer test` (Pest, parallel), `composer lint` (Pint, `--test`), `composer analyse` (PHPStan), `composer test:coverage` (Mindestabdeckung 90 %).

**Widget (Vitest)**: Redaktion (Tokens, Passwörter, Authorization-Header, Query-Parameter), Ringpuffer (Grenzen 50 Einträge, 1 KB, 32 KB), Locale-Fallback und `messages`-Overrides, Theme-Ableitung (dieselben Kontrastwerte wie im Backend-Test, 4.6), Koordinatenrechnung (Dokumentkoordinaten, Skalierung beim Einbrennen), Zustandsautomat (jeder Übergang aus 4.11.2), Entwurf (Speichern, Laden, TTL, Fehler bei Storage, Löschen nach Senden), Formularvalidierung (Tabelle 4.11.5), API-Client (Wiederholung, Idempotency-Key, Fehlerzuordnung 4.11.7), Größenbudget (`size-limit`).

**Widget (Playwright, Chromium, Firefox, WebKit + Mobile-Emulation)** gegen die gebaute Datei und einen Mock-Server in `widget/tests/e2e/server.ts`:

| Bereich | Tests |
|---|---|
| Öffnen/Schliessen | Launcher öffnet, X schliesst, Esc schliesst, Fokus kehrt zum Launcher zurück, Launcher zeigt Kreuz/Funkeln |
| Kopfzeile | Icon links, X rechts, kein Titel, kein Minimieren |
| Identität | mit `identity`: «Senden als» mit Badge (Name, E-Mail), kein E-Mail-Feld. Ohne: nur E-Mail-Feld, kein Namensfeld |
| Pflichtfelder | `*` an E-Mail und Feedback, leere Eingabe zeigt Meldungen, Fokus auf erstes Feld, Meldung verschwindet bei gültiger Eingabe |
| **Kein Layoutshift** | Boundingbox von Panel und Folgefeldern vor und nach Fehlermeldung identisch (Toleranz 0 px) |
| Tools-Zeile | nur Kamera sichtbar, nach Screenshot Vorschau + Stift + Papierkorb, keine Texte («Ganze Seite», «Markierungen») im DOM sichtbar |
| Screenshot | Klick Kamera blendet Panel aus, Spinner, Markiermodus, «Fertig» bringt Panel zurück, kein automatischer Screenshot beim Öffnen |
| Markieren | Rechteck ziehen, Marker ziehen, Rückgängig, Alle löschen, Esc, Strg/Cmd+Z, Mindestgrössen, Scrollen im Markiermodus, Markierungen haften |
| Entwurf | Text und E-Mail bleiben nach Schliessen/Öffnen, nach Reload, nach Navigation auf zweite Seite (Mehrseiten-Fixture), nach SPA-Navigation. Screenshot und Markierungen bleiben nach Reload. Nach erfolgreichem Senden leer. TTL abgelaufen = leer. Privatmodus-Simulation (Storage wirft) = Widget funktioniert |
| Status-Seiten | Erfolg und Fehler haben **dieselbe Höhe wie das Formular**, Fehlerseite hat nur «Zurück zum Formular», Text im Formular bleibt erhalten, Erfolg schliesst nach 3 s und leert den Entwurf |
| Senden | Payload entspricht 5.2 (Felder, Screenshot als Datei, Idempotency-Key), Wiederholung bei Netzwerkfehler ohne Duplikat, 422/403/429/5xx-Zuordnung |
| Sprachen | alle vier Sprachen vollständig (keine Schlüssel im DOM), Fallback, `messages`-Override |
| Projektfarben | Schwarz, Weiss, Custom-Hex: Kopfzeile, Launcher, Button und Kontrast (≥ 4.5:1), Weiss mit Trennlinie und dunklem Senden-Button |
| Barrierefreiheit | axe-core ohne Verstösse in jedem Zustand, Tab-Reihenfolge, Fokus-Falle, `aria-live`, `prefers-reduced-motion` |
| Mobile | Bottom-Sheet, 44-px-Ziele, Markieren per Touch, Tastatur verdeckt das Feld nicht |
| Isolation | Host-Seiten-CSS beeinflusst das Widget nicht (Shadow DOM), Widget-CSS die Seite nicht, Konsolen-Hook entfernt sich bei `destroy()` |

**Vollstack-E2E (`e2e/`, Playwright)**: `php artisan serve` mit SQLite-Testdatenbank, `feedback:project:create` legt ein Projekt an, eine Fixture-Seite lädt das gebaute Widget. Geprüft werden: Feedback samt Screenshot, Konsolenfehler und Browserdaten landet in der Datenbank (Prüfung per `feedback:show --json`, `completed_at` leer), erlaubter Origin (`http://localhost:PORT`) vs. nicht erlaubter (`http://127.0.0.1:PORT`) mit 403 und Fehlerseite, IP-Sperre, Honeypot, Idempotenz bei wiederholtem Senden, `feedback:complete` setzt `completed_at`.

Befehle (Wurzel): `npm --prefix widget run test`, `npm --prefix widget run e2e`, `npm --prefix widget run e2e:full`. Ein Sammelbefehl `composer check` führt Lint, Analyse und Backend-Tests aus, `npm --prefix widget run check` Lint, Typen, Unit-Tests, Build und Budget.

### 12.6 GitHub Actions und Badges

| Workflow | Auslöser | Inhalt |
|---|---|---|
| `tests.yml` (Backend) | Push, Pull Request | Matrix: PHP 8.5 × {SQLite, MySQL 8, PostgreSQL 17}. Schritte: `composer install` (mit Cache), Pint (`--test`), PHPStan, Pest mit Coverage, Upload Coverage als Artefakt |
| `widget.yml` | Push, Pull Request, Pfadfilter `widget/**` | Node 22: `npm ci`, Lint, Typecheck, Vitest, Build, `size-limit`, `publint`, `attw` |
| `e2e.yml` | Push, Pull Request | Playwright-Browser installieren (`--with-deps`), Widget-E2E (3 Browser + Mobile) und Vollstack-E2E, Report und Trace als Artefakt bei Fehlern |
| `security.yml` | wöchentlich, Pull Request | `composer audit`, `npm audit --omit=dev`, Dependency Review |
| `release.yml` | Tag `v*` | Widget bauen, `npm publish --provenance`, GitHub Release mit Changelog und SRI-Hash der IIFE-Datei |
| `dependabot.yml` | – | Composer, npm, GitHub Actions wöchentlich |

Alle Workflows nutzen `concurrency` (laufende Läufe pro Branch abbrechen), minimale `permissions`, gepinnte Action-Versionen. **Badges** oben im README: Tests (Backend), Widget, E2E, Security, PHP 8.5, Laravel 13, npm-Version, Lizenz.

### 12.7 Dokumentation

| Datei | Inhalt |
|---|---|
| `README.md` | Badges, Kurzbeschreibung, Funktionsüberblick, **Schnellstart** (Backend installieren, Projekt anlegen, Widget einbinden), Verweise auf `docs/` |
| `docs/installation.md` | Anforderungen (PHP 8.5, Composer, Node 22, MySQL/PostgreSQL), Setup-Schritte, `.env`-Variablen, Migration, MySQL-Einstellungen (`max_allowed_packet`, LONGBLOB), Webserver (nginx-Beispiel, `post_max_size`), Trusted Proxies, Deployment-Checkliste |
| `docs/widget-setup.md` | **Installationsanleitung des Widgets**: npm (`npm i @codebar/feedback-widget`), Script-Tag mit SRI, Init-Optionen, Identität (Blade/Inertia/Livewire/React/Vue-Beispiele), Sprachen, Farben, `data-feedback-mask`/`-ignore`, CSP-Hinweise, Events, Entwurf, `destroy()`, Fehlerbehebung |
| `docs/commands.md` | Alle `feedback:*` Commands mit Optionen, Beispielen und Ausgabe, Farbregeln, Activity-Log, Export |
| `docs/configuration.md` | `config/feedback.php`, Limits, IP-Modus, Geo-Quelle, Standardfarbe |
| `docs/security.md` | Origin- und IP-Freigabe, Grenzen (Origin ist browserdurchgesetzt), Rate Limits, Datenschutz, Hinweis zur Datenschutzerklärung der Host-Seite |
| `docs/testing.md` | Wie man Pest, Vitest, Playwright lokal ausführt, Testdaten, CI |
| `docs/architecture.md` | Diagramm (Kap. 3), Zustandsautomat, Datenmodell, Entscheide, Starter-Kit-Bereinigung |
| `docs/feedback-widget-design.html` | Interaktives Mockup, Verweis auf den Design-Canvas |
| `docs/feedback-widget-konzept.md` | dieses Dokument |
| `CHANGELOG.md` | Keep-a-Changelog, SemVer |

### 12.8 Definition of Done

1. Alle Tests lokal und in GitHub Actions grün (Backend-Matrix, Widget, E2E in drei Browsern, Security).
2. Jede Zeile der Abnahmematrix (Kapitel 13) hat mindestens einen automatisierten Test, der in CI läuft.
3. README mit Badges, Schnellstart, Widget-Setup und Command-Referenz vollständig, Links funktionieren.
4. Starter-Kit-Ballast entfernt, `composer install` und `npm ci` laufen ohne Warnungen, keine toten Routen, Konfigurationen oder Abhängigkeiten.
5. Statische Analyse und Lint ohne Befunde, Bundle-Budget eingehalten.
6. Manuelle Abnahme anhand des Canvas und des Mockups (Kapitel 13) ohne Abweichung.

---

## 13. Abnahmematrix: Screens → Funktion → Spezifikation → Test

Die Nummern der Boards entsprechen dem Design-Canvas «Feedback Widget · Design».

| Board | Funktion im Screen | Spezifikation | Automatisierter Test |
|---|---|---|---|
| 1 Customer Journey | Alle zehn Schritte vom Launcher bis zur Bestätigung | 4.2, 4.11.2 | Playwright «Journey» (ein Durchlauf pro Browser) |
| 2 Panel auf der Seite | Position, Grösse, kein Überlappen mit Launcher | 4.11.4 | Playwright Boundingbox-Test |
| 3 Markieren | Abdunkeln, Toolbar, Rechteck, Marker | 4.11.6 | Playwright «Markieren», Vitest Koordinaten |
| 4 Alle Funktionen | Launcher, Kopfzeile, Identität, Feedback, Screenshot, Entwurf, Senden, Projektfarbe | 4.2 | je ein Playwright-Test pro Zeile der Funktionstabelle |
| 5 Markierwerkzeuge | Zwei Werkzeuge, Undo, Löschen, Tastatur | 4.11.6 | Playwright, Vitest |
| 6 Screenshot | Zustände leer, Erfassung, Vorschau, Fehler, Datenschutz im Bild, Ganze Seite | 4.8, 4.11.6 | Playwright, Vitest (Masken), Vollstack-E2E (Bild in DB) |
| 7 Fehler und Erfolg | Eigene Seiten, gleiche Höhe wie Formular, ein Button | 4.11.4, 4.11.7 | Playwright Höhenvergleich, API-Mock |
| 8 Zustände | Validierung ohne Layoutshift, «Senden als», Ladezustand, Launcher-Zustände | 4.11.5 | Playwright (Boundingbox, `aria-busy`) |
| 9 Projektfarben | Schwarz, Weiss, Custom, abgeleitete Schrift | 4.6 | Vitest und Pest (gleiche Kontrastwerte), Playwright (Sichtprüfung per Computed Style) |
| 10 Sprachen | `de_CH`, `fr_CH`, `en_CH`, `it_CH` | 4.5 | Playwright, Vitest (vollständige Schlüssel) |
| 11 Mobile | Bottom-Sheet, Touch, Fehler- und Erfolgsseite | 4.9, 4.11.4 | Playwright Mobile-Emulation |
| 12 Farben und Typografie | Tokens, Kontraste, Schriftgrössen 13/15/18 | 4.6, 4.11.4 | Vitest (Tokens), Playwright (Computed Style) |
| Mockup | Entwurf nach Schliessen/Reload/Navigation | 4.11.3 | Playwright «Entwurf» |
| Mockup | Pflichtfelder E-Mail und Feedback, kein Namensfeld | 4.11.5 | Playwright, Pest (422-Regeln) |
| Konzept | Origin- und IP-Freigabe, Idempotenz, Limits, Honeypot | 5.2, 5.4 | Pest Feature, Vollstack-E2E |
| Konzept | Commands und Activity-Log | 5.6 | Pest Feature |
| Konzept | Konsolen- und Browserdaten mit Redaktion | 4.7 | Vitest, Vollstack-E2E |
