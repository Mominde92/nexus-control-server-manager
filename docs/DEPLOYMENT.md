# Deployment Guide

Production deployment of the Server Manager stack: `server-manager-api` (Laravel
REST API) and `server-manager-ui` (React SPA).

---

## 1. Architecture

```
                    ┌─────────────────┐
   browser ────────▶│  ui  (nginx)    │  static SPA bundle
                    └─────────────────┘
                             │  XHR (cookies, credentials: include)
                             ▼
                    ┌─────────────────┐      ┌──────────────┐
                    │  web (nginx)    │─────▶│ api (php-fpm)│
                    └─────────────────┘      └──────┬───────┘
                                                    │
                             ┌──────────────────────┼──────────────────────┐
                             ▼                      ▼                      ▼
                      ┌────────────┐        ┌──────────────┐       ┌─────────────┐
                      │  mysql 8.4 │        │ queue worker │       │  scheduler  │
                      └────────────┘        └──────────────┘       └─────────────┘
                             ▲                      │                      │
                             └──────── redis ◀──────┴──────────────────────┘
```

`api`, `queue` and `scheduler` all run the **same image**, differentiated only by
`CONTAINER_ROLE`. Only the `api` role runs migrations, so scaling replicas never
races the schema.

---

## 2. Critical prerequisite: the cookie domain

The SPA authenticates with Sanctum in **stateful (cookie) mode**. The session
cookie is `httpOnly`, so JavaScript never holds a token — but this only works if
the API and SPA are **same-site**. Cookies ignore ports, so `localhost:5173` and
`localhost:8000` are already same-site in development.

In production, host both under one registrable domain:

| Component | Host                  |
| --------- | --------------------- |
| SPA       | `app.example.com`     |
| API       | `api.example.com`     |

and set:

```dotenv
APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
SESSION_DOMAIN=.example.com      # leading dot — shared by both subdomains
SESSION_SECURE_COOKIE=true       # HTTPS only
SESSION_SAME_SITE=lax
```

> **If you must serve the SPA from a different registrable domain** (e.g.
> `app.acme.io` + `api.example.com`), cookies cannot be shared. Either put both
> behind one domain via a reverse proxy (recommended), or switch the SPA to
> Bearer tokens from `POST /api/auth/tokens` — at the cost of storing a token in
> JavaScript, which is XSS-readable.

---

## 3. Environment configuration

```bash
cp server-manager-api/.env.example server-manager-api/.env
```

Then set, at minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false                  # never true in production — leaks stack traces
APP_KEY=                         # php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=server_manager
DB_USERNAME=server_manager
DB_PASSWORD=<strong-password>

CACHE_STORE=redis                # database cache does not scale across replicas
QUEUE_CONNECTION=redis
SESSION_DRIVER=database          # or redis

MAIL_MAILER=smtp                 # required for password reset + expiry digests
MAIL_HOST=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@example.com

EXPIRY_NOTIFICATION_DIGEST_RECIPIENT=ops@example.com
```

### ⚠️ APP_KEY is the encryption root

Every hosting/domain/FTP/registrar password is encrypted with `APP_KEY` via
`Crypt::encryptString()`. Consequences:

- **Losing `APP_KEY` permanently destroys every stored credential.** Back it up
  in a secret manager, separately from database backups.
- **Rotating `APP_KEY` invalidates existing ciphertext.** To rotate, keep the old
  key in `APP_PREVIOUS_KEYS` (comma separated) so decryption still succeeds,
  re-encrypt every credential column, then drop the old key. The
  `EncryptedString` cast degrades to `null` and logs a warning rather than
  throwing if it meets undecryptable data, so a botched rotation shows up as
  missing secrets, not a total outage.
- Never commit `.env`. It is excluded by `.dockerignore` and `.gitignore`.

---

## 4. Deploy with Docker Compose

```bash
docker compose up -d --build
docker compose exec api php artisan key:generate      # first boot only
docker compose exec api php artisan db:seed --force   # roles + initial admin
```

The entrypoint automatically runs migrations, caches config/routes/events (when
`APP_DEBUG != true`), and links storage.

Verify:

```bash
curl -fsS http://localhost:8000/up          # framework health
curl -fsS http://localhost:8000/api/health  # application health
docker compose ps                           # all services healthy
```

**Change the seeded admin password immediately** — see the installation guide for
the default credentials.

### Zero-downtime updates

```bash
docker compose build api ui
docker compose up -d --no-deps api          # migrations run here
docker compose up -d --no-deps queue scheduler web ui
```

Restart the workers after every deploy: `queue:work` holds the old code in
memory and will otherwise keep executing it.

---

## 5. Deploy without Docker

```bash
# --- API -------------------------------------------------------------------
cd server-manager-api
composer install --no-dev --optimize-autoloader
php artisan key:generate                    # first install only
php artisan migrate --force
php artisan db:seed --force                 # first install only
php artisan config:cache route:cache event:cache
php artisan storage:link
chown -R www-data:www-data storage bootstrap/cache

