# Tests

Alles muss grün sein, bevor etwas gemerged wird. Die Zuordnung der Abnahmematrix (Konzept Kapitel 13) zu den Tests steht unten.

## Backend (Pest 5)

```bash
composer check            # Pint --test, PHPStan (Level 9), Pest parallel
composer test             # nur Pest, parallel
composer test:coverage    # Pest mit Coverage, Minimum 90 % (braucht pcov oder Xdebug)
composer format           # Pint anwenden
```

Standard ist SQLite in-memory. Dieselbe Suite läuft gegen MySQL und PostgreSQL, indem du die Verbindung setzt:

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=feedback_test DB_USERNAME=postgres DB_PASSWORD=secret ./vendor/bin/pest
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=feedback_test DB_USERNAME=root DB_PASSWORD=secret ./vendor/bin/pest
```

| Ordner | Inhalt |
|---|---|
| `tests/Unit` | `OriginMatcher`, `IpMatcher`, `ProjectColor` (gleiche Kontrastwerte wie das Widget), `ClientData`, `UserAgentParser`, Public-Key-Generator, `Locale`, Screenshot-Validator, IP-Anonymisierung, Geo, JSON-Regel |
| `tests/Feature` | Endpoint (201, 200 bei Idempotenz, 403, 404, 413, 422 je Regel, 429, Honeypot, Mindestzeit, ungültiges Bild), CORS-Preflight, `GET …/config`, `/up`, alle Commands, Activity-Log ohne Personendaten, BLOB-Roundtrip, `geoip:update` |
| `tests/Arch` | `strict_types`, keine Debug-Reste, `env()` nur in `config/`, Controller ohne `DB`, Support-Klassen `final readonly`, keine Auth-/Session-/View-Abhängigkeiten |

## Widget (Vitest)

```bash
npm --prefix widget ci
npm --prefix widget run test            # Vitest (jsdom, fake-indexeddb)
npm --prefix widget run test:coverage
npm --prefix widget run check           # Lint, Typen, Vitest, Build, size-limit, publint, attw
```

Abgedeckt: Zustandsautomat (jeder Übergang), Formularvalidierung, i18n (Vollständigkeit und Konzepttexte), Theme-Ableitung und Tokens, Redaktion, Ringpuffer (50 Einträge, 1 KB, 32 KB), Konsolen- und Netzwerk-Erfassung, API-Client (Wiederholung, Idempotency-Key, Fehlerzuordnung), Entwurf (Speichern, Laden, TTL, Privatmodus, Löschen), Koordinaten und Einbrennen, Masken, Pixelbudget, Fokus-Falle, Widget-Integration.

Das Grössenbudget prüft `size-limit` (`widget/.size-limit.json`): Kern ≤ 20 KB gzip (ESM und IIFE), Screenshot-Chunk separat.

## Widget (Playwright)

```bash
npm --prefix widget exec -- playwright install --with-deps chromium firefox webkit
npm --prefix widget run build
npm --prefix widget run e2e
```

Läuft gegen die gebauten Dateien (`widget/dist`) und den Mock-Server [`widget/tests/e2e/server.ts`](../widget/tests/e2e/server.ts) mit den Fixture-Seiten in `widget/fixtures/`. Jeder Test hat eine eigene Mock-Session (`/s/{sid}/…`), die Tests laufen parallel. Projekte: Chromium, Firefox, WebKit, dazu `mobile-chrome` (Pixel 7) und `mobile-safari` (iPhone 15) für `mobile.spec.ts`.

Manuell ausprobieren: `node --experimental-strip-types widget/tests/e2e/server.ts` und `http://localhost:4173/fixtures/index.html?identity=1&color=%230A7C66` öffnen.

## Vollstack-E2E (`e2e/`)

```bash
composer install
npm --prefix widget run build
npm --prefix widget run e2e:full
```

Startet Laravel mit dem PHP-Server und einer frischen SQLite-Datenbank (`e2e/.tmp/`), legt per `feedback:project:create` zwei Projekte an (eines mit IP-Sperre) und lädt das gebaute Widget auf einer Fixture-Seite (`e2e/fixtures/`). Geprüft werden: Feedback mit Screenshot, Konsolenfehler und Browserdaten in der Datenbank (`feedback:show --json`), erlaubte (`http://localhost:4180`) gegen fremde Origin (`http://127.0.0.1:4180`), IP-Sperre, Honeypot, Idempotenz und `feedback:complete`.

