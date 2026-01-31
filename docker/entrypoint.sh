#!/bin/sh
set -e

# Create .env from .env.example if missing (e.g. first run with Docker)
if [ ! -f ".env" ]; then
    cp .env.example .env
    php artisan key:generate
fi

# Install dependencies if vendor doesn't exist (e.g. first run with volume mount)
if [ ! -d "vendor" ]; then
    composer install --no-interaction --optimize-autoloader
fi

# Fix storage/cache permissions
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

exec "$@"