# --- UI --------------------------------------------------------------------
cd ../server-manager-ui
npm ci
VITE_API_URL=https://api.example.com npm run build
# deploy dist/ to the web root
```

`VITE_API_URL` is inlined at **build** time, so the bundle must be rebuilt to
point at a different API.

Reference nginx vhosts are provided at
`server-manager-api/docker/nginx/default.conf` (API: PHP-FPM upstream, security
headers, auth rate limiting) and `server-manager-ui/docker/nginx.conf` (SPA:
history fallback plus the immutable-assets / no-cache-index split that prevents
stale shells after a deploy).

### Queue worker + scheduler (systemd)

```ini
# /etc/systemd/system/server-manager-queue.service
[Unit]
Description=Server Manager queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/server-manager-api
ExecStart=/usr/bin/php artisan queue:work --tries=3 --backoff=5 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```cron
# crontab -u www-data -e
* * * * * cd /var/www/server-manager-api && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled work (declared in `routes/console.php`):

| Command                | Schedule     | Purpose                                     |
| ---------------------- | ------------ | ------------------------------------------- |
| `expiry:notify`        | daily 08:00  | Email digests for expiring hostings/domains |
| `activity:prune`       | weekly       | Trim the audit trail to the retention window |
| `db:backup`            | daily 02:00  | Gzipped `mysqldump` + rotation              |

---

## 6. Database backup strategy

`php artisan db:backup` writes `storage/app/backups/<db>-<timestamp>.sql.gz` and
prunes files older than `BACKUP_RETENTION_DAYS` (default 14). The password is
passed via a temporary defaults-file, never as a command-line argument, so it
cannot leak through the process list.

**A local-disk backup is not a backup.** Production requires:

1. **Off-host replication** — sync `storage/app/backups` to object storage
   (S3/GCS) with versioning and a lifecycle policy. In compose the directory is
   bind-mounted to `./backups` for exactly this purpose.
2. **`APP_KEY` stored separately** — a database dump without the key is
   undecryptable ciphertext for every credential column. Storing them together
   also means a single breach yields both.
3. **Point-in-time recovery** — enable binary logging (`log_bin`) for recovery
   between nightly dumps.
4. **Tested restores** — an untested backup is a hypothesis:
   ```bash
   gunzip < backups/server_manager-20260805-020000.sql.gz \
     | docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" server_manager
   ```
   Restore into a scratch database quarterly and confirm a credential decrypts.
5. **Retention** — keep 14 dailies, 8 weeklies, 12 monthlies.

---

## 7. Production hardening checklist

**Application**
- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] `APP_KEY` generated and backed up to a secret manager
- [ ] `config:cache`, `route:cache`, `event:cache` applied
- [ ] `composer install --no-dev`
- [ ] Seeded admin password rotated; seeded demo data removed
- [ ] OPcache on with `validate_timestamps=0` (redeploy, never hot-edit)

**Transport & cookies**
- [ ] HTTPS enforced; HSTS header enabled in both vhosts
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_DOMAIN` set to the shared parent
- [ ] `SANCTUM_STATEFUL_DOMAINS` lists the SPA host — and nothing else
- [ ] CORS `allowed_origins` is an explicit allow-list (never `*`; incompatible
      with credentialed requests anyway)

**Data**
- [ ] Database user is not `root` and is limited to the one schema
- [ ] Database port not published to the internet (drop the `ports:` mapping)
- [ ] Backups replicated off-host and a restore has been tested

**Operations**
- [ ] Rate limits reviewed (`AUTH_RATE_LIMIT`, `API_RATE_LIMIT`) plus the nginx
      `limit_req` zones
- [ ] Queue worker supervised and restarted on deploy
- [ ] `schedule:run`/`schedule:work` running (expiry notifications depend on it)
- [ ] Log aggregation configured; `LOG_LEVEL=warning` or above
- [ ] Health checks wired to `/up` and `/api/health`
- [ ] Audit-log retention matches your compliance policy

---

## 8. Scaling notes

- **API** is stateless — scale `api` replicas freely, but set
  `RUN_MIGRATIONS=false` on all but one.
- **Sessions/cache** must move off the `database` driver to Redis before running
  multiple replicas.
- **Queue** scales horizontally; add `queue` replicas and shard by queue name for
  priority work.
- **Database** is the bottleneck first. The schema already indexes the hot paths
  (`status`, `expiry_date`, the composite `(status, expiry_date)` used by the
  dashboard window scan, and every foreign key). Add a read replica before
  reaching for anything more exotic.
- **`Model::preventLazyLoading()`** is disabled in production but active
  everywhere else, so N+1 regressions surface in development and CI rather than
  as production latency.
