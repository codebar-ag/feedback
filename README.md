<h1 align="center">codebar Feedback</h1>

<p align="center">
  A self-hosted feedback widget for websites and web apps.<br>
  Visitors describe the problem, mark it on a full-page screenshot and send it. Browser data and console errors come along automatically.
</p>

<p align="center">
  <a href="https://github.com/codebar-ag/feedback/actions/workflows/tests.yml"><img src="https://github.com/codebar-ag/feedback/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://github.com/codebar-ag/feedback/actions/workflows/e2e.yml"><img src="https://github.com/codebar-ag/feedback/actions/workflows/e2e.yml/badge.svg" alt="E2E"></a>
  <a href="https://www.npmjs.com/package/@codebar-ag/feedback-widget"><img src="https://img.shields.io/npm/v/@codebar-ag/feedback-widget?label=npm" alt="npm"></a>
  <img src="https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white" alt="PHP 8.5">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT License"></a>
</p>

<p align="center">
  <img src=".github/screenshots/form.png" width="250" alt="Feedback form">
  &nbsp;
  <img src=".github/screenshots/identity.png" width="250" alt="Signed-in user with a screenshot attached">
  &nbsp;
  <img src=".github/screenshots/success.png" width="250" alt="Confirmation">
</p>

## Features

- **Full-page screenshots** on demand, with a rectangle and a highlighter to mark the problem
- **Privacy first**: mask sensitive elements with `data-feedback-mask`, password fields are always hidden, console logs are redacted in the browser
- **Drafts survive** closing the panel, navigating and reloading the page
- **Diagnostics**: console errors, failed requests, browser, OS, viewport and page size
- **Identity**: pass the signed-in user and the email field becomes "Sending as"
- **Four languages** (`de_CH`, `fr_CH`, `en_CH`, `it_CH`) and a project color with automatic contrast
- **Accessible** (WCAG 2.2 AA), isolated in a Shadow DOM, 18 KB gzip, no runtime dependencies
- **Your data, your server**: a small Laravel API with origin and IP allowlists, rate limits and a honeypot. Screenshots are stored in your database

## How it works

1. The widget runs on your website, in a Shadow DOM that leaves your styles untouched.
2. A visitor writes feedback and can attach a screenshot of the whole page. In marking mode they draw rectangles or highlight text:

<p align="center">
  <img src=".github/screenshots/annotate.png" width="720" alt="Marking mode with toolbar, rectangle and highlighter">
</p>

3. The widget sends message, screenshot and diagnostics to your API: `POST /v1/projects/{key}/feedback`.
4. The API checks origin, IP and rate limits and stores everything in your database. You work through the feedback with Artisan commands.

