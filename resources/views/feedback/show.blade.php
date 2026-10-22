<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Feedback {{ $entry->id }} · {{ $project->name }}</title>
    <style nonce="{{ $nonce }}">
        :root { color-scheme: light dark; --bg: #f6f7f9; --card: #fff; --ink: #101f3c; --mute: #5b6676; --line: #dde3ea; --ok: #157f3b; --open: #b45309; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0f141b; --card: #171e28; --ink: #e8edf3; --mute: #9aa6b5; --line: #2a3442; --ok: #4ade80; --open: #fbbf24; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
        header { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px 16px; margin-bottom: 16px; }
        h1 { font-size: 22px; margin: 0; }
        h2 { font-size: 15px; margin: 0 0 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--mute); }
        .id { color: var(--mute); font-family: ui-monospace, Menlo, monospace; font-size: 13px; }
        .status { font-weight: 600; padding: 2px 10px; border-radius: 999px; border: 1px solid currentColor; font-size: 13px; }
        .status.open { color: var(--open); }
        .status.completed { color: var(--ok); }
        section { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 20px; margin-bottom: 16px; overflow-wrap: anywhere; }
        .message { white-space: pre-wrap; font-size: 17px; margin: 0; }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 20px; margin: 0; }
        dt { color: var(--mute); }
        dd { margin: 0; }
        a { color: inherit; }
        .shot img { display: block; max-width: 100%; height: auto; border: 1px solid var(--line); border-radius: 8px; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 12px; }
        .button { display: inline-block; padding: 8px 16px; border-radius: 999px; background: var(--ink); color: var(--card); text-decoration: none; font-weight: 600; }
        button.button { border: 0; font: inherit; cursor: pointer; }
        .state { margin-left: auto; }
        .button.secondary { background: transparent; color: var(--ink); border: 1px solid var(--line); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 6px 8px; border-top: 1px solid var(--line); vertical-align: top; }
        th { color: var(--mute); font-weight: 600; }
        pre { margin: 0; white-space: pre-wrap; font-size: 13px; }
        .mono { font-family: ui-monospace, Menlo, monospace; }
        footer { color: var(--mute); font-size: 13px; }
        @media (max-width: 600px) { dl { grid-template-columns: 1fr; } dt { margin-top: 8px; } }
    </style>
