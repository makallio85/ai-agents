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

docker compose -f docker-compose.prod.yml up -d --build

# composer install writes to vendor/ which is owned by root — run as root.
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

# Migrations run as root — they write schema-dump-default.lock back into
# config/Migrations/ which is part of the root-owned source tree.
docker compose -f docker-compose.prod.yml exec -T app bin/cake migrations migrate

for plugin_dir in "$APP_DIR"/plugins/*/; do
    plugin_name=$(basename "$plugin_dir")
    if [ -d "${plugin_dir}config/Migrations" ]; then
        echo "Running migrations for plugin: $plugin_name"
        docker compose -f docker-compose.prod.yml exec -T app bin/cake migrations migrate --plugin "$plugin_name"
    fi
done

# Seeds and cache clear run as www-data — they write to tmp/ and logs/
# which must stay writable by PHP-FPM.
docker compose -f docker-compose.prod.yml exec -T --user www-data app bin/cake seeds run InitialDataSeed
docker compose -f docker-compose.prod.yml exec -T --user www-data app bin/cake seeds run AdminUserSeed
docker compose -f docker-compose.prod.yml exec -T --user www-data app bin/cake cache clear_all

# Gracefully restart PHP-FPM to clear opcache so updated PHP files are
# picked up immediately.
docker compose -f docker-compose.prod.yml exec -T app kill -USR2 1 || true

echo "Deployment completed"
