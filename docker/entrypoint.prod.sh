#!/bin/sh
set -e

echo "==> Preparing SMART application for production..."

# Ensure storage directories exist
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# Optimize configuration, routes, and views in production
if [ "$APP_ENV" = "production" ]; then
    echo "==> Optimizing configuration, routes, and views..."
    php artisan optimize || true
fi

WORKERS="${OCTANE_WORKERS:-auto}"
MAX_REQUESTS="${OCTANE_MAX_REQUESTS:-1000}"
HTTPS_FLAGS=""

if [ "$OCTANE_HTTPS" = "true" ] || [ "$OCTANE_HTTPS" = "1" ]; then
    echo "==> Direct TLS enabled (HTTPS, HTTP/2, HTTP/3, auto-certificates, HTTP redirect)..."
    HTTPS_FLAGS="--https --http-redirect"
fi

echo "==> Starting Laravel Octane with FrankenPHP (workers: $WORKERS, max-requests: $MAX_REQUESTS)..."
exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=80 \
    --admin-port=2019 \
    --workers="$WORKERS" \
    --max-requests="$MAX_REQUESTS" \
    --caddyfile=Caddyfile \
    $HTTPS_FLAGS
