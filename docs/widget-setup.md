# Widget einbinden

Das Widget ist ein npm-Paket ohne Laufzeit-Abhängigkeiten im Kern (17.8 KB gzip). Es rendert in einem Shadow DOM, beeinflusst also weder das CSS deiner Seite noch umgekehrt. Der Screenshot-Teil (`modern-screenshot`) wird erst geladen, wenn jemand mit der Maus über den Launcher fährt oder ihn fokussiert.

Vorher brauchst du ein Projekt und dessen öffentlichen Schlüssel (`php artisan feedback:project:create …`, siehe [`commands.md`](commands.md)). Der Schlüssel ist öffentlich, geschützt wird über die Domain- und IP-Freigabe des Projekts.

## Installation per npm

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

Rufe `initFeedback()` so früh wie möglich auf, am besten im ersten Skript der Seite. Konsolenfehler und fehlgeschlagene Requests werden erst ab dann erfasst. Ein zweiter Aufruf gibt dieselbe Instanz zurück.

## Script-Tag

Für Seiten ohne Build. Die Datei liegt im Paket unter `dist/feedback.iife.js` (auch über unpkg/jsDelivr) oder auf der API unter `/widget/v1/feedback.js` (siehe [`installation.md`](installation.md#widget-von-der-api-ausliefern-optional)). Den SRI-Hash findest du in `dist/sri.json` bzw. `public/widget/v1/sri.json` und in jedem GitHub-Release.

```html
<script async src="https://feedback.codebar.ch/widget/v1/feedback.js"
        integrity="sha384-…" crossorigin="anonymous"></script>
<script>
  window.addEventListener('load', function () {
    CodebarFeedback.init({
      projectKey: 'fbk_live_MHoE8S6QzLaeMWMYt4GWP4',
      endpoint: 'https://feedback.codebar.ch',
      locale: 'de_CH',
    }).then(function (feedback) {
      window.feedback = feedback;
    });
  });
</script>
```

Der Screenshot-Chunk (`chunks/capture-….js`) wird relativ zur Script-URL nachgeladen und muss daneben liegen.

## Init-Optionen

| Option | Typ | Standard | Bedeutung |
|---|---|---|---|
| `projectKey` | string | – | Öffentlicher Schlüssel, Pflicht |
| `endpoint` | string | – | Basis-URL der API, Pflicht |
| `locale` | `de_CH` \| `fr_CH` \| `en_CH` \| `it_CH` | `lang` der Seite | Fallback: Basissprache (`de` → `de_CH`), dann `en_CH` |
| `identity` | `{ id?, name?, email }` | – | Eingeloggte Person, ersetzt das E-Mail-Feld |
| `colors.primary` | Hex, `black`, `white` | Projektfarbe | Überschreibt die Farbe aus der API |
| `position` | `bottom-right` \| `bottom-left` | `bottom-right` | Position von Launcher und Panel |
| `launcher` | `{ style: 'icon' \| 'text', label }` | `icon` | `text` zeigt das Label neben dem Icon |
| `screenshot.mode` | `optional` \| `required` \| `off` | `optional` | `required` verlangt einen Screenshot, `off` blendet die Tools aus |
| `screenshot.quality` | 0.1–1 | `0.82` | JPEG-Qualität |
| `screenshot.maxPixels` | number | 25 Mio. (Safari/Mobile 16 Mio.) | Pixelbudget, darüber wird verkleinert |
| `diagnostics.console` | boolean | `true` | Konsole, Fehler, Rejections, Ressourcen, CSP |
| `diagnostics.network` | boolean | `true` | Fehlgeschlagene fetch/XHR (nur Methode, URL ohne Query, Status, Dauer) |
| `diagnostics.levels` | `('error' \| 'warn' \| 'info' \| 'log' \| 'debug')[]` | `['error', 'warn']` | Erfasste Konsolenstufen |
| `diagnostics.maxEntries` | number | `50` | Grösse des Ringpuffers (max. 50) |
| `diagnostics.redact` | `RegExp[]` | `[]` | Eigene Redaktionsregeln zusätzlich zu den eingebauten |
| `mask.selectors` | string[] | `[]` | Zusätzlich zu `[data-feedback-mask]` verdecken |
| `mask.ignore` | string[] | `[]` | Zusätzlich zu `[data-feedback-ignore]` weglassen |
| `customData` | flaches Objekt | – | Zusatzdaten, max. 8 KB |
| `messages` | `{ de_CH: { submit: 'Abschicken' } }` | – | Einzelne Texte überschreiben |
| `draft` | `{ persist, ttlHours }` | `{ persist: true, ttlHours: 24 }` | Entwurf im Browser behalten |
| `nonce` | string | – | CSP-Nonce, nur für Browser ohne `adoptedStyleSheets` |
| `zIndex` | number | `2147483000` | Stapelreihenfolge |

## Methoden und Events

```ts
feedback.open();
feedback.close();
feedback.showLauncher();
feedback.hideLauncher();
feedback.identify({ id: '4711', name: 'Anna Muster', email: 'anna.muster@beispiel.ch' });
feedback.setLocale('fr_CH');          // wirkt sofort, auch bei offenem Panel
feedback.setData({ tenant: 'acme' }); // ergänzt customData
feedback.isOpen();
feedback.destroy({ clearDraft: true });

const off = feedback.on('after_send', ({ id }) => console.log('gespeichert', id));
off();
```

| Event | Argument | Hinweis |
|---|---|---|
| `ready` | – | nach dem Laden von Entwurf und Projektfarbe |
| `open`, `close` | – | |
| `before_send` | Payload (änderbar) | `return false` bricht das Senden ab |
| `after_send` | `{ id }` | nach 201/200 |
| `error` | `{ code, status, retryAfter? }` | z. B. `origin_not_allowed`, `project_not_found`, `rate_limited`, `network_error` |

Während Screenshot, Markieren und Senden ignoriert das Widget `open()` und `close()` der Host-Seite.

## Identität aus dem eingeloggten Bereich

Mit Identität zeigt das Widget «Senden als» mit Name und E-Mail statt des E-Mail-Felds. Die Angabe wird vom Server nicht verifiziert (kein HMAC). Für private Anwendungen schützt die IP-Freigabe.

**Blade**

```blade
<script type="module">
  import { initFeedback } from '{{ Vite::asset('resources/js/feedback.js') }}';
  initFeedback({
    projectKey: @json(config('services.feedback.key')),
    endpoint: @json(config('services.feedback.url')),
    locale: @json(str(app()->getLocale())->replace('-', '_')),
    identity: @auth { id: @json((string) auth()->id()), name: @json(auth()->user()->name), email: @json(auth()->user()->email) } @else null @endauth,
  });
</script>
```

**Inertia** (`resources/js/app.ts`)

```ts
import { router } from '@inertiajs/vue3';
import { initFeedback } from '@codebar/feedback-widget';

const feedback = await initFeedback({ projectKey: import.meta.env.VITE_FEEDBACK_KEY, endpoint: import.meta.env.VITE_FEEDBACK_URL });

router.on('navigate', (event) => {
  const user = event.detail.page.props.auth?.user;
  feedback.identify(user ? { id: String(user.id), name: user.name, email: user.email } : null);
});
```

**Livewire**

```blade
<div wire:ignore x-data x-init="
  import('/build/feedback.js').then(({ initFeedback }) => initFeedback({
    projectKey: @js(config('services.feedback.key')),
    endpoint: @js(config('services.feedback.url')),
    identity: @js(auth()->check() ? ['id' => (string) auth()->id(), 'name' => auth()->user()->name, 'email' => auth()->user()->email] : null),
  }))
"></div>
```

**React**

```tsx
import { useEffect } from 'react';
import { initFeedback } from '@codebar/feedback-widget';

export function Feedback({ user }: { user: { id: string; name: string; email: string } | null }) {
  useEffect(() => {
    let alive = true;
    void initFeedback({ projectKey: 'fbk_live_…', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' }).then((fb) => {
      if (alive) fb.identify(user);
    });
    return () => {
      alive = false;
    };
  }, [user]);
  return null;
}
```

**Vue**

```ts
import { onMounted, watch } from 'vue';
import { initFeedback, type FeedbackInstance } from '@codebar/feedback-widget';

let feedback: FeedbackInstance | null = null;
onMounted(async () => {
  feedback = await initFeedback({ projectKey: 'fbk_live_…', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' });
});
watch(user, (u) => feedback?.identify(u ? { id: String(u.id), name: u.name, email: u.email } : null));
```

## Sprachen

`de_CH`, `fr_CH`, `en_CH`, `it_CH`, Anrede Du (tutoiement, «tu»). Einzelne Texte überschreibst du pro Sprache:

```ts
initFeedback({ projectKey, endpoint, locale: 'de_CH', messages: { de_CH: { submit: 'Abschicken' } } });
```

Die Schlüssel stehen in `widget/src/i18n/de_CH.ts`.

## Farben

Die Farbe kommt aus dem Projekt (`--color` beim Anlegen, siehe [`commands.md`](commands.md#farbe)). Schrift und Icons auf der Farbe werden automatisch Weiss oder Dunkelblau, je nach Kontrast. Erreicht ein mittlerer Ton keine 4.5:1, dunkelt oder hellt das Widget Launcher und Buttons in 2-%-Schritten ab. Weiss bekommt eine Trennlinie und einen dunklen Senden-Button. Ist die API nicht erreichbar, startet das Widget in Schwarz. Es gibt keinen Dark Mode.

## Datenschutz im Screenshot

```html
<div data-feedback-mask>Kundennummer 12345</div>  <!-- wird als deckender Block gerendert -->
<aside data-feedback-ignore>…</aside>               <!-- fehlt im Screenshot -->
```

Passwortfelder und Felder mit `autocomplete="cc-…"` sind immer verdeckt. Zusätzliche Selektoren: `mask: { selectors: ['.iban'], ignore: ['.chat-bubble'] }`.

Bekannte Grenzen jeder DOM-Rendering-Lösung: Cross-Origin-Bilder ohne CORS-Header und Cross-Origin-iframes erscheinen leer, WebGL/Canvas nur mit `preserveDrawingBuffer`, Videos ggf. nur mit Poster. Setze `crossorigin="anonymous"` auf eigene Bilder von anderen Hosts. Schlägt das Rendern fehl, geht das Feedback ohne Bild raus (`screenshot_error`).

## Content Security Policy

| Direktive | Wert |
|---|---|
| `connect-src` | die API, z. B. `https://feedback.codebar.ch` |
| `img-src` | `blob: data:` |
| `script-src` | nur Script-Tag-Variante: Host der Datei (oder SRI/Nonce) |
| `style-src` | nichts nötig (`adoptedStyleSheets`). Ältere Browser: `nonce` setzen |

## Entwurf

Text, E-Mail, Screenshot und Markierungen bleiben bei Schliessen, Seitenwechsel und Reload erhalten, nur in diesem Tab (`sessionStorage`, Screenshot in `IndexedDB`). Gelöscht wird nach dem Senden, beim Entfernen des Screenshots, nach `ttlHours` und mit `destroy({ clearDraft: true })`. Im Privatmodus ohne Speicher arbeitet das Widget normal, nur ohne Entwurf.

## Entfernen

```ts
feedback.destroy();                      // Entwurf bleibt
feedback.destroy({ clearDraft: true });  // Entwurf löschen
```

`destroy()` entfernt das Element, alle Listener und die Konsolen- und Netzwerk-Erfassung.

## Fehlerbehebung

| Symptom | Ursache und Lösung |
|---|---|
| Fehlerseite, in der Konsole `origin_not_allowed` | Die Origin der Seite fehlt im Projekt: `feedback:project:update {key} --add-origin=https://…`. Schema und Port müssen passen |
| Fehlerseite, Netzwerkfehler im DevTools-Tab, kein Status | Preflight abgelehnt (fremde Origin) oder Projekt unbekannt/inaktiv. Prüfe `feedback:project:show {key}` |
| `403` trotz richtiger Origin | IP-Freigabe greift. Hinter einem Proxy `TRUSTED_PROXIES` setzen |
| `429` | Rate Limit oder Tageslimit erreicht, `retryAfter` im `error`-Event |
| Screenshot leer oder Bilder fehlen | Cross-Origin-Ressourcen ohne CORS, siehe oben |
| Kamera rot, «Screenshot nicht möglich.» | Chunk nicht ladbar (CSP `script-src`) oder Rendern fehlgeschlagen. Feedback kann trotzdem gesendet werden |
| Projektfarbe erscheint nicht | `GET …/config` blockiert (CSP `connect-src`, Origin). Das Widget bleibt dann schwarz |
| Frühe Konsolenfehler fehlen | `initFeedback()` früher aufrufen |