## CI

| Workflow | Inhalt |
|---|---|
| `tests.yml` | PHP 8.5 × SQLite, MySQL 8.4, PostgreSQL 17: Pint, PHPStan, Pest mit Coverage ≥ 90 % |
| `widget.yml` | Node 22: Lint, Typen, Vitest mit Coverage, Build, size-limit, publint, attw |
| `e2e.yml` | Playwright (3 Browser + Mobile) und Vollstack-E2E, Report und Trace bei Fehlern |
| `security.yml` | `composer audit`, `npm audit --omit=dev`, Dependency Review (wöchentlich und bei PRs) |
| `release.yml` | Tag `v*`: `npm run check`, `npm publish --provenance`, GitHub-Release mit Changelog und SRI-Hash |

## Abnahmematrix → Tests

| Board / Quelle | Funktion | Tests |
|---|---|---|
| 1 Customer Journey | Launcher bis Bestätigung | `widget/tests/e2e/journey.spec.ts` (je Browser), Script-Tag-Variante |
| 2 Panel auf der Seite | Position, Grösse, kein Überlappen | `panel.spec.ts` «panel geometry» |
| 3 Markieren | Abdunkeln, Toolbar, Rechteck, Marker | `screenshot.spec.ts`, Vitest `annotate.test.ts` (Koordinaten) |
| 4 Alle Funktionen | Launcher, Kopfzeile, Identität, Feedback, Screenshot, Entwurf, Senden, Farbe | `panel.spec.ts`, `form.spec.ts`, `screenshot.spec.ts`, `draft.spec.ts`, `send.spec.ts`, `colors.spec.ts` |
| 5 Markierwerkzeuge | Zwei Werkzeuge, Undo, Löschen, Tastatur | `screenshot.spec.ts` «rectangle, marker, undo …», Vitest `annotate.test.ts` |
| 6 Screenshot | leer, Erfassung, Vorschau, Fehler, Datenschutz, ganze Seite | `screenshot.spec.ts` (Masken und Bildgrösse per Pixelprüfung), Vitest Masken, `e2e/feedback.e2e.ts` (Bild in DB) |
| 7 Fehler und Erfolg | eigene Seiten, gleiche Höhe, ein Button | `status.spec.ts`, `send.spec.ts` (API-Mock) |
| 8 Zustände | kein Layoutshift, «Senden als», Ladezustand, Launcher | `form.spec.ts` (Boundingbox, `aria-busy`), `panel.spec.ts` |
| 9 Projektfarben | Schwarz, Weiss, Custom, Schrift | Vitest `theme.test.ts`, Pest `ProjectColorTest`, `colors.spec.ts` (Computed Style) |
| 10 Sprachen | vier Sprachen | `i18n.spec.ts`, Vitest `i18n.test.ts` |
| 11 Mobile | Bottom-Sheet, Touch, Status-Seiten | `mobile.spec.ts` (Mobile Chrome, Mobile Safari) |
| 12 Farben und Typografie | Tokens, Kontraste, 13/15/18 | Vitest `theme.test.ts` (Tokens), `form.spec.ts`, `status.spec.ts` (Computed Style) |
| Mockup | Entwurf nach Schliessen/Reload/Navigation | `draft.spec.ts` |
| Mockup | Pflichtfelder, kein Namensfeld | `form.spec.ts`, Pest `StoreFeedbackTest` (422 je Regel) |
| Konzept 5.2, 5.4 | Origin, IP, Idempotenz, Limits, Honeypot | Pest `StoreFeedbackTest`, `CorsTest`, `RateLimitTest`, `e2e/feedback.e2e.ts` |
| Konzept 5.6 | Commands, Activity-Log | Pest `ProjectCommandsTest`, `FeedbackCommandsTest`, `ActivityLogTest` |
| Konzept 4.7 | Konsole und Browserdaten mit Redaktion | Vitest `diagnostics.test.ts`, `redact.test.ts`, `send.spec.ts`, `e2e/feedback.e2e.ts` |
| Konzept 4.9 | Barrierefreiheit | `a11y.spec.ts` (axe-core in jedem Zustand, Tab-Reihenfolge, Fokus-Falle, `aria-live`, reduced motion) |
| Konzept 12.5 | Isolation | `isolation.spec.ts` |