The repository contains both parts: the **API**, a Laravel 13 app in the root folder, and the **widget** in [`widget/`](widget), published as [`@codebar-ag/feedback-widget`](https://www.npmjs.com/package/@codebar-ag/feedback-widget).

## Quick start

**1. Install the API**

```bash
git clone https://github.com/codebar-ag/feedback.git && cd feedback
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan serve
```

**2. Create a project**

```bash
php artisan feedback:project:create "Example Website" --origin=https://www.example.com --color="#0A7C66"
```

```
   INFO  Project created.

  Name ............................................................. Example Website
  Public Key ....................................... fbk_live_92T7QQB6fj6Zb37jBGzrDI
  Active ....................................................................... yes
  Origins .................................................. https://www.example.com
  IPs ............................................................. (no restriction)
  Color ................................... #0A7C66 · text #FFFFFF · contrast 5.14:1
  Daily limit ........................................................... (no limit)
```

**3. Add the widget to your website**

```bash
npm install @codebar-ag/feedback-widget
```

```ts
import { initFeedback } from '@codebar-ag/feedback-widget';

await initFeedback({
  projectKey: 'fbk_live_92T7QQB6fj6Zb37jBGzrDI',
  endpoint: 'https://feedback.example.com',
});
```

## Widget

### Options

Only `projectKey` and `endpoint` are required.

```ts
const feedback = await initFeedback({
  projectKey: 'fbk_live_92T7QQB6fj6Zb37jBGzrDI',
  endpoint: 'https://feedback.example.com',

  locale: 'en_CH', // de_CH | fr_CH | en_CH | it_CH, defaults to the page language

  // Signed-in user: replaces the email field with "Sending as". Not verified by the server.
  identity: { id: '4711', name: 'Anna Muster', email: 'anna@example.com' },

  colors: { primary: '#0A7C66' }, // overrides the project color: black, white or hex
  position: 'bottom-right', // or 'bottom-left'
  launcher: { style: 'icon' }, // 'text' shows `label` next to the icon
  zIndex: 2147483000,

  screenshot: { mode: 'optional', quality: 0.82 }, // mode: optional | required | off

  diagnostics: {
    console: true, // console errors, uncaught errors, failed resources, CSP violations
    network: true, // failed fetch/XHR: method, URL without query, status, duration
    levels: ['error', 'warn'], // also: info, log, debug
    redact: [/CUST-\d{6}/], // extra rules, tokens, passwords, emails and card numbers are always redacted
  },

  mask: { selectors: ['.iban'], ignore: ['.chat-bubble'] }, // in addition to the data attributes below
  customData: { appVersion: '2.3.1', tenant: 'acme' }, // flat object, max. 8 KB
  messages: { en_CH: { submit: 'Send feedback' } }, // override single texts
  draft: { persist: true, ttlHours: 24 }, // keep the draft in this tab
});
```

Call `initFeedback()` as early as possible: console errors are captured from that moment on. Calling it twice returns the same instance.

### Methods and events

```ts
feedback.open();
feedback.close();
feedback.identify({ id: '4711', name: 'Anna Muster', email: 'anna@example.com' }); // or null
feedback.setLocale('fr_CH');
feedback.setData({ plan: 'pro' });
feedback.hideLauncher();
feedback.showLauncher();
feedback.destroy({ clearDraft: true });

feedback.on('before_send', (payload) => {
  payload.custom_data = { ...payload.custom_data, route: location.pathname };
  // return false to cancel
});
feedback.on('after_send', ({ id }) => console.log('Feedback stored', id));
feedback.on('error', ({ code, status }) => console.warn(code, status));
```

Events: `ready`, `open`, `close`, `before_send`, `after_send`, `error`.

### Privacy in screenshots

```html
<div data-feedback-mask>Customer number 12345</div> <!-- rendered as a solid block -->
<aside data-feedback-ignore>…</aside>              <!-- left out entirely -->
```

Password fields and `autocomplete="cc-*"` fields are always masked. Images from other hosts without CORS headers and cross-origin iframes render empty. If rendering fails, the feedback is sent without a screenshot.

### Script tag

No build step needed:

```html
<script src="https://unpkg.com/@codebar-ag/feedback-widget@1/dist/feedback.iife.js" crossorigin="anonymous"></script>
<script>
  CodebarFeedback.init({ projectKey: 'fbk_live_92T7QQB6fj6Zb37jBGzrDI', endpoint: 'https://feedback.example.com' });
</script>
```

Each [release](https://github.com/codebar-ag/feedback/releases) lists the SRI hash for `integrity="…"`.

### Laravel example

```blade
<script type="module">
  import { initFeedback } from '@codebar-ag/feedback-widget';

  initFeedback({
    projectKey: @js(config('services.feedback.key')),
    endpoint: @js(config('services.feedback.url')),
    locale: @js(str(app()->getLocale())->replace('-', '_')),
    identity: @js(auth()->user()?->only(['id', 'name', 'email'])),
  });
</script>
```

In single-page apps call `feedback.identify(user)` after sign-in.

### Content Security Policy

Allow the API in `connect-src` and `blob: data:` in `img-src`. For the script tag also allow its host in `script-src`. Styles need nothing, the widget uses `adoptedStyleSheets`.

## Managing projects and feedback

```bash
php artisan feedback:project:create "Name" --origin=https://app.example.com [--ip=10.0.0.0/8] [--color=black] [--limit=500]
php artisan feedback:project:list
php artisan feedback:project:show {key}
php artisan feedback:project:update {key} --add-origin=https://staging.example.com --color=white --deactivate
php artisan feedback:project:rotate-key {key}

php artisan feedback:list --open
php artisan feedback:show {id}
php artisan feedback:export {id} --path=./export
php artisan feedback:complete {id} {id}
php artisan feedback:reopen {id}
```

All commands accept `--json`. Changing commands accept `--by="Name"`, which is recorded in the activity log.

- **Origins**: `https://app.example.com` exact, `https://*.example.com` all subdomains, `http://localhost:*` any port, `*` any origin (needs `--allow-any-origin`).
- **IPs**: single addresses or CIDR ranges, IPv4 and IPv6. No IPs means no restriction. A request must match the origins **and** the IPs.
- **Color**: `black`, `white` or a hex value. Text becomes white or dark blue, whichever has more contrast, and button tones are adjusted until they reach 4.5:1.

## Configuration

Set in `.env`, see [`config/feedback.php`](config/feedback.php):

| Variable | Default | |
|---|---|---|
| `FEEDBACK_ENDPOINT` | `APP_URL` | Public URL of the API, used in the init snippet |
| `FEEDBACK_DEFAULT_COLOR` | `black` | Color for projects without one |
| `FEEDBACK_LIMIT_PER_IP_PER_MINUTE` | `10` | `0` disables the limit |
| `FEEDBACK_LIMIT_PER_PROJECT_PER_MINUTE` | `60` | `0` disables the limit |
| `FEEDBACK_MIN_ELAPSED_MS` | `2000` | Faster submissions are dropped silently |
| `TRUSTED_PROXIES` | | Load balancer or CDN addresses |
| `CACHE_STORE` | `file` | Use `database` or `redis` with several instances |
| `OH_DEAR_HEALTH_CHECK_*` | on, needs the secret | [Oh Dear](https://ohdear.app) application health |
| `NIGHTWATCH_API_TOKEN`, `NIGHTWATCH_APPLICATION_ID`, `NIGHTWATCH_ENVIRONMENT_ID` | | Health check fails while Nightwatch has open issues. Token from `php artisan nightwatch:login` (valid one year); the check only runs when token and application ID are set |

`DB_READ_HOST` and `DB_WRITE_HOST` are supported for read replicas.

## Deployment

- PHP 8.5 with `pdo_pgsql`, `pdo_mysql` or `pdo_sqlite`, `fileinfo` and `intl`. No queue worker needed.
- Screenshots are up to 6 MB: set `post_max_size` and `upload_max_filesize` to at least 8 MB. On MySQL raise `max_allowed_packet` to 16 MB.
- Behind a load balancer set `TRUSTED_PROXIES`, otherwise IP allowlists and rate limits see the proxy.
- Run `php artisan schedule:run` every minute for the health checks.
- `GET /up` is the health endpoint. Every other path, including `/`, returns 404 and is excluded from search engines.

## HTTP API

| Method | Path | |
|---|---|---|
| `POST` | `/v1/projects/{key}/feedback` | Submit feedback (`multipart/form-data`, `Idempotency-Key` header) |
| `GET` | `/v1/projects/{key}/config` | Project color for the widget |
| `GET` | `/up` | Health check |

Responses: `201` created, `200` duplicate `Idempotency-Key`, `403` origin or IP not allowed, `404` unknown project, `413` too large, `422` validation failed, `429` rate limited. Every stored feedback dispatches `App\Events\FeedbackReceived`, the place to hook in notifications.

## Security and privacy

The project key is public by design. The origin check is enforced by browsers, so use IP allowlists for internal apps and keep rate limits on for public sites. The API stores email, message, screenshot, browser data and the full IP address: mention the widget in your privacy policy. Please report vulnerabilities as described in [SECURITY.md](SECURITY.md).

## Development

Requires PHP 8.5, Composer and Node 26.

```bash
composer install && npm --prefix widget ci
composer check                    # Pint, PHPStan, Pest
npm --prefix widget run check     # lint, types, Vitest, build, size budget
npm --prefix widget run e2e       # Playwright: Chromium, Firefox, WebKit, mobile
npm --prefix widget run e2e:full  # API and widget together
npm --prefix widget run dev       # playground with a mock API on http://localhost:4173/fixtures/index.html
```

Releases are published from a `v*` tag through GitHub Actions with npm trusted publishing (`release.yml`). The very first publish uses an `NPM_TOKEN` repository secret, since a trusted publisher can only be linked to an existing package. See the [changelog](CHANGELOG.md) for changes.

## License

MIT. See [LICENSE](LICENSE). Made by [codebar Solutions AG](https://www.codebar.ch).
