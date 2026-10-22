# Changelog

All notable changes to the API and the widget are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Feedback pages behind signed, expiring links with all details, console and network log and the screenshot (inline and download).
- `feedback:link {id}` creates team links that can complete and reopen the entry (`--by` is logged, `--read-only`, `--expires`).
- The API returns a read-only share link after sending (`FEEDBACK_SHARE_LINK_MINUTES`, `0` turns it off); the widget shows it on the thank you screen with a copy button, `after_send` receives it as `url`.
- Close button in marking mode (top right, bottom right on phones), same as Esc.

### Fixed

- Screenshots of pages using Alpine, Vue or other framework attributes (`:class`, `x-on:click`) were blank.

## [1.0.0] - 2026-10-16

Initial release.

- Laravel 13 API: projects with origin and IP allowlists, rate limits, honeypot, idempotency, screenshots stored in the database, Artisan commands, Oh Dear health checks.
- Widget `@codebar-ag/feedback-widget`: full-page screenshots with rectangle and highlighter, persistent drafts, diagnostics with redaction, identity, four languages, project colors, WCAG 2.2 AA.

[Unreleased]: https://github.com/codebar-ag/feedback.codebar.ch/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/codebar-ag/feedback.codebar.ch/releases/tag/v1.0.0
