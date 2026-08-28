#!/bin/sh
set -e

echo "Installing Composer dependencies..."
composer install --no-interaction --optimize-autoloader

echo "Installing npm dependencies..."
npm install
# npm run build #testing/production only

echo "Fixing storage and cache permissions..."
chmod -R 775 storage bootstrap/cache || true

echo "Running database migrations..."
php artisan migrate --force

echo "Starting Laravel Octane with FrankenPHP..."
exec php artisan octane:start --server=frankenphp --host=localhost --workers=auto --max-requests=auto --port=443 --admin-port=2019 --https --caddyfile=Caddyfile