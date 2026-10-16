# Sicherheit und Datenschutz

## Grundsatz

Der öffentliche Schlüssel (`fbk_live_…`) steht im Code der Website und ist **öffentlich**. Er identifiziert das Projekt, er schützt nichts. Geschützt wird serverseitig, in dieser Reihenfolge:

1. `ResolveProject`: Schlüssel im Pfad → aktives Projekt. Unbekannt und inaktiv antworten gleich (404 `project_not_found`), damit sich Schlüssel nicht erraten lassen.
2. `ApplyProjectCors`: beantwortet den Preflight und setzt `Access-Control-Allow-Origin` nur für erlaubte Origins (genau die anfragende Origin, nie `*`, nie mit Credentials, immer `Vary: Origin`).
3. `EnforceOriginPolicy`: Anfragen ohne oder mit fremder `Origin` → 403 `origin_not_allowed`.
4. `EnforceIpPolicy`: IP ausserhalb der erlaubten Bereiche → 403 (gleiche Antwort).
5. `throttle:feedback`: Limits pro IP und Projekt, pro Projekt und Tageslimit (Werte aus der Konfiguration, ohne Standard).
6. `StoreFeedbackRequest`: Honeypot und Mindestzeit (still 201, nichts gespeichert), strikte Validierung, Steuerzeichen entfernt, Screenshot per `finfo` geprüft.

## Erlaubte Origins

Jedes Projekt braucht mindestens eine Origin. Eine Anfrage wird nur angenommen, wenn Origin **und** IP passen.

| Muster | Bedeutung |
|---|---|
| `https://app.beispiel.ch` | genau diese Origin (Schema, Host, Port) |
| `https://*.beispiel.ch` | alle Subdomains, **nicht** `beispiel.ch` selbst |
| `http://localhost:*` | beliebiger Port, für Entwicklung |
| `*` | jede Origin, nur mit `--allow-any-origin` oder Bestätigung |

`https://app.beispiel.ch.evil.com` passt auf keines der Muster oben. Origins mit Pfad, Benutzer oder Wildcard werden als Anfrage abgelehnt.

## Erlaubte IPs

Einzeladressen und CIDR, IPv4 und IPv6 (`203.0.113.7`, `10.0.0.0/8`, `2001:db8::/32`), geprüft mit Symfony `IpUtils`. Leer = keine IP-Einschränkung. Hinter Load Balancer oder CDN müssen `TRUSTED_PROXIES` stimmen, sonst wird die IP des Proxys geprüft.

Typische Einsätze:

- **Intranet:** Origin `https://intranet.firma.ch` plus IP-Bereich von Büro und VPN.
- **Öffentliche Website:** Origin `https://www.firma.ch`, keine IP-Einschränkung, dafür Limits und Honeypot.
- **Mehrere Umgebungen:** eigene Projekte (Produktion, Test) mit eigenem Schlüssel.

## Grenzen

Die Origin-Prüfung ist eine **vom Browser durchgesetzte** Kontrolle. Ein Skript ausserhalb des Browsers kann den Header `Origin` fälschen. Sie verhindert, dass dein Schlüssel auf fremden Websites funktioniert, nicht gezielte Angriffe. Dagegen helfen die weiteren Schichten: IP-Freigabe bei privaten Projekten, Rate Limits, Honeypot, Mindestzeit, Grössenlimits und strikte Validierung.

Die Identität (`identity`) kommt von der Host-Seite und wird **nicht** verifiziert (bewusst kein HMAC). Eine Person kann sie im Browser ändern. Für private Anwendungen schützt die IP-Freigabe.

## Rate Limits

Ohne Werte in der Konfiguration ist kein Limit aktiv (siehe [`configuration.md`](configuration.md#limits)). Für öffentliche Websites solltest du mindestens `FEEDBACK_LIMIT_PER_IP_PER_MINUTE` und ein Tageslimit pro Projekt setzen.

## Daten

| Risiko | Massnahme |
|---|---|
| Schädliche Dateien statt Screenshot | Nur JPEG, PNG, WebP, Inhalt per `finfo`, Dimension und 6 MB geprüft. Das Bild liegt als BLOB in der Datenbank und wird nie ausgeliefert oder ausgeführt |
| XSS über gespeicherte Texte | Rohtext in der Datenbank, Ausgabe nur escaped. Das Widget setzt Texte nur als `textContent` |
| SQL-Injection | Eloquent mit Bindings |
| Geheimnisse in Konsolenlogs | Redaktion im Browser vor dem Senden (Bearer-Tokens, JWTs, `password=`/`token=`/`secret=`/`apikey=`, E-Mail-Adressen, Kartennummern, lange Hex-/Base64-Strings), URLs ohne Query, Grössenlimits |
| Personendaten im Screenshot | `data-feedback-mask`, Passwortfelder und `autocomplete=cc-*` immer verdeckt. Maskiert wird schon beim Rendern, also auch im lokalen Entwurf |
| Fehlerdetails | Antworten enthalten nur Fehlercodes, nie Stacktraces oder SQL |
| Activity-Log | ohne `message`, `email`, `name`, `ip` |
| Lieferkette | UI im eigenen Paket, gepinnte Abhängigkeiten und Actions, npm-Provenance, SRI-Hash für das Script-Tag |
| Schlüssel-Leck | `feedback:project:rotate-key`, `--deactivate` |

Der Entwurf bleibt lokal im Browser (`sessionStorage`, `IndexedDB`, nur dieser Tab) und wird nach dem Senden, beim Entfernen des Screenshots und nach 24 Stunden gelöscht.

## Datenschutz (nDSG, DSGVO)

Das Widget erfasst ab `init()` Browser- und Systemdaten, Konsolenfehler und fehlgeschlagene Requests (redigiert) und sendet sie zusammen mit E-Mail, Feedback, Screenshot und IP beim Absenden. Das Widget enthält bewusst keine Info-Seite und keine Hinweiszeile. **Die Datenschutzerklärung der Host-Website muss das Feedback-Widget erwähnen**: Zweck (Bearbeitung von Rückmeldungen), erfasste Daten, Speicherort, Aufbewahrung. Aufbewahrung und Löschung alter Einträge regelst du direkt in der Datenbank. Das ersetzt keine juristische Prüfung.

## Schwachstellen melden

Siehe [`SECURITY.md`](../SECURITY.md).
