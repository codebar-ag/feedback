# Changelog

All notable changes to the API and the widget are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-16

### Added

- Laravel 13 API with `POST /v1/projects/{key}/feedback`, `GET /v1/projects/{key}/config` and `GET /up`.
- Origin and IP allowlists per project, per-project CORS, rate limits with safe defaults, honeypot, minimum fill time and idempotency via `Idempotency-Key`.
- Screenshots stored in the database (`bytea`, `LONGBLOB`, `blob`), activity log without personal data, full IPv4/IPv6 address.
- Artisan commands `feedback:project:create|list|show|update|rotate-key` and `feedback:list|show|export|complete|reopen` with `--json`.
- Oh Dear health checks, read replica support and an idempotent `DatabaseSeeder` for deployments.
- Widget `@codebar-ag/feedback-widget` (ESM and script tag): launcher, form, identity, full-page screenshot with rectangle and highlighter, persistent drafts, diagnostics with redaction, four languages, project color with contrast derivation, accessibility and mobile bottom sheet.
- Tests with Pest, Vitest and Playwright (Chromium, Firefox, WebKit, mobile) and a full-stack E2E suite.

[Unreleased]: https://github.com/codebar-ag/feedback/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/codebar-ag/feedback/releases/tag/v1.0.0
