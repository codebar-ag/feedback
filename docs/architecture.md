# Architektur

## Überblick

```
┌─────────────── Website / Web-App (beliebig viele) ───────────────┐
│  npm: @codebar/feedback-widget  (oder Script-Tag, IIFE)          │
│  ┌───────────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │ UI (Shadow    │  │ Annotation   │  │ Diagnostics           │ │
│  │ DOM, i18n)    │  │ Canvas-Layer │  │ Console/Errors/Meta   │ │
│  └──────┬────────┘  └──────┬───────┘  └──────────┬────────────┘ │
│         └─────────┬────────┴─────────────────────┘              │
│                   │  Lazy chunk: Screenshot (modern-screenshot) │
└───────────────────┼─────────────────────────────────────────────┘
                    │ HTTPS, multipart/form-data
                    ▼  POST /v1/projects/{publicKey}/feedback
┌──────────────────────── Feedback-API (Laravel 13) ───────────────┐
│ ResolveProject → ApplyProjectCors → ValidatePostSize             │
│ → EnforceOriginPolicy → EnforceIpPolicy → throttle:feedback      │
│ → StoreFeedbackRequest → StoreFeedback (Transaktion)             │
│ → Event FeedbackReceived                                         │
└───────────────┬──────────────────────────────────────────────────┘
                ▼
   feedback_projects · feedback_entries · feedback_screenshots (BLOB) · activity_log

   Verwaltung: php artisan feedback:*   (keine HTTP-Verwaltung)
```

| Pfad | Inhalt |
|---|---|
| `app/Http/Middleware` | `ResolveProject`, `ApplyProjectCors`, `EnforceOriginPolicy`, `EnforceIpPolicy` |
| `app/Http/Requests/StoreFeedbackRequest.php` | Validierung, Honeypot, Mindestzeit, 413 |
| `app/Actions` | `StoreFeedback` (Transaktion, Idempotenz), `RotatePublicKey` |
| `app/Support` | `OriginMatcher`, `IpMatcher`, `ProjectColor`, `ClientData`, `UserAgentParser`, `ScreenshotValidator`, `PublicKeyGenerator`, `IpAnonymizer` (alle `final readonly`) |
| `app/Geo` | `NullGeoLocator`, `CloudflareGeoLocator`, `MaxMindGeoLocator` |
| `app/Console/Commands` | `feedback:*`, `geoip:update` |
| `widget/src` | Widget, Modulstruktur nach Konzept 4.11.1 |
| `e2e/` | Vollstack-Tests |

Routen (`routes/api.php`, ohne `/api`-Präfix): `POST|OPTIONS /v1/projects/{publicKey}/feedback`, `GET /v1/projects/{publicKey}/config`, `GET /up`. Laravels globale `HandleCors` ist deaktiviert, `ValidatePostSize` läuft innerhalb der Gruppe, damit auch eine 413-Antwort CORS-Header trägt. Alle Fehler werden als `{"error":{"code":"…"}}` gerendert, ohne interne Details.

## Zustandsautomat (Widget)

| Zustand | Sichtbar | Übergänge |
|---|---|---|
| `closed` | Launcher (Funkeln) | `OPEN` → `form` |
| `form` | Panel mit Formular, Launcher als Kreuz | `CLOSE` → `closed`, `CAPTURE` → `capturing`, `EDIT` → `annotating`, `SUBMIT` → `sending` |
| `capturing` | Panel und Launcher `visibility:hidden`, Abdunklung, Spinner | `CAPTURED` → `annotating`, `CAPTURE_FAILED` → `form` |
| `annotating` | Screenshot in Dokumentkoordinaten, Abdunklung, Zeichenfläche, Toolbar | `DONE` → `form`, `CANCEL` (Esc) → `form` |
| `sending` | Formular, Button mit Spinner, `aria-busy` | `SENT` → `success`, `FAILED` → `error`, `INVALID` (422, `before_send` → false) → `form` |
| `success` | Erfolgsseite | `CLOSE` (Button oder nach 3 s) → `closed` |
| `error` | Fehlerseite | `BACK` → `form`, `CLOSE` → `closed` |

Andere Übergänge werden ignoriert, darum wirken `open()`/`close()` der Host-Seite während Screenshot, Markieren und Senden nicht. Implementierung und Test: `widget/src/store.ts`, `widget/tests/unit/store.test.ts`.

## Datenmodell

- **`feedback_projects`**: `id` (ULID), `name`, `public_key` (unique), `theme` (JSON `{"primary": "#…"}`), `allowed_origins` (JSON), `allowed_ips` (JSON), `is_active`, `daily_limit`, Zeitstempel.
- **`feedback_entries`**: alle Felder aus Konzept 5.5, dazu `elapsed_ms`. Unique `(project_id, client_request_id)` für die Idempotenz, Indizes `(project_id, created_at)` und `(project_id, completed_at)`.
- **`feedback_screenshots`**: `entry_id` (PK, FK mit `ON DELETE CASCADE`), `mime`, `width`, `height`, `bytes`, `sha256`, `data` (MySQL `LONGBLOB`, PostgreSQL `bytea`, SQLite `blob`). Eigene Tabelle, damit Listen nie BLOBs laden. Geschrieben wird als Stream (PDO `PARAM_LOB`), was `bytea` verlangt.
- **`activity_log`**: Tabelle von `spatie/laravel-activitylog`, mit ULID-Morphs.

## Entscheide

Die Entscheide aus dem Konzept (Kapitel 0 und 11) gelten unverändert: clientseitiger Full-Page-Screenshot, BLOB in der Datenbank, Vanilla TypeScript mit Shadow DOM, Verwaltung nur per Artisan, vier Sprachen mit Du-Anrede, Identität ohne Signatur, nur Rechteck und Marker, Projektfarbe Schwarz/Weiss/Hex, kein Dark Mode, Entwurf 24 h, Activity-Log, keine festgelegten Werte für Limits und Aufbewahrung.

## Was aus dem Starter-Kit entfernt wurde und warum

Das Repository war das codebar-Starter-Kit (`codebar-ag/laravel-start`, Laravel 13). Das Konzept braucht davon nur den Laravel-Kern (12.1). Entfernt wurde:

