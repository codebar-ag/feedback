# @codebar-ag/feedback-widget

A feedback widget for websites and web apps: visitors describe a problem, mark it on a full-page screenshot and send it, together with browser data and console errors. Vanilla TypeScript in a Shadow DOM, 18 KB gzip, no runtime dependencies.

It sends to the self-hosted [codebar Feedback API](https://github.com/codebar-ag/feedback).

```bash
npm install @codebar-ag/feedback-widget
```

```ts
import { initFeedback } from '@codebar-ag/feedback-widget';

const feedback = await initFeedback({
  projectKey: 'fbk_live_…',
  endpoint: 'https://feedback.example.com',
  locale: 'en_CH',
  identity: { id: '4711', name: 'Anna Muster', email: 'anna@example.com' }, // optional
});

feedback.on('after_send', ({ id }) => console.log('Feedback stored', id));
```

Without a build step:

```html
<script src="https://unpkg.com/@codebar-ag/feedback-widget@1/dist/feedback.iife.js" crossorigin="anonymous"></script>
<script>
  CodebarFeedback.init({ projectKey: 'fbk_live_…', endpoint: 'https://feedback.example.com' });
</script>
```

All options, methods and events are documented in the [repository README](https://github.com/codebar-ag/feedback#widget).

## License

MIT
