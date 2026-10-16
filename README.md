# codebar Feedback

[![Tests](https://github.com/codebar-ag/feedback/actions/workflows/tests.yml/badge.svg)](https://github.com/codebar-ag/feedback/actions/workflows/tests.yml)
[![Widget](https://github.com/codebar-ag/feedback/actions/workflows/widget.yml/badge.svg)](https://github.com/codebar-ag/feedback/actions/workflows/widget.yml)
[![E2E](https://github.com/codebar-ag/feedback/actions/workflows/e2e.yml/badge.svg)](https://github.com/codebar-ag/feedback/actions/workflows/e2e.yml)
[![Security](https://github.com/codebar-ag/feedback/actions/workflows/security.yml/badge.svg)](https://github.com/codebar-ag/feedback/actions/workflows/security.yml)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![npm](https://img.shields.io/npm/v/@codebar/feedback-widget?label=npm)](https://www.npmjs.com/package/@codebar/feedback-widget)
[![Lizenz: MIT](https://img.shields.io/badge/Lizenz-MIT-blue.svg)](LICENSE)

Ein schlankes Feedback-Widget für unsere Websites und Web-Apps: Launcher-Icon, Formular, Full-Page-Screenshot mit Markierungen (Rechteck und Marker) und automatisch erfasste technische Daten (Browser, System, Konsolenfehler). Das Widget ist ein npm-Paket (`widget/`), die Feedbacks landen über eine eigenständige Laravel-API (dieses Repository) in der Datenbank. Es gibt keine Verwaltungsoberfläche: Projekte und Feedbacks verwaltest du per Artisan.

Die verbindliche Spezifikation steht in [`docs/feedback-widget-konzept.md`](docs/feedback-widget-konzept.md), das interaktive Mockup in [`docs/feedback-widget-design.html`](docs/feedback-widget-design.html).

## Funktionen

| Bereich | Was es kann |
|---|---|
| Launcher und Panel | Runder Icon-Button, Panel mit schlanker Kopfzeile (Icon links, X rechts), Bottom-Sheet auf Mobile |
| Formular | Mit Identität «Senden als» und Badge, sonst nur das Pflichtfeld E-Mail. Feedback ist Pflicht. Fehlermeldungen ohne Layoutshift |
| Screenshot | Nur auf Klick (Kamera-Icon). Ganze Seite, `data-feedback-mask` und Passwortfelder verdeckt, `data-feedback-ignore` entfernt |
| Markieren | Rechteck und Marker, Rückgängig, Alle löschen, Esc, Strg/Cmd+Z. Markierungen haften beim Scrollen |
| Entwurf | Text, E-Mail, Screenshot und Markierungen bleiben bei Schliessen, Seitenwechsel und Reload erhalten (24 h) |
| Senden | Ladezustand, ein automatischer Wiederholungsversuch mit `Idempotency-Key`, eigene Erfolgs- und Fehlerseite |
| Diagnose | Konsolenfehler, unbehandelte Fehler, fehlgeschlagene Requests und Browserdaten, im Browser redigiert |
| Sprachen und Farbe | `de_CH`, `fr_CH`, `en_CH`, `it_CH`. Projektfarbe Schwarz, Weiss oder Hex mit automatischer Kontrastableitung. Kein Dark Mode |
| Sicherheit | Domain- und IP-Freigabe pro Projekt, Rate Limits, Honeypot, strikte Validierung, Screenshot als BLOB in der Datenbank |
| Barrierefreiheit | WCAG 2.2 AA: Dialog-Semantik, Fokus-Falle, sichtbarer Fokus, `aria-live`, `prefers-reduced-motion` |

## Schnellstart

Voraussetzungen: PHP 8.5, Composer, Node 22, SQLite (lokal) oder MySQL 8 / PostgreSQL 17. Details in [`docs/installation.md`](docs/installation.md).

**1. Backend installieren**

```bash
git clone https://github.com/codebar-ag/feedback.git && cd feedback
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan serve
```

**2. Projekt anlegen**

```bash
php artisan feedback:project:create "Beispiel Website" --origin=https://www.beispiel.ch --origin="https://*.beispiel.ch" --color="#0A7C66"
```

Der Command gibt den öffentlichen Schlüssel und das fertige Init-Snippet aus (gekürzt, vollständig in [`docs/commands.md`](docs/commands.md)):

```
   INFO  Projekt angelegt.

  Name ..................................................... Beispiel Website
  Public Key ................................. fbk_live_MHoE8S6QzLaeMWMYt4GWP4
  Origins ...................... https://www.beispiel.ch, https://*.beispiel.ch
  Farbe ......................... #0A7C66 · Schrift #FFFFFF · Kontrast 5.14:1
Init-Snippet:
  initFeedback({ projectKey: 'fbk_live_MHoE8S6QzLaeMWMYt4GWP4', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' })
```

**3. Widget einbinden**

```bash
npm install @codebar/feedback-widget
```

```ts
import { initFeedback } from '@codebar/feedback-widget';

const feedback = await initFeedback({
  projectKey: 'fbk_live_MHoE8S6QzLaeMWMYt4GWP4',
  endpoint: 'https://feedback.codebar.ch',
  locale: 'de_CH',
});
```

Ohne Build-Pipeline per Script-Tag (die API kann die Datei selbst ausliefern, siehe [`docs/widget-setup.md`](docs/widget-setup.md#script-tag)):

```html
<script async src="https://feedback.codebar.ch/widget/v1/feedback.js" integrity="sha384-…" crossorigin="anonymous"></script>
<script>
  window.addEventListener('load', function () {
    CodebarFeedback.init({ projectKey: 'fbk_live_MHoE8S6QzLaeMWMYt4GWP4', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' });
  });
</script>
```

Initialisiere das Widget möglichst früh, die Konsolen-Erfassung beginnt mit `init()`.

## Widget-Installation im Überblick

| Thema | Kurzfassung |
|---|---|
| Identität | `identity: { id, name, email }` aus dem eingeloggten Bereich, ersetzt das E-Mail-Feld durch «Senden als» |
| Sprache | `locale: 'de_CH' \| 'fr_CH' \| 'en_CH' \| 'it_CH'`, einzelne Texte über `messages` |
| Farbe | kommt aus dem Projekt, `colors.primary` überschreibt sie |
| Datenschutz im Bild | `data-feedback-mask` verdeckt, `data-feedback-ignore` entfernt ein Element |
| Events | `ready`, `open`, `close`, `before_send`, `after_send`, `error` |
| CSP | `connect-src` auf die API, `img-src blob: data:`, beim Script-Tag `script-src` für Datei und Chunk |

Alle Optionen, Framework-Beispiele (Blade, Inertia, Livewire, React, Vue) und Fehlerbehebung: [`docs/widget-setup.md`](docs/widget-setup.md).

## Commands

| Command | Zweck |
|---|---|
| `feedback:project:create {name} --origin=* [--ip=*] [--color=] [--limit=] [--allow-any-origin] [--by=] [--json]` | Projekt anlegen, gibt Schlüssel und Snippet aus |
| `feedback:project:list [--json]` | Alle Projekte mit Farbe, Origins, IPs, offen/erledigt |
| `feedback:project:show {key} [--json]` | Details eines Projekts inklusive Snippet |
| `feedback:project:update {key} [--name=] [--add-origin=*] [--remove-origin=*] [--add-ip=*] [--remove-ip=*] [--color=] [--limit=\|none] [--activate\|--deactivate] [--by=] [--json]` | Freigaben, Farbe, Limit und Status ändern |
| `feedback:project:rotate-key {key} [--by=] [--json]` | Öffentlichen Schlüssel erneuern |
| `feedback:list [--project=] [--open] [--completed] [--limit=20] [--json]` | Neueste Feedbacks |
| `feedback:show {id} [--json]` | Eintrag mit Metadaten, Konsole und Notiz |
| `feedback:export {id} [--path=]` | Schreibt `feedback-{id}.json` und `screenshot.jpg` |
| `feedback:complete {id…} [--by=]` / `feedback:reopen {id…} [--by=]` | Erledigt setzen oder wieder öffnen |
| `feedback:note {id} {note?} [--clear] [--by=]` | Interne Notiz setzen oder entfernen |
| `geoip:update [--json]` | Lokale GeoLite2-Datenbank laden (nur mit `FEEDBACK_GEO=maxmind`) |

Optionen, Ausgaben und Activity-Log: [`docs/commands.md`](docs/commands.md).

## Entwicklung und Tests

```bash
composer check                    # Pint, PHPStan, Pest
npm --prefix widget ci
npm --prefix widget run check     # ESLint, Prettier, Typen, Vitest, Build, Budget, publint, attw
npm --prefix widget run e2e       # Playwright: Chromium, Firefox, WebKit, Mobile
npm --prefix widget run e2e:full  # Vollstack: Laravel + gebautes Widget
```

Mehr in [`docs/testing.md`](docs/testing.md).

## Dokumentation

- [Installation und Betrieb](docs/installation.md)
- [Widget einbinden](docs/widget-setup.md)
- [Commands](docs/commands.md)
- [Konfiguration](docs/configuration.md)
- [Sicherheit und Datenschutz](docs/security.md)
- [Tests](docs/testing.md)
- [Architektur, Entscheide und Annahmen](docs/architecture.md)
- [Konzept](docs/feedback-widget-konzept.md) · [Mockup](docs/feedback-widget-design.html) · [Design-Canvas](https://claude.ai/artifact/VWDZhGKztfygvLRpjWFWST)
- [Changelog](CHANGELOG.md)

## Zu entscheiden

Diese Punkte aus Kapitel 11 des Konzepts sind offen. Sie sind als Konfiguration oder Platzhalter umgesetzt:

| Punkt | Stand heute | Wo änderbar |
|---|---|---|
| Datenbank | MySQL/MariaDB und PostgreSQL werden unterstützt und in CI getestet, lokal SQLite | `DB_CONNECTION` |
| Standort aus der IP | aus (`none`). Möglich: `cloudflare` (Header) oder `maxmind` (lokale GeoLite2-Datenbank) | `FEEDBACK_GEO` |
| Standardfarbe | neutral Schwarz, wenn ein Projekt keine Farbe hat | `FEEDBACK_DEFAULT_COLOR` |
| npm-Scope | Platzhalter `@codebar/feedback-widget` | `widget/package.json` |
| API-Domain | Platzhalter `https://feedback.codebar.ch` in Doku und Snippets | `FEEDBACK_ENDPOINT` |
| Rate Limits, Mindestzeit, Aufbewahrung | bewusst ohne Werte (aus) | `FEEDBACK_LIMIT_*`, `FEEDBACK_MIN_ELAPSED_MS`, Datenbank |
| Dependency Review in CI | braucht ein öffentliches Repository oder GitHub Code Security | Repo-Variable `DEPENDENCY_REVIEW=true` |
| npm-Veröffentlichung | Release-Workflow braucht das Secret `NPM_TOKEN`, Provenance setzt ein öffentliches Repository voraus | `.github/workflows/release.yml` |
| Userback-Code `v1.js` | war für die Analyse nicht abrufbar | Konzept 1.1 |

## Lizenz

MIT, siehe [LICENSE](LICENSE).
