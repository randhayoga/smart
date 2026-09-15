#!/bin/sh
set -e

echo "==> Preparing SMART application for production..."

# Ensure storage directories exist and clear stale bootstrap cache
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public
rm -f bootstrap/cache/*.php
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# Optimize configuration, routes, and views in production
if [ "$APP_ENV" = "production" ]; then
    echo "==> Optimizing configuration, routes, and views..."
    php artisan optimize || true
fi

PORT="${OCTANE_PORT:-8000}"
WORKERS="${OCTANE_WORKERS:-auto}"
MAX_REQUESTS="${OCTANE_MAX_REQUESTS:-1000}"

echo "==> Starting Laravel Octane with FrankenPHP (port: $PORT, workers: $WORKERS, max-requests: $MAX_REQUESTS)..."
exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port="$PORT" \
    --admin-port=2019 \
    --workers="$WORKERS" \
    --max-requests="$MAX_REQUESTS" \
    --caddyfile=Caddyfile
