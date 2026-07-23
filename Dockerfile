# Dockerfile — Laravel 13 + Inertia/React + Vite (pnpm) + PHP-FPM/Nginx
# ---------------------------------------------------------------------------
# Stage 1: build — dependencias PHP (Composer) y assets (Vite/pnpm).
# Se usa una imagen con PHP 8.4 + Node 20 porque el build de Vite ejecuta
# `php artisan wayfinder:generate` (plugin @laravel/vite-plugin-wayfinder),
# así que necesita PHP y la app booteable durante la compilación del frontend.
# ---------------------------------------------------------------------------
FROM php:8.4-cli AS build

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev libpng-dev libonig-dev ca-certificates curl gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencias PHP primero (mejor cacheo de capas)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# Dependencias JS con pnpm (vía corepack); .npmrc y pnpm-workspace.yaml
# definen ignore-scripts y allowBuilds (unrs-resolver).
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
RUN corepack enable && corepack prepare pnpm@11.16.0 --activate
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN pnpm install --frozen-lockfile

# Código de la app + autoload optimizado (package:discover necesita la app)
COPY . .
RUN mkdir -p database bootstrap/cache \
    && touch database/database.sqlite \
    && composer dump-autoload --optimize --no-dev --ignore-platform-reqs

# Build de Vite (genera public/build/manifest.json y tipos de wayfinder).
# APP_KEY dummy solo para que artisan bootee durante el build; en runtime se usa
# la APP_KEY real inyectada por el entorno.
ENV NODE_ENV=production
ENV APP_KEY=base64:n/ShdI30tjZLk3Ogks89VemxsZI7gNcq3wz6SL73Yms=
RUN pnpm run build

# ---------------------------------------------------------------------------
# Stage 2: runtime — PHP-FPM + Nginx + Supervisord en un solo contenedor.
# ---------------------------------------------------------------------------
FROM php:8.4-fpm AS runtime

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx supervisor \
        libpng-dev libonig-dev libxml2-dev libzip-dev libpq-dev unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/uploads.ini "$PHP_INI_DIR/conf.d/zz-uploads.ini"

WORKDIR /app

# Código de la app (sin vendor/node_modules/public build/.env — ver .dockerignore)
COPY . .
# vendor, autoload optimizado y assets compilados desde la etapa build
COPY --from=build /app/vendor ./vendor
COPY --from=build /app/bootstrap/cache ./bootstrap/cache
COPY --from=build /app/public/build ./public/build

# Directorios y permisos de escritura
RUN mkdir -p storage/framework/sessions storage/framework/views \
        storage/framework/cache storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# PHP-FPM escucha por TCP en 127.0.0.1:9000 (Nginx lo consume en el mismo contenedor)
RUN sed -i 's|^listen = .*|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/www.conf

# Nginx + Supervisord
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80

CMD ["/usr/local/bin/entrypoint"]
