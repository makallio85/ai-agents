#!/bin/sh
# entrypoint.sh — wait for db, optionally run migrations, then exec CMD.

set -e

# Ensure runtime dirs exist
mkdir -p \
    /var/www/html/logs \
    /var/www/html/tmp/cache/models \
    /var/www/html/tmp/cache/persistent \
    /var/www/html/tmp/cache/views \
    /var/www/html/tmp/sessions \
    /var/www/html/tmp/tests
chown -R www-data:www-data /var/www/html/tmp /var/www/html/logs 2>/dev/null || true

DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"

echo "[entrypoint] Waiting for db at ${DB_HOST}:${DB_PORT}..."
attempts=0
until php -r "
    \$s=@stream_socket_client('tcp://${DB_HOST}:${DB_PORT}', \$e, \$m, 2);
    exit(\$s===false ? 1 : 0);
" 2>/dev/null; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 60 ]; then
        echo "[entrypoint] DB unreachable after 60s — proceeding anyway"
        break
    fi
    sleep 1
done
echo "[entrypoint] DB reachable after ${attempts}s"

if [ "${RUN_MIGRATIONS:-false}" = "true" ] && [ -f bin/cake ]; then
    echo "[entrypoint] Running CakePHP migrations..."
    su -s /bin/sh www-data -c "bin/cake migrations migrate" 2>&1 \
        || echo "[entrypoint] Migrations failed or none defined — continuing"
fi

echo "[entrypoint] Handing off to: $*"
exec "$@"