</head>
<body>
<main>
    <header>
        <h1>{{ $project->name }}</h1>
        <span class="id">{{ $entry->id }}</span>
        @if ($entry->completed_at === null)
            <span class="status open">open</span>
        @else
            <span class="status completed">completed {{ $entry->completed_at->toDateTimeString() }}</span>
        @endif
        @if ($statusUrl !== null)
            <form class="state" method="post" action="{{ $statusUrl }}">
                @if ($entry->completed_at === null)
                    <button class="button" type="submit" name="status" value="completed">Mark as completed</button>
                @else
                    <button class="button secondary" type="submit" name="status" value="open">Reopen</button>
                @endif
            </form>
        @endif
    </header>

    <section aria-labelledby="message">
        <h2 id="message">Feedback</h2>
        <p class="message">{{ $entry->message }}</p>
    </section>

    <section aria-labelledby="details">
        <h2 id="details">Details</h2>
        <dl>
            <dt>From</dt>
            <dd>{{ trim(($entry->name ?? '').' <'.$entry->email.'>') }}</dd>
            <dt>Created</dt>
            <dd>{{ $entry->created_at->toDateTimeString() }} UTC</dd>
            <dt>Page</dt>
            <dd>
                @if ($pageHref !== null)
                    <a href="{{ $pageHref }}" rel="noopener noreferrer" target="_blank">{{ $entry->page_url }}</a>
                @else
                    {{ $entry->page_url }}
                @endif
            </dd>
            @if ($entry->page_title !== null)
                <dt>Page title</dt>
                <dd>{{ $entry->page_title }}</dd>
            @endif
            @if ($entry->submitted_from_url !== null && $entry->submitted_from_url !== $entry->page_url)
                <dt>Sent from</dt>
                <dd>
                    @if ($sentFromHref !== null)
                        <a href="{{ $sentFromHref }}" rel="noopener noreferrer" target="_blank">{{ $entry->submitted_from_url }}</a>
                    @else
                        {{ $entry->submitted_from_url }}
                    @endif
                </dd>
            @endif
            <dt>Locale</dt>
            <dd>{{ $entry->locale }}</dd>
            <dt>System</dt>
            <dd>{{ trim(($entry->os_name ?? '?').' '.($entry->os_version ?? '')) }}</dd>
            <dt>Browser</dt>
            <dd>{{ trim(($entry->browser_name ?? '?').' '.($entry->browser_version ?? '')) }} ({{ $entry->version_precision }})</dd>
            @if ($entry->device_type !== null)
                <dt>Device</dt>
                <dd>{{ $entry->device_type }}</dd>
            @endif
            <dt>Viewport</dt>
            <dd>{{ $entry->viewport_w ?? '?' }} × {{ $entry->viewport_h ?? '?' }}</dd>
            <dt>Screen</dt>
            <dd>{{ $entry->screen_w ?? '?' }} × {{ $entry->screen_h ?? '?' }} @ {{ $entry->dpr ?? '?' }}</dd>
            @if ($entry->timezone !== null || $entry->browser_language !== null)
                <dt>Timezone / language</dt>
                <dd>{{ $entry->timezone ?? '?' }} / {{ $entry->browser_language ?? '?' }}</dd>
            @endif
            <dt>IP</dt>
            <dd class="mono">{{ $entry->ip ?? '–' }}</dd>
            <dt>Widget</dt>
            <dd>{{ $entry->widget_version }}</dd>
            @if ($entry->user_agent !== null)
                <dt>User agent</dt>
                <dd class="mono">{{ $entry->user_agent }}</dd>
            @endif
        </dl>
    </section>

    <section class="shot" aria-labelledby="screenshot">
        <h2 id="screenshot">Screenshot</h2>
        @if ($shot !== null)
            <div class="actions">
                <a class="button" href="{{ $downloadUrl }}" download>Download ({{ $shot->width }} × {{ $shot->height }}, {{ number_format($shot->bytes / 1024, 0, '.', "'") }} KB)</a>
                <a class="button secondary" href="{{ $imageUrl }}" target="_blank" rel="noopener noreferrer">Open full size</a>
            </div>
            <img src="{{ $imageUrl }}" width="{{ $shot->width }}" height="{{ $shot->height }}" alt="Screenshot of {{ $entry->page_url }}">
        @else
            <p>{{ $entry->screenshot_error === null ? 'No screenshot.' : 'No screenshot ('.$entry->screenshot_error.').' }}</p>
        @endif
    </section>

    @if ($console !== [])
        <section aria-labelledby="console">
            <h2 id="console">Console ({{ count($console) }})</h2>
            <table>
                <thead><tr><th>Type</th><th>Message</th><th>Count</th></tr></thead>
                <tbody>
                @foreach ($console as $row)
                    <tr><td>{{ $row['type'] }}</td><td class="mono">{{ $row['message'] }}</td><td>{{ $row['count'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($network !== [])
        <section aria-labelledby="network">
            <h2 id="network">Network ({{ count($network) }})</h2>
            <table>
                <thead><tr><th>Method</th><th>URL</th><th>Status</th><th>ms</th><th>Count</th></tr></thead>
                <tbody>
                @foreach ($network as $row)
                    <tr><td>{{ $row['method'] }}</td><td class="mono">{{ $row['url'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['duration'] }}</td><td>{{ $row['count'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($customData !== null)
        <section aria-labelledby="custom">
            <h2 id="custom">Custom data</h2>
            <pre>{{ $customData }}</pre>
        </section>
    @endif

    <footer>This link expires {{ $expires->toDateTimeString() }} UTC.</footer>
</main>
</body>
</html>
