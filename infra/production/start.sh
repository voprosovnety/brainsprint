#!/bin/sh
set -eu
case "${PORT:-8080}" in *[!0-9]*|'') echo 'PORT must be numeric.' >&2; exit 1;; esac
if [ "${APP_ENV:-production}" = production ]; then
    php -r 'if (!str_starts_with(getenv("APP_URL") ?: "", "https://") || strlen(getenv("CONSUMER_PROXY_SECRET") ?: "") < 32 || !getenv("APP_KEY") || getenv("SESSION_DRIVER") !== "database") {fwrite(STDERR, "Production requires HTTPS APP_URL, APP_KEY, database sessions and a private proxy secret.\n"); exit(1);}'
fi
sed -i "s/Listen 8080/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -i "s/VirtualHost \*:8080/VirtualHost *:${PORT:-8080}/" /etc/apache2/sites-available/000-default.conf
php artisan config:cache --quiet
# Migrations, publication, maintenance and administrator creation are deliberate separate jobs.
exec "$@"
