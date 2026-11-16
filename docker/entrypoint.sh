#!/bin/sh
#
# Container entrypoint for the Smart Garment Defect Detection backend.
#
# The image is immutable: configuration comes from environment variables and
# secrets injected by the platform (Docker Compose, ECS, Kubernetes). This
# script only prepares writable directories, optionally waits for the database,
# optionally migrates/seeds, and warms Laravel's caches.

set -eu

APP_ROOT="/var/www/html"
cd "$APP_ROOT"

log() {
    printf '%s [entrypoint] %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*"
}

truthy() {
    case "${1:-}" in
        1|true|TRUE|yes|YES|on|ON) return 0 ;;
        *) return 1 ;;
    esac
}

prepare_directories() {
    mkdir -p \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache

    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
    chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true
}

wait_for_database() {
    attempts=0
    max_attempts="${DB_WAIT_ATTEMPTS:-30}"

    until php -r '
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s",
            getenv("DB_HOST") ?: "127.0.0.1",
            getenv("DB_PORT") ?: "3306",
            getenv("DB_DATABASE") ?: ""
        );
        new PDO($dsn, getenv("DB_USERNAME") ?: "", getenv("DB_PASSWORD") ?: "");
    ' >/dev/null 2>&1; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge "$max_attempts" ]; then
            log "ERROR: database unreachable after ${max_attempts} attempts."
            exit 1
        fi

        log "waiting for database (${attempts}/${max_attempts})..."
        sleep 2
    done

    log "database is reachable."
}

prepare_directories

if [ -z "${APP_KEY:-}" ]; then
    if [ "${APP_ENV:-production}" = "production" ]; then
        log "ERROR: APP_KEY is not set. Inject it from a secret store (AWS Secrets Manager, SSM, etc.)."
        exit 1
    fi

    log "APP_KEY is not set; generating an ephemeral key for ${APP_ENV:-local}."
    php artisan key:generate --force --no-interaction
fi

if truthy "${WAIT_FOR_DB:-false}"; then
    wait_for_database
fi

if truthy "${RUN_MIGRATIONS:-false}"; then
    log "running database migrations."
    php artisan migrate --force --no-interaction
fi

if truthy "${RUN_SEED:-false}"; then
    log "seeding reference data and accounts."
    php artisan db:seed --force --no-interaction
fi

if truthy "${OPTIMIZE:-true}"; then
    log "warming application caches."
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
    php artisan event:cache --no-interaction
fi

# Caches written above are owned by root; hand them back to the runtime user.
prepare_directories

log "starting: $*"
exec "$@"
