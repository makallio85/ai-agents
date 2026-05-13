#!/bin/sh
# Fix ownership of bind-mounted volumes before PHP-FPM starts.
# The Dockerfile chown only applies to image layers; when Docker mounts
# host directories over tmp/ and logs/, those host-side files may be
# owned by root. Re-chowning here ensures www-data can write on every
# container start, regardless of host ownership.
chown -R www-data:www-data /var/www/html/tmp /var/www/html/logs
exec "$@"
