#!/bin/sh
set -e

echo "Installing Composer dependencies..."
composer install --no-interaction --optimize-autoloader

echo "Installing npm dependencies..."
npm install && npm run build


echo "Starting Laravel Octane with FrankenPHP..."
exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --workers=auto --max-requests=auto --port=8000