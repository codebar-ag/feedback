# Changelog

Alle relevanten Änderungen an API und Widget. Format nach [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionierung nach [SemVer](https://semver.org/lang/de/).

## [Unreleased]

## [1.0.0] - 2026-10-16

### Added

- Laravel-13-API mit `POST|OPTIONS /v1/projects/{publicKey}/feedback`, `GET /v1/projects/{publicKey}/config` und `GET /up`.
- Domain- und IP-Freigabe pro Projekt, dynamisches CORS, Rate Limits ohne Standardwerte, Honeypot und Mindestzeit, Idempotenz über `Idempotency-Key`.
- Datenmodell mit Screenshots als BLOB (`LONGBLOB`, `bytea`, `blob`) und Activity-Log ohne Personendaten.
- Artisan-Commands `feedback:project:create|list|show|update|rotate-key`, `feedback:list|show|export|complete|reopen|note` und `geoip:update`, jeweils mit `--json` wo sinnvoll.
- Widget `@codebar/feedback-widget` (ESM und IIFE `CodebarFeedback`): Launcher, Panel, Formular mit Identität, Full-Page-Screenshot als lazy Chunk, Markieren mit Rechteck und Marker, persistenter Entwurf, Diagnose mit Redaktion, vier Sprachen, Projektfarbe mit Kontrastableitung, Barrierefreiheit, Mobile.
- Tests: Pest (Unit, Feature, Arch) für SQLite, MySQL und PostgreSQL, Vitest, Playwright (Chromium, Firefox, WebKit, Mobile) und Vollstack-E2E.
- GitHub Actions für Tests, Widget, E2E, Security und Release, Dependabot.

### Removed

- Starter-Kit-Ballast (Nova, SSO, Auth, Views, Vite/Tailwind im Wurzelverzeichnis, Dusk und weitere), siehe [`docs/architecture.md`](docs/architecture.md#was-aus-dem-starter-kit-entfernt-wurde-und-warum).

[Unreleased]: https://github.com/codebar-ag/feedback/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/codebar-ag/feedback/releases/tag/v1.0.0
