# Übergabe an Claude Code: codebar Feedback Widget

> Kopiere alles unterhalb der Linie in Claude Code (im Wurzelverzeichnis von `codebar-ag/feedback`).

---

Du baust das Projekt **codebar Feedback** im Repository `https://github.com/codebar-ag/feedback` (Starter-Kit) komplett auf. Sprache der Doku: Deutsch (Du-Anrede), Code und Commits: Englisch.

## Verbindliche Quellen (zuerst vollständig lesen)

1. `docs/feedback-widget-konzept.md` ist die **verbindliche Spezifikation**. Besonders: 4.2 (UX), 4.11 (Widget-Umsetzung), 5 (Backend), 12 (Repository, Tests, CI, Doku, Definition of Done), 13 (Abnahmematrix).
2. `docs/feedback-widget-design.html` ist das interaktive Mockup (Referenz für Layout, Texte, Verhalten).
3. Design-Canvas: https://claude.ai/artifact/VWDZhGKztfygvLRpjWFWST (visuelle Referenz aller Screens).

Wenn Konzept und Mockup abweichen, gilt das Konzept. Bei echten Lücken: kleinste vernünftige Annahme treffen und in `docs/architecture.md` unter «Annahmen» festhalten, nicht nachfragen.

## Schritt 0: Starter-Kit prüfen und aufräumen

- Repo klonen, Laravel-Version, Frontend-Stack, Auth, Beispiele und Tests inspizieren.
- Entferne alles, was das Konzept nicht braucht (Konzept 12.1): Auth/Registrierung/Profil, `users`/`sessions`/`password_reset_tokens`, Views, Wurzel-`package.json`, Vite/Tailwind/Livewire/Inertia, Welcome-Seite, Beispiel-Tests, ungenutzte Docker-Dateien und Dienste.
- Behalte: Laravel-Kern, Pest, Pint (PHPStan/Larastan ergänzen, falls fehlend), `.editorconfig`, Lizenz.
- Dokumentiere das Entfernte in `docs/architecture.md`.
- Lege Konzept und Mockup unter `docs/` ab, falls noch nicht vorhanden.

## Schritt 1: Backend (Laravel 13, PHP 8.5)

Setze Konzept 5 und 12.3 vollständig um: Migrations (`feedback_projects`, `feedback_entries`, `feedback_screenshots` als LONGBLOB bzw. `bytea`/`blob`, `activity_log`), Modelle mit ULID und Activitylog, Middleware-Kette (ResolveProject, ApplyProjectCors, EnforceOriginPolicy, EnforceIpPolicy, Throttle), `StoreFeedbackRequest`, `StoreFeedback`-Action mit Idempotenz, Endpoints `POST/OPTIONS /v1/projects/{publicKey}/feedback`, `GET …/config`, `GET /up`, alle `feedback:*` und `geoip:update` Commands (mit `--json`), `config/feedback.php`. Verwaltung **nur per Artisan**. Keine Werte für Limits erfinden (Konfiguration ohne Standard).

## Schritt 2: Widget (`widget/`)

Setze Konzept 4.2 bis 4.11 vollständig um: Vanilla TypeScript (strict), Shadow DOM, Vite (ESM + IIFE `CodebarFeedback`), lazy Chunk für den Screenshot (`modern-screenshot`), Zustandsautomat, persistenter Entwurf (sessionStorage + IndexedDB, 24 h), Markieren (nur Rechteck und Marker), Diagnose mit Redaktion, vier Sprachen (`de_CH`, `fr_CH`, `en_CH`, `it_CH`), Projektfarbe (Schwarz/Weiss/Custom) mit Kontrastableitung, Barrierefreiheit, Mobile. Harte UX-Regeln, die nie verletzt werden dürfen:

