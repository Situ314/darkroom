#!/bin/sh
set -e

cd /app

php artisan migrate --force
php artisan optimize

# Queue worker: submits generations to Pixelforge. Restarted if it ever exits.
(
    while true; do
        php artisan queue:work --sleep=1 --tries=0 --max-time=3600 || true
        sleep 1
    done
) &

# Scheduler: runs `pixelforge:sync` every minute to catch missed webhooks.
php artisan schedule:work &

exec frankenphp php-server --root /app/public --listen ":${PORT:-8080}"
