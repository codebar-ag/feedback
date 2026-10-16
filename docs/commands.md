# Commands

Es gibt keine Verwaltungsoberfläche und keine Management-API. Projekte und Feedbacks verwaltest du mit Artisan. Alle Ausgaben unten stammen aus einem echten Durchlauf.

Gemeinsame Optionen:

- `--json` gibt maschinenlesbares JSON aus (bei `project:create`, `project:list`, `project:show`, `project:update`, `project:rotate-key`, `list`, `show`, `geoip:update`).
- `--by="Name"` hält im Activity-Log fest, wer etwas geändert hat (bei allen ändernden Commands).
- Projekte lassen sich überall über den öffentlichen Schlüssel oder die ID ansprechen.

Exit-Codes: `0` Erfolg, `1` nicht gefunden oder fehlgeschlagen, `2` ungültige Eingabe.

## Projekte

### `feedback:project:create`

```bash
php artisan feedback:project:create "Beispiel Website" --origin=https://www.beispiel.ch --origin="https://*.beispiel.ch" --color="#0A7C66" --by="Anna Muster"
```

```
   INFO  Projekt angelegt.

  Name .......................................................................... Beispiel Website
  Public Key ..................................................... fbk_live_MHoE8S6QzLaeMWMYt4GWP4
  Aktiv ....................................................................................... ja
  Origins ......................................... https://www.beispiel.ch, https://*.beispiel.ch
  IPs ...................................................................... (keine Einschränkung)
  Farbe .............................................. #0A7C66 · Schrift #FFFFFF · Kontrast 5.14:1
  Tageslimit ........................................................................ (kein Limit)
Init-Snippet:
  initFeedback({ projectKey: 'fbk_live_MHoE8S6QzLaeMWMYt4GWP4', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' })
```

