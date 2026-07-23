#!/bin/sh
set -e

# Directorios y permisos de escritura de Laravel (por si el volumen los reinicia)
mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    storage/app/public \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Enlace de storage y cachés de config/vistas.
# No cacheamos rutas (route:cache) porque la ruta '/' usa una clausura.
php artisan storage:link 2>/dev/null || true
php artisan config:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true

# Migraciones (no aborta el arranque si la BD todavía no está lista)
php artisan migrate --force 2>/dev/null || true

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
