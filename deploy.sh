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

docker compose -f docker-compose.prod.yml down --remove-orphans || true

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

docker compose -f docker-compose.prod.yml exec -T app bin/cake migrations seed --seed InitialDataSeed
docker compose -f docker-compose.prod.yml exec -T app bin/cake migrations seed --seed AdminUserSeed

docker compose -f docker-compose.prod.yml exec -T app bin/cake cache clear_all

echo "Deployment completed"
