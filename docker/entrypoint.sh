#!/bin/sh
set -e

[ -f .env ] || cp .env.example .env
if ! grep -q '^APP_KEY=base64' .env && [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi
php artisan package:discover --ansi

echo "Waiting for the database..."
tries=0
until php artisan migrate --force; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then echo "Database is not reachable, giving up."; exit 1; fi
    sleep 3
done

# Idempotent: skips shipments when data already exists
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port=8000
