#!/usr/bin/env bash
set -e

APP_DIR="/srv/apps/ai-agents"
REPO="git@github.com:makallio85/ai-agents.git"

if [ ! -d "$APP_DIR/.git" ]; then
    echo "First deploy: cloning repository"

    rm -rf "$APP_DIR"
    git clone "$REPO" "$APP_DIR"
else
    echo "Updating existing repository"

    cd "$APP_DIR"

    git fetch origin
    git reset --hard origin/master
fi

cd "$APP_DIR"

# Write git commit info so the PHP app can display it without needing
# access to .git (which may be owned by a different user than PHP-FPM).
GIT_HASH=$(git log -1 --format=%h)
GIT_MSG=$(git log -1 --format=%s)
cat > config/git_version.php <<EOF
<?php
return ['hash' => '${GIT_HASH}', 'message' => '${GIT_MSG}'];
EOF

docker compose -f docker-compose.prod.yml down --remove-orphans || true

# Fix ownership on the host before the container starts so PHP-FPM
# (www-data, uid=33 in the Debian-based image) can write to logs/ and
# tmp/cache/ from the very first request. The chown at the end of this
# script runs too late — PHP-FPM has already started and created files
# as root by then.
chown -R 33:33 "$APP_DIR/logs" "$APP_DIR/tmp" 2>/dev/null || true

docker compose -f docker-compose.prod.yml up -d --build

docker compose -f docker-compose.prod.yml exec -T app composer install --no-dev --optimize-autoloader

# Wait for MariaDB to be healthy before running migrations.
echo "Waiting for database to be ready..."
for i in $(seq 1 30); do
    if docker compose -f docker-compose.prod.yml exec -T db healthcheck.sh --connect --innodb_initialized > /dev/null 2>&1; then
        echo "Database is ready."
        break
    fi
    if [ "$i" -eq 30 ]; then
        echo "ERROR: Database did not become ready in time." >&2
        exit 1
    fi
    sleep 2
done

# Run app migrations
docker compose -f docker-compose.prod.yml exec -T app bin/cake migrations migrate

# Run plugin migrations (discovers any plugin that ships its own Migrations folder)
for plugin_dir in "$APP_DIR"/plugins/*/; do
    plugin_name=$(basename "$plugin_dir")
    if [ -d "${plugin_dir}config/Migrations" ]; then
        echo "Running migrations for plugin: $plugin_name"
        docker compose -f docker-compose.prod.yml exec -T app bin/cake migrations migrate --plugin "$plugin_name"
    fi
done

# cakephp/migrations v5 uses positional arguments: `seeds run <Name>`
docker compose -f docker-compose.prod.yml exec -T app bin/cake seeds run InitialDataSeed
docker compose -f docker-compose.prod.yml exec -T app bin/cake seeds run AdminUserSeed

docker compose -f docker-compose.prod.yml exec -T app bin/cake cache clear_all

# Fix ownership of tmp/ and logs/ so PHP-FPM (www-data) can read and write
# cache and log files. Without this, files created by a root-owned deploy
# remain unreadable/unwritable by www-data.
docker compose -f docker-compose.prod.yml exec -T --user root app \
  chown -R www-data:www-data /var/www/html/tmp/ /var/www/html/logs/

# Gracefully restart PHP-FPM to clear opcache so updated PHP files are
# picked up immediately. Without this, opcache may serve stale bytecode
# compiled from the previous deploy's source files.
docker compose -f docker-compose.prod.yml exec -T app \
  kill -USR2 1 || true

echo "Deployment completed"
