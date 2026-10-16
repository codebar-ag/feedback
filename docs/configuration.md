# Konfiguration

Alle Einstellungen der Feedback-API liegen in [`config/feedback.php`](../config/feedback.php) und werden über `.env` gesetzt. Nach Änderungen in Produktion `php artisan config:cache`.

| Schlüssel | `.env` | Standard | Bedeutung |
|---|---|---|---|
| `endpoint` | `FEEDBACK_ENDPOINT` | `APP_URL` | Öffentliche Basis-URL der API, erscheint im Init-Snippet |
| `ip_mode` | `FEEDBACK_IP_MODE` | `full` | `full` speichert die IP, `anonymized` das letzte IPv4-Oktett bzw. die letzten 80 Bit einer IPv6-Adresse auf 0 |
| `geo` | `FEEDBACK_GEO` | `none` | Standortquelle, siehe unten |
| `maxmind.database_path` | `FEEDBACK_GEOIP_PATH` | `storage/app/geoip/GeoLite2-City.mmdb` | Pfad der GeoLite2-Datenbank |
| `maxmind.account_id`, `maxmind.license_key` | `MAXMIND_ACCOUNT_ID`, `MAXMIND_LICENSE_KEY` | – | Zugang für `geoip:update` |
| `maxmind.edition` | `MAXMIND_EDITION` | `GeoLite2-City` | MaxMind-Edition |
| `default_color` | `FEEDBACK_DEFAULT_COLOR` | `black` | Farbe für Projekte ohne eigene Farbe (`black`, `white`, Hex). Zu entscheiden |
| `limits.per_ip_per_minute` | `FEEDBACK_LIMIT_PER_IP_PER_MINUTE` | leer (aus) | Anfragen pro Minute je IP und Projekt |
| `limits.per_project_per_minute` | `FEEDBACK_LIMIT_PER_PROJECT_PER_MINUTE` | leer (aus) | Anfragen pro Minute je Projekt |
| `limits.min_elapsed_ms` | `FEEDBACK_MIN_ELAPSED_MS` | leer (aus) | Mindestzeit zwischen Öffnen und Senden. Schnellere Anfragen werden still mit 201 beantwortet und nicht gespeichert |
| `max_screenshot_bytes` | – | 6 MB | Grösster Screenshot, darüber 413 |
| `max_screenshot_pixels` | – | 25 Mio. | Grösste Bildfläche (Breite × Höhe) |
| `config_max_age` | – | 600 | `Cache-Control` von `GET …/config` in Sekunden (das Widget cacht selbst 10 Minuten) |

Das Tageslimit gilt pro Projekt und wird mit `--limit` gesetzt (`feedback:project:update {key} --limit=500`, `--limit=none` entfernt es).

## Limits

Das Konzept legt bewusst **keine** Zahlenwerte fest. Ohne Eintrag ist das jeweilige Limit aus. Setze sie passend zur Website, zum Beispiel strenger für öffentliche Seiten als für ein Intranet mit IP-Freigabe. Bei Überschreitung antwortet die API mit `429` und `Retry-After`, das Widget zeigt die Fehlerseite und meldet `rate_limited` im `error`-Event.

Das Rate Limiting nutzt den Cache-Store (`CACHE_STORE`). Bei mehreren App-Servern muss der Store gemeinsam sein (z. B. Redis oder Datenbank).

## Standort (`FEEDBACK_GEO`)

| Wert | Quelle |
|---|---|
| `none`, `off` | kein Standort (Standard) |
| `cloudflare` | Header `CF-IPCountry`, `CF-IPCity`, `CF-Postal-Code` (Cloudflare «Add visitor location headers»). Nur sicher, wenn die API ausschliesslich über Cloudflare erreichbar ist |
| `maxmind` | lokale GeoLite2-Datenbank, kein Aufruf externer Dienste. Fehlt die Datei, bleibt der Standort leer |

Die Genauigkeit liegt auf Stadtebene, die PLZ ist oft ungenau. Welche Quelle gilt, ist noch zu entscheiden.

## IP-Modus

`anonymized` wirkt beim Speichern. Der Standort wird vorher aus der vollen IP ermittelt. Die IP-Freigabe prüft immer die volle IP der Anfrage.

## Trusted Proxies

Kein Teil von `config/feedback.php`, aber entscheidend für IP-Freigabe und Limits: siehe [`installation.md`](installation.md#trusted-proxies).
