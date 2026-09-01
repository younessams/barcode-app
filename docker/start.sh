#!/bin/sh
set -e

export PORT="${PORT:-8080}"

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
