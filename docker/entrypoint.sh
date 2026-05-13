#!/bin/sh
# Ensure runtime directories exist and are writable by www-data.
#
# logs/ and tmp/ are gitignored, so they are absent on a fresh clone.
# Creating them here (inside the container) creates them on the host too,
# because the entire project is bind-mounted at /var/www/html.
# Re-chowning every start also corrects root-owned files left by a root deploy.
mkdir -p \
    /var/www/html/logs \
    /var/www/html/tmp/cache/models \
    /var/www/html/tmp/cache/persistent \
    /var/www/html/tmp/cache/views \
    /var/www/html/tmp/sessions \
    /var/www/html/tmp/tests
chown -R www-data:www-data /var/www/html/tmp /var/www/html/logs
exec "$@"