- Kein Dark Mode, keine Hilfetexte, keine Info-Seite oder -Button.
- Kopfzeile schlank: Icon links, X rechts, kein Titel, kein Minimieren.
- Mit Identität: Label «Senden als» + Badge (Name, darunter E-Mail), kein E-Mail-Feld. Ohne Identität: **nur** E-Mail-Feld, kein Namensfeld. E-Mail (nur ohne Identität) und Feedback sind Pflicht und mit `*` markiert.
- Label «Tools» mit Kamera-Icon-Button. Nie automatischer Screenshot. Nach der Aufnahme nur Vorschau + Stift + Papierkorb, keine Texte.
- Fehlermeldungen reservieren eine 16-px-Zeile: **kein Layoutshift**.
- Erfolgs- und Fehlerseite im gleichen Container, **gleiche Höhe wie das Formular**, Inhalt zentriert. Fehlerseite nur mit «Zurück zum Formular».
- Schliessen mit X behält den Entwurf, auch über Seitenwechsel und Reload.
- Typografie: Labels/Fehler 13 px, Text/Felder/Buttons 15 px, Statustitel 18 px.

## Schritt 3: Tests (alles muss grün sein)

Setze Konzept 12.5 und die Matrix in Kapitel 13 um, **jede Zeile mit mindestens einem automatisierten Test**:

- **Pest 5** (Unit, Feature, Arch) inkl. Matrix SQLite/MySQL/PostgreSQL, Coverage ≥ 90 %.
- **Vitest** für das Widget.
- **Playwright** für das Widget (Chromium, Firefox, WebKit, Mobile) und Vollstack-E2E (`e2e/`, Laravel + gebautes Widget), inkl. axe-core.
- Prüfe vor jedem Abschluss lokal: `composer check`, `npm --prefix widget run check`, `npm --prefix widget run e2e`, `npm --prefix widget run e2e:full`. Behebe Fehler an der Ursache, **deaktiviere oder lockere keine Tests**.

## Schritt 4: GitHub Actions und Badges

Workflows gemäss 12.6: `tests.yml`, `widget.yml`, `e2e.yml`, `security.yml`, `release.yml`, plus `dependabot.yml`. Minimale Permissions, `concurrency`, gepinnte Versionen. Badges im README (Tests, Widget, E2E, Security, PHP, Laravel, npm, Lizenz).

## Schritt 5: Dokumentation

Gemäss 12.7: `README.md` mit Badges, Funktionsüberblick, **Schnellstart und Installationsanleitung des Widgets**, **Command-Referenz**, Verweisen auf `docs/installation.md`, `docs/widget-setup.md`, `docs/commands.md`, `docs/configuration.md`, `docs/security.md`, `docs/testing.md`, `docs/architecture.md`, `CHANGELOG.md`. Alle Beispiele müssen real lauffähig sein und werden einmal ausgeführt.

## Arbeitsweise

- Arbeite in Etappen mit je einem Commit (Conventional Commits): Aufräumen → Backend → Backend-Tests → Widget-Kern → Entwurf/Screenshot/Markieren → Widget-Tests → E2E → CI → Doku.
- Nutze `TaskCreate`-Liste mit Prüfschritt am Ende jeder Etappe.
- Lege keine Zahlenwerte für Aufbewahrung oder Rate Limits fest. Offene Punkte aus Konzept Kapitel 11 (DB-Engine, Geo-Quelle, Standardfarbe, npm-Scope, API-Domain) als Konfiguration/Platzhalter umsetzen und im README als «Zu entscheiden» auflisten.
- Kein Secret im Repo. Öffentlicher Schlüssel gilt als öffentlich.

## Definition of Done

Konzept 12.8: alle Tests lokal und in GitHub Actions grün (Backend-Matrix, Widget, E2E in drei Browsern, Security), jede Zeile der Abnahmematrix automatisiert getestet, README mit Badges + Widget-Setup + Commands vollständig, Starter-Kit-Ballast entfernt, Lint/Analyse ohne Befunde, Bundle ≤ 20 KB gzip. Berichte am Ende kurz: was gebaut wurde, Teststatus, offene Punkte.
