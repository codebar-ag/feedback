# @codebar-ag/feedback-widget

Feedback-Widget von codebar: Launcher, Formular, Full-Page-Screenshot mit Markierungen (Rechteck, Marker) und technische Diagnose (Browser, System, Konsolenfehler). Vanilla TypeScript im Shadow DOM, ohne Laufzeit-Abhängigkeiten im Kern (≤ 20 KB gzip). Der Screenshot-Teil wird lazy geladen.

Das Widget sendet an die codebar-Feedback-API (Laravel), siehe [github.com/codebar-ag/feedback](https://github.com/codebar-ag/feedback).

## Installation

```bash
npm install @codebar-ag/feedback-widget
```

```ts
import { initFeedback } from '@codebar-ag/feedback-widget';

const feedback = await initFeedback({
  projectKey: 'fbk_live_…',
  endpoint: 'https://feedback.codebar.ch',
  locale: 'de_CH',
  identity: { id: '4711', name: 'Anna Muster', email: 'anna.muster@beispiel.ch' }, // optional
});

feedback.on('after_send', ({ id }) => console.log('Feedback gespeichert', id));
```

Script-Tag (IIFE, globale Variable `CodebarFeedback`):

```html
<script async src="https://unpkg.com/@codebar-ag/feedback-widget/dist/feedback.iife.js" crossorigin="anonymous"></script>
<script>
  window.addEventListener('load', function () {
    CodebarFeedback.init({ projectKey: 'fbk_live_…', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' });
  });
</script>
```

Alle Optionen, Methoden, Events, Framework-Beispiele, CSP-Hinweise und Fehlerbehebung: [docs/widget-setup.md](https://github.com/codebar-ag/feedback/blob/main/docs/widget-setup.md).

## Entwicklung

```bash
npm ci
npm run check     # Lint, Typen, Vitest, Build, Budget, publint, attw
npm run e2e       # Playwright (Chromium, Firefox, WebKit, Mobile)
npm run dev       # Mock-Server mit Fixture-Seiten: http://localhost:4173/fixtures/index.html
```

## Lizenz

MIT
