#!/bin/sh
set -e
cd /var/www/html

# Wait for the database container.
echo "Waiting for the database at ${DB_HOST}:${DB_PORT:-3306}..."
until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent; do
    sleep 2
done

# First run with an empty database: load the demo dump if one is mounted, otherwise migrate + seed reference data.
TABLES=$(mysql -N -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_DATABASE}'")
if [ "$TABLES" = "0" ]; then
    if [ -f /docker/seed/lav_sms.sql ]; then
        echo "Empty database: importing /docker/seed/lav_sms.sql"
        mysql -h"${DB_HOST}" -P"${DB_PORT:-3306}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" < /docker/seed/lav_sms.sql
    else
        echo "Empty database: running migrations and seeders"
        php artisan migrate --force --seed
    fi
fi

# Apply any new migrations, then cache config and views for speed (routes cannot be cached: two share the name "home").
php artisan migrate --force
php artisan storage:link >/dev/null 2>&1 || true
php artisan package:discover --ansi >/dev/null
php artisan config:cache
php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache

exec "$@"
