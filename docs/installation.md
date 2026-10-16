# Installation und Betrieb

Diese Anleitung richtet die Feedback-API ein. Wie du das Widget auf einer Website einbindest, steht in [`widget-setup.md`](widget-setup.md).

## Anforderungen

| Komponente | Version |
|---|---|
| PHP | 8.5 mit `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql`, `fileinfo`, `gd` (nur für Tests), `intl` |
| Composer | 2 |
| Datenbank | MySQL 8 / MariaDB 10.11+ oder PostgreSQL 17. SQLite für lokale Entwicklung und Tests |
| Node | 22 oder neuer (nur zum Bauen des Widgets) |
| Webserver | nginx oder Apache mit PHP-FPM, oder Laravel Octane |

Es braucht keinen Queue-Worker, kein Redis und keinen Objektspeicher. Screenshots liegen als BLOB in der Datenbank.

## Setup

```bash
git clone https://github.com/codebar-ag/feedback.git && cd feedback
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Trage in `.env` die Datenbank und die öffentliche Adresse ein, dann:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Lege danach das erste Projekt an (siehe [`commands.md`](commands.md)):

```bash
php artisan feedback:project:create "Beispiel Website" --origin=https://www.beispiel.ch
```

Lokal genügt SQLite:

```bash
touch database/database.sqlite
php artisan migrate
php artisan serve
```

## `.env`-Variablen

| Variable | Bedeutung |
|---|---|
| `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL` | Laravel-Standard. In Produktion `APP_DEBUG=false` |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Datenbank (`sqlite`, `mysql`, `mariadb`, `pgsql`) |
| `CACHE_STORE` | Store für das Rate Limiting (`file`, `redis`, `database` …). Bei mehreren App-Servern einen gemeinsamen Store wählen |
| `TRUSTED_PROXIES` | Kommagetrennte IPs/CIDR des Load Balancers oder CDNs, `*` für alle. Leer = keinem Proxy vertrauen |
| `FEEDBACK_ENDPOINT` | Öffentliche Basis-URL der API für das Init-Snippet |
| `FEEDBACK_IP_MODE` | `full` oder `anonymized` |
| `FEEDBACK_GEO` | `none` (oder `off`), `cloudflare`, `maxmind` |
| `MAXMIND_ACCOUNT_ID`, `MAXMIND_LICENSE_KEY`, `FEEDBACK_GEOIP_PATH` | Nur für `maxmind` |
| `FEEDBACK_DEFAULT_COLOR` | Farbe für Projekte ohne eigene Farbe (`black`, `white`, Hex) |
| `FEEDBACK_LIMIT_PER_IP_PER_MINUTE`, `FEEDBACK_LIMIT_PER_PROJECT_PER_MINUTE` | Rate Limits, leer = aus |
| `FEEDBACK_MIN_ELAPSED_MS` | Mindestzeit zwischen Öffnen und Senden, leer = aus |

Alle Werte und Standards erklärt [`configuration.md`](configuration.md).

## MySQL / MariaDB

Die Migration stellt die Spalte `feedback_screenshots.data` auf `LONGBLOB` um (Laravels `binary()` wäre nur ein `BLOB` mit 64 KB). Ein Screenshot ist bis 6 MB gross, darum braucht der Server ein ausreichend grosses Paketlimit:

```ini
# my.cnf
[mysqld]
max_allowed_packet = 16M
```

Auf PostgreSQL ist die Spalte `bytea`, auf SQLite `blob`. Eine Anpassung ist dort nicht nötig.

## Webserver

Der Screenshot kommt als `multipart/form-data` bis 6 MB. PHP und der Webserver müssen etwas mehr annehmen:

```ini
; php.ini
post_max_size = 8M
upload_max_filesize = 8M
```

nginx-Beispiel:

```nginx
server {
    listen 443 ssl http2;
    server_name feedback.codebar.ch;
    root /var/www/feedback/public;
    index index.php;

    client_max_body_size 8m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Optional: Widget-Datei für die Script-Tag-Variante (npm run publish:api)
    location /widget/ {
        add_header Cache-Control "public, max-age=3600";
        add_header Access-Control-Allow-Origin "*";
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Die API setzt ihre CORS-Header selbst und pro Projekt. Füge im Webserver für `/v1/` **keine** eigenen CORS-Header hinzu.

## Trusted Proxies

Hinter einem Load Balancer oder CDN ist `$request->ip()` sonst die Adresse des Proxys. Dann greift die IP-Freigabe falsch, und Rate Limits treffen alle gemeinsam. Trage die Proxy-Adressen ein:

```dotenv
TRUSTED_PROXIES=10.0.0.10,10.0.0.11
```

Hinter Cloudflare sind das die [Cloudflare-IP-Bereiche](https://www.cloudflare.com/ips/). `*` nur, wenn die App ausschliesslich über den Proxy erreichbar ist.

## Widget von der API ausliefern (optional)

Für Websites ohne Build-Pipeline kann die API die Script-Tag-Datei selbst ausliefern:

```bash
npm --prefix widget ci
npm --prefix widget run build
npm --prefix widget run publish:api
```

Das kopiert `feedback.js`, den Screenshot-Chunk und `sri.json` nach `public/widget/v1/`. Der SRI-Hash für das Script-Tag steht in `public/widget/v1/sri.json`.

## Scheduler

Nur wenn du `FEEDBACK_GEO=maxmind` nutzt: `geoip:update` läuft monatlich über den Laravel-Scheduler.

```cron
* * * * * cd /var/www/feedback && php artisan schedule:run >> /dev/null 2>&1
```

Lade die Datenbank beim ersten Mal von Hand: `php artisan geoip:update`.

## Deployment-Checkliste

- [ ] HTTPS erzwungen, `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `TRUSTED_PROXIES` gesetzt (oder bewusst leer)
- [ ] `post_max_size` und `upload_max_filesize` mindestens 8 MB, `client_max_body_size 8m`
- [ ] MySQL: `max_allowed_packet` mindestens 16 MB
- [ ] `CACHE_STORE` ist bei mehreren Servern ein gemeinsamer Store
- [ ] Rate Limits und Tageslimits bewusst gesetzt oder bewusst leer gelassen
- [ ] `php artisan migrate --force`, `config:cache`, `route:cache`
- [ ] Health-Check auf `GET /up` (200 `{"status":"ok"}`, 503 bei Datenbankproblem)
- [ ] Scheduler aktiv, falls `FEEDBACK_GEO=maxmind`
- [ ] Tägliche Datenbank-Sicherung mit Wiederherstellungstest (Screenshots liegen in der Datenbank)
- [ ] Datenschutzerklärung der Host-Websites erwähnt das Feedback-Widget
