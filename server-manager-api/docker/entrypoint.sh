#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Container entrypoint.
#
# Prepares runtime state, then hands off to the given command (php-fpm, a queue
# worker, or the scheduler) via exec so signals reach PID 1 correctly.
# ---------------------------------------------------------------------------
set -euo pipefail

role="${CONTAINER_ROLE:-app}"

log() { printf '[entrypoint] %s\n' "$*"; }

# --- Writable paths ---------------------------------------------------------
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/backups \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# --- Sanity checks ----------------------------------------------------------
if [ -z "${APP_KEY:-}" ] && ! grep -qs '^APP_KEY=base64:' .env 2>/dev/null; then
    log "WARNING: APP_KEY is not set."
    log "Encrypted credentials cannot be read or written without it."
    log "Generate one with: php artisan key:generate"
fi

# --- Wait for the database --------------------------------------------------
# Only the roles that touch the DB on boot need to block on it.
if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    host="${DB_HOST:-mysql}"
    port="${DB_PORT:-3306}"
    log "Waiting for database at ${host}:${port} ..."

    for i in $(seq 1 60); do
        if php -r 'exit(@fsockopen(getenv("DB_HOST") ?: "mysql", (int)(getenv("DB_PORT") ?: 3306), $e, $s, 1) ? 0 : 1);'; then
            log "Database is reachable."
            break
        fi
        if [ "$i" -eq 60 ]; then
            log "ERROR: database not reachable after 60s."
            exit 1
        fi
        sleep 1
    done
fi

# --- Per-role bootstrap -----------------------------------------------------
case "$role" in
    app)
        # Migrations run only here so N replicas do not race each other.
        if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
            log "Running migrations ..."
            su-exec www-data php artisan migrate --force --no-interaction
        fi

        if [ "${SEED_DATABASE:-false}" = "true" ]; then
            log "Seeding database ..."
            su-exec www-data php artisan db:seed --force --no-interaction
        fi

        # Cache config/routes/events for production throughput. Skipped when
        # APP_DEBUG is on so local changes are picked up immediately.
        if [ "${APP_DEBUG:-false}" != "true" ]; then
            log "Caching configuration ..."
            su-exec www-data php artisan config:cache
            su-exec www-data php artisan route:cache
            su-exec www-data php artisan event:cache
        else
            su-exec www-data php artisan optimize:clear || true
        fi

        # Link storage for publicly served uploads (avatars).
        su-exec www-data php artisan storage:link --force 2>/dev/null || true
        ;;
    queue|scheduler)
        log "Starting as ${role}; skipping migrations."
        ;;
    *)
        log "Unknown CONTAINER_ROLE '${role}', continuing."
        ;;
esac

log "Starting: $*"
exec "$@"