| Entfernt | Grund |
|---|---|
| Laravel Nova (Ressourcen, Policies, Provider, Lang-Dateien, privates Composer-Repository) | Keine Verwaltungsoberfläche |
| Microsoft Entra SSO, Auth-Routen, Login-/Reset-Requests, Jobs, Listener, Notifications, Auth-Views | Keine Anmeldung |
| `User`, `Role`, `PasswordResetToken`, `Activity`-Modell, Factories, Seeder, `spatie/laravel-permission` | Keine Benutzer, Verwaltung per Artisan |
| Migrationen `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, `notifications`, Permission-Tabellen | Nicht benötigt, kein Queue-Worker. Rate Limiting nutzt den konfigurierten Cache-Store |
| `resources/views`, `resources/js` (inkl. altem Userback-Loader), `resources/css`, Bilder | Keine Web-Oberfläche |
| Wurzel-`package.json`, `package-lock.json`, Vite, Tailwind, Alpine, `.nvmrc` | Frontend-Tooling liegt nur in `widget/` |
| `routes/web.php`, Dashboard, Sitemap, Fehlerseiten | API hat nur `/v1/…` und `/up` |
| Helper-Klassen (Bank, Date, Device, File, Markdown, Money, Number, Phone) und Facades, Traits, Enums des Kits | Nicht benötigt |
| `spatie/laravel-health` und Checks, `spatie/laravel-csp`, `codebar-ag/laravel-feature-policy`, `spatie/laravel-honeypot`, `spatie/laravel-flash`, `spatie/laravel-sitemap`, Security-Advisories-Check | Health ist `/up`, CSP/Feature-Policy betreffen HTML-Seiten, der Honeypot ist ein eigenes Feld des Widgets |
| Lasso, Nightwatch, Cloudinary- und S3-Flysystem, Postmark, `symfony/http-client`, Tinker, Ray | Keine Assets, kein Objektspeicher, kein Mailversand |
| Laravel Dusk, `phpunit.dusk.xml.dist`, `.env.dusk.example`, Browser-Tests, Beispiel-Tests (Core, Helper, Frontend) | Ersetzt durch Pest 5 und Playwright (12.5) |
| `.env.testing`, `.env.testing.ci` | Testwerte stehen in `phpunit.xml` |
| `packages/coding-guidelines`, Laravel Boost (`boost.json`, `AGENTS.md`) | Nicht Teil des Produkts |
| Starter-Workflows (Pest, Pint, Larastan, Dusk, Coverage, Lasso, Dependabot-Automerge, Release) | Ersetzt durch die Workflows aus 12.6 |
| `public/manifest.json`, `public/favicon.svg` | Keine Web-App |

Behalten: Laravel-Kern, `artisan`, `public/index.php`, `.htaccess`, `.editorconfig`, `.gitattributes`, Pint, PHPStan/Larastan (jetzt Level 9), `spatie/laravel-activitylog`, MIT-Lizenz. Docker-, Sail- oder DevContainer-Dateien gab es nicht.

## Annahmen

Wo Konzept und Mockup schweigen oder sich widersprechen, gilt das Konzept. Für echte Lücken habe ich die kleinste vernünftige Annahme getroffen:

1. **Name ohne Identität:** `name` ohne `identity` wird mit 422 abgelehnt (`prohibited`). Mit Identität kommen Name und E-Mail aus der Identität.
2. **IP-Sperre:** antwortet mit demselben Body wie die Origin-Sperre (`403 origin_not_allowed`, Konzept 5.2).
3. **Unbekanntes Projekt und fremde Origin:** ohne CORS-Header kann der Browser den Status nicht lesen. Das Widget zeigt die Fehlerseite und meldet `network_error` (nach einem Wiederholungsversuch).
4. **Zusätzliche Texte:** `shot_busy` (Ladeanzeige), `shot_error` (Hinweis in der Tools-Zeile), `error_shot` (Pflicht-Screenshot), `tools_label` (Toolbar-Label) in allen vier Sprachen.
5. **Notiz:** Das Konzept protokolliert Notizänderungen, nennt aber keinen Command. Ergänzt: `feedback:note`.
6. **`elapsed_ms`** wird zusätzlich als Spalte gespeichert.
7. **Pixelgrenze serverseitig:** 25 Mio. Pixel, wie der Desktop-Standard des Widgets.
8. **Panel beim Markieren:** ist auf allen Grössen ausgeblendet (Konzept 4.2 Punkt 4), das Mockup zeigt es auf Desktop noch.
9. **Zeichenfläche:** Statt eines Canvas in Dokumentgrösse (Canvas-Limits, Safari) zeigt die Ebene den Screenshot in Dokumentkoordinaten und zeichnet auf ein Canvas in Viewport-Grösse. Markierungen bleiben in Dokumentkoordinaten und haften beim Scrollen. Zwei Finger verschieben auf Touch-Geräten die Seite.
10. **Masken:** werden nach dem Rendern als deckende Blöcke (`#C9D1DC`) an den Positionen der Elemente auf das Bild gemalt, also vor dem Speichern im Entwurf.
11. **Esc:** im Formular und auf der Erfolgsseite schliesst das Panel, auf der Fehlerseite geht es zurück zum Formular. Das X schliesst auch die Fehlerseite (Entwurf bleibt).
12. **Fokus beim Öffnen:** springt ins Feedback-Feld (wie im Mockup), beim Wiederherstellen eines offenen Entwurfs nicht.
13. **Screenshot im Entwurf:** wird als `ArrayBuffer` statt als `Blob` in IndexedDB gespeichert (Blobs in IndexedDB sind in einigen Safari-Versionen unzuverlässig).
14. **Leerer Entwurf:** Ist das Panel zu und nichts eingegeben, wird der Entwurf gelöscht statt leer gespeichert.
15. **Limits:** drei Stufen (pro IP und Projekt pro Minute, pro Projekt pro Minute, Tageslimit pro Projekt). Alle ohne Standard. `GET …/config` ist nicht gedrosselt.
16. **`Cache-Control` der Config:** 600 Sekunden, passend zum 10-Minuten-Cache des Widgets.
17. **`/up`:** eigene Route mit JSON `{"status":"ok"}` und Datenbankprüfung (503 bei Fehler), ohne Details.
18. **`TRUSTED_PROXIES`:** Standard ist «keinem Proxy vertrauen» (das Kit hatte `*`).
19. **Storage-Routen:** Laravels `serve` für die lokale Disk ist aus, damit keine toten Routen entstehen.
20. **ULIDs:** Laravel erzeugt sie in Kleinbuchstaben.
21. **TypeScript 6.0:** statt 7, weil `typescript-eslint` TypeScript 7 noch nicht unterstützt.
22. **Script-Tag:** Der Screenshot-Chunk wird relativ zur Script-URL geladen. Bei CSP muss `script-src` den Host erlauben.
23. **Dependency Review:** läuft nur, wenn das Repository öffentlich ist oder die Repo-Variable `DEPENDENCY_REVIEW=true` gesetzt ist (braucht GitHub Code Security).
24. **CI-Datenbanken:** MySQL 8.4 (aktuelle LTS der Reihe 8) und PostgreSQL 17.
25. **Position:** zusätzlich `bottom-left` unterstützt (Konzept: «Position konfigurierbar»).