| Option | Bedeutung |
|---|---|
| `--origin=*` | Erlaubte Origins, mindestens eine. Muster siehe [`security.md`](security.md#erlaubte-origins) |
| `--ip=*` | Erlaubte IPs oder CIDR-Bereiche (IPv4, IPv6). Leer = keine Einschränkung |
| `--color=` | `black`, `white` oder Hex (`#0A7C66`, `#abc`) |
| `--limit=` | Tageslimit für dieses Projekt (positive Zahl) |
| `--allow-any-origin` | Bestätigt die Origin `*` (nur für öffentliche Seiten). Ohne die Option fragt der Command nach |

Das Init-Snippet verwendet `FEEDBACK_ENDPOINT`.

### `feedback:project:list`

```bash
php artisan feedback:project:list
```

```
+------------------+---------------------------------+-------+---------+------------------------------------------------+------------+-------+----------+
| Name             | Schlüssel                       | Aktiv | Farbe   | Origins                                        | IPs        | Offen | Erledigt |
+------------------+---------------------------------+-------+---------+------------------------------------------------+------------+-------+----------+
| Beispiel Website | fbk_live_MHoE8S6QzLaeMWMYt4GWP4 | ja    | #FFFFFF | https://www.beispiel.ch, https://*.beispiel.ch | 10.0.0.0/8 | 0     | 0        |
+------------------+---------------------------------+-------+---------+------------------------------------------------+------------+-------+----------+
```

### `feedback:project:show`

```bash
php artisan feedback:project:show fbk_live_MHoE8S6QzLaeMWMYt4GWP4 --json
```

```json
{
    "id": "01m51fm1bh5fqsby7eehxcw4fp",
    "name": "Beispiel Website",
    "public_key": "fbk_live_MHoE8S6QzLaeMWMYt4GWP4",
    "is_active": true,
    "color": {
        "primary": "#FFFFFF",
        "on_primary": "#101F3C",
        "contrast": 16.36,
        "strong": "#101F3C",
        "on_strong": "#FFFFFF",
        "strong_contrast": 16.36,
        "divider": true
    },
    "allowed_origins": ["https://www.beispiel.ch", "https://*.beispiel.ch"],
    "allowed_ips": ["10.0.0.0/8"],
    "daily_limit": 500,
    "open": 0,
    "completed": 0,
    "snippet": "initFeedback({ projectKey: 'fbk_live_MHoE8S6QzLaeMWMYt4GWP4', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' })",
    "created_at": "2026-10-16T04:30:44+00:00",
    "updated_at": "2026-10-16T04:30:44+00:00"
}
```

### `feedback:project:update`

```bash
php artisan feedback:project:update fbk_live_MHoE8S6QzLaeMWMYt4GWP4 --add-ip=10.0.0.0/8 --color=white --limit=500
```

```
   INFO  Projekt aktualisiert.

  Name .......................................................................... Beispiel Website
  Public Key ..................................................... fbk_live_MHoE8S6QzLaeMWMYt4GWP4
  Aktiv ....................................................................................... ja
  Origins ......................................... https://www.beispiel.ch, https://*.beispiel.ch
  IPs ................................................................................. 10.0.0.0/8
  Farbe ........ #FFFFFF · Schrift #101F3C · Kontrast 16.36:1 · Buttons #101F3C (#FFFFFF, 16.36:1)
  Tageslimit ................................................................................. 500
```

| Option | Bedeutung |
|---|---|
| `--name=` | Neuer Name |
| `--add-origin=*`, `--remove-origin=*` | Origins ergänzen oder entfernen (mindestens eine bleibt) |
| `--add-ip=*`, `--remove-ip=*` | IPs/CIDR ergänzen oder entfernen |
| `--color=` | Neue Farbe |
| `--limit=` | Tageslimit, `none` entfernt es |
| `--activate`, `--deactivate` | Projekt ein- oder ausschalten. Inaktive Projekte antworten mit 404 |

### `feedback:project:rotate-key`

```bash
php artisan feedback:project:rotate-key fbk_live_MHoE8S6QzLaeMWMYt4GWP4
```

```
   INFO  Schlüssel erneuert. Der alte Schlüssel ist ab sofort ungültig.

  Public Key ..................................................... fbk_live_9aRaVwTQODcQ5gegjeVxcA
Init-Snippet:
  initFeedback({ projectKey: 'fbk_live_9aRaVwTQODcQ5gegjeVxcA', endpoint: 'https://feedback.codebar.ch', locale: 'de_CH' })
```

## Farbe

`--color` akzeptiert `black` (`#000000`), `white` (`#FFFFFF`) oder einen Hex-Wert. Der Command zeigt die abgeleitete Schriftfarbe und den Kontrast. Muss für Launcher und Buttons nachgedunkelt oder aufgehellt werden (Kontrast unter 4.5:1), steht der angepasste Ton unter «Buttons». Beispiele:

| Farbe | Schrift | Kontrast |
|---|---|---|
| `black` | `#FFFFFF` | 21.00:1 |
| `white` | `#101F3C` | 16.36:1 (Trennlinie, Button `#101F3C`) |
| `#ED813A` | `#101F3C` | 6.08:1 |
| `#0A7C66` | `#FFFFFF` | 5.14:1 |

## Feedbacks

### `feedback:list`

```bash
php artisan feedback:list --open --limit=20
```

```
+----------------------------+------------------+------------------+--------+------------------+----------------------------------------------+
| ID                         | Projekt          | Erstellt         | Status | E-Mail           | Feedback                                     |
+----------------------------+------------------+------------------+--------+------------------+----------------------------------------------+
| 01m51fmj1sx73afx07nf3xzb6a | Beispiel Website | 2026-10-16 04:31 | offen  | anna@beispiel.ch | Die Schaltfläche «Speichern» reagiert nicht. |
+----------------------------+------------------+------------------+--------+------------------+----------------------------------------------+
```

Optionen: `--project=` (Schlüssel oder ID), `--open`, `--completed`, `--limit=20`, `--json`.

### `feedback:show`

```bash
php artisan feedback:show 01m51fmj1sx73afx07nf3xzb6a
```

```
  ID .................................................................. 01m51fmj1sx73afx07nf3xzb6a
  Projekt ....................................................................... Beispiel Website
  Erstellt ................................................................... 2026-10-16 04:31:01
  Status ................................................................................... offen
  Von ......................................................................... <anna@beispiel.ch>
  Sprache .................................................................................. de_CH
  Seite ............................................................ https://www.beispiel.ch/konto
  System ....................................................................................... ?
  Browser ............................................................... curl 8.7.1 (approximate)
  Browserfenster ...................................................................... 1541 × 947
  Auflösung .................................................................... 1800 × 1169 @2.00
  Standort ..................................................................................... –
  IP ................................................................................... 127.0.0.1
  Screenshot ............................................................. 800 × 1600, 20691 Bytes
  Konsole ............................................................................. 0 Einträge

Die Schaltfläche «Speichern» reagiert nicht.
```

(Das Beispiel wurde mit `curl` gesendet, darum «curl» als Browser.) Mit `--json` kommen alle Felder inklusive `console_log`, `network_log`, `annotations`, `custom_data` und den Screenshot-Metadaten (`mime`, `width`, `height`, `bytes`, `sha256`), nie das Bild selbst.

### `feedback:export`

```bash
php artisan feedback:export 01m51fmj1sx73afx07nf3xzb6a --path=/tmp/cbf-export
```

```
  JSON .................................. /tmp/cbf-export/feedback-01m51fmj1sx73afx07nf3xzb6a.json
  Screenshot ...................................................... /tmp/cbf-export/screenshot.jpg

   INFO  Export abgeschlossen.
```

Ohne `--path` landet der Export im aktuellen Verzeichnis.

### `feedback:complete`, `feedback:reopen`

```bash
php artisan feedback:complete 01m51fmj1sx73afx07nf3xzb6a --by="Anna Muster"
php artisan feedback:reopen 01m51fmj1sx73afx07nf3xzb6a
```

Beide nehmen mehrere IDs. Unbekannte IDs werden gemeldet (Exit-Code 1), die übrigen trotzdem verarbeitet.

### `feedback:note`

```bash
php artisan feedback:note 01m51fmj1sx73afx07nf3xzb6a "Ticket FB-42 erstellt"
php artisan feedback:note 01m51fmj1sx73afx07nf3xzb6a --clear
```

## `geoip:update`

Lädt die MaxMind-GeoLite2-City-Datenbank nach `FEEDBACK_GEOIP_PATH`. Nur nötig mit `FEEDBACK_GEO=maxmind`, braucht `MAXMIND_ACCOUNT_ID` und `MAXMIND_LICENSE_KEY` (kostenloses MaxMind-Konto). Läuft monatlich über den Scheduler.

```bash
php artisan geoip:update --json
```

```json
{"status":"error","message":"MAXMIND_ACCOUNT_ID and MAXMIND_LICENSE_KEY must be set."}
```

(Ausgabe ohne Zugangsdaten. Mit Zugangsdaten: `{"status":"updated","path":"…/GeoLite2-City.mmdb","bytes":…}`.)

## Activity-Log

Änderungen landen mit `spatie/laravel-activitylog` in der Tabelle `activity_log`, Log-Name `feedback`:

| Ereignis | Beschreibung | Protokollierte Felder |
|---|---|---|
| Projekt angelegt / geändert | `created`, `updated` | `name`, `public_key`, `theme`, `allowed_origins`, `allowed_ips`, `is_active`, `daily_limit` |
| Schlüssel rotiert | `updated` | `public_key` |
| Eintrag erledigt / wieder geöffnet | `completed`, `reopened` | `completed_at` |
| Notiz geändert | `note_changed` | `internal_note` |

Jeder Eintrag hat die Eigenschaft `via: artisan` und, falls angegeben, `by`. Personendaten (`message`, `email`, `name`, `ip`) werden nie ins Log kopiert. Eingehende Feedbacks werden nicht zusätzlich protokolliert.

Abfrage direkt in der Datenbank:

```sql
SELECT created_at, description, subject_id, attribute_changes, properties
FROM activity_log
WHERE log_name = 'feedback'
ORDER BY id DESC
LIMIT 20;
```
