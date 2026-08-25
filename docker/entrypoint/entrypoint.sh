#!/usr/bin/env sh
set -eu

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
