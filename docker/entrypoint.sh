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

# Ensure the <DB>_test database exists and the app user has full grants on it.
# Idempotent — runs every boot so existing previews (whose db volume predates
# docker/db/01-create-test-db.sh) pick up the fix without a volume wipe.
# Skipped in production. Uses root creds (DB_PASSWORD doubles as
# MARIADB_ROOT_PASSWORD in our compose).
if [ "${CAKE_ENV:-development}" != "production" ] && [ -n "${DB_PASSWORD:-}" ]; then
    php -r '
        $host = getenv("DB_HOST") ?: "db";
        $port = getenv("DB_PORT") ?: "3306";
        $pass = getenv("DB_PASSWORD");
        $testDb = (getenv("DB_NAME") ?: "app") . "_test";
        $appUser = getenv("DB_USER") ?: "app";
        try {
            $pdo = new PDO("mysql:host={$host};port={$port}", "root", $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$testDb}`.* TO \"{$appUser}\"@\"%\"");
            $pdo->exec("FLUSH PRIVILEGES");
            fwrite(STDOUT, "[entrypoint] Test DB {$testDb} ready (granted to {$appUser})\n");
        } catch (Throwable $e) {
            fwrite(STDERR, "[entrypoint] Test DB setup skipped: " . $e->getMessage() . PHP_EOL);
        }
    ' || true
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ] && [ -f bin/cake ]; then
    echo "[entrypoint] Running CakePHP migrations..."
    su -s /bin/sh www-data -c "bin/cake migrations migrate" 2>&1 \
        || echo "[entrypoint] Migrations failed or none defined — continuing"
fi

echo "[entrypoint] Handing off to: $*"
exec "$@"
