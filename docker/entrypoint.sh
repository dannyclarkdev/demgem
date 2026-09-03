#!/bin/sh
set -e

# A named volume mounted at /app/storage hides whatever the image put there, so the
# framework directories are recreated on every boot, whatever the command. This is
# the most common way a Laravel container comes up broken.
mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Everything below is server start-up. A one-off command (php artisan ..., sh) skips
# it, so that "key:generate --show" works on a container that has no key yet, and so
# that the worker does not race the app to the migrations.
#
# The base image's entrypoint uses the same test: a leading "-" means the web server.
case "${1}" in
    -*) ;;
    *) exec docker-php-entrypoint "$@" ;;
esac

# No key, no boot. Generating one here would be worse than failing: an unpersisted
# key changes on every restart, which signs every member out and turns every
# encrypted value into junk.
if [ -z "${APP_KEY}" ]; then
    cat >&2 <<'MESSAGE'
demgem cannot start: APP_KEY is empty.

Generate one:

    docker compose run --rm --no-deps app php artisan key:generate --show

Copy the whole "base64:..." string into APP_KEY in .env.docker, then start again.
MESSAGE
    exit 1
fi

if [ "${AUTO_MIGRATE}" = "true" ]; then
    if [ -n "${DB_HOST}" ]; then
        echo "Waiting for ${DB_HOST}:${DB_PORT:-5432} ..."
        until pg_isready --host="${DB_HOST}" --port="${DB_PORT:-5432}" --quiet; do
            sleep 1
        done
    fi

    php artisan migrate --force --no-interaction
fi

php artisan storage:link --force --quiet || true
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet

# Hand over to the base image's entrypoint, which turns "--config ..." into
# "frankenphp run --config ...".
exec docker-php-entrypoint "$@"
