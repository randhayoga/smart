#!/bin/sh
set -e

# Container startup entrypoint: manages dependencies, file permissions, database migrations, and starts Octane

mkdir -p storage/framework/cache

# Composer dependencies check
COMPOSER_HASH_FILE="storage/framework/cache/.composer-hash"
CURRENT_COMPOSER_HASH=$(md5sum composer.json composer.lock 2>/dev/null || true)
if [ ! -d "vendor" ] || [ ! -f "$COMPOSER_HASH_FILE" ] || [ "$(cat "$COMPOSER_HASH_FILE" 2>/dev/null)" != "$CURRENT_COMPOSER_HASH" ]; then
    echo "Composer dependencies changed or missing. Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
    echo "$CURRENT_COMPOSER_HASH" > "$COMPOSER_HASH_FILE"
else
    echo "Composer dependencies are up to date. Skipping composer install."
fi

# npm dependencies check
NPM_HASH_FILE="storage/framework/cache/.npm-hash"
CURRENT_NPM_HASH=$(md5sum package.json package-lock.json 2>/dev/null || true)
if [ ! -d "node_modules" ] || [ ! -f "$NPM_HASH_FILE" ] || [ "$(cat "$NPM_HASH_FILE" 2>/dev/null)" != "$CURRENT_NPM_HASH" ]; then
    echo "npm dependencies changed or missing. Installing npm dependencies..."
    npm install --prefer-offline --no-audit --no-fund
    echo "$CURRENT_NPM_HASH" > "$NPM_HASH_FILE"
else
    echo "npm dependencies are up to date. Skipping npm install."
fi

echo "Fixing storage and cache permissions..."
chmod -R 775 storage bootstrap/cache || true

echo "Running database migrations..."
php artisan migrate --force

echo "Starting Laravel Octane with FrankenPHP..."
exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --workers=auto --max-requests=auto --port=80 --admin-port=2019 --caddyfile=Caddyfile