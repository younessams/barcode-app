# syntax=docker/dockerfile:1

FROM node:22-bookworm-slim AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --no-scripts

COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY artisan ./
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM dunglas/frankenphp:1-php8.3-bookworm AS runtime
WORKDIR /app

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    mbstring \
    opcache \
    xml \
    zip

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/start.sh /usr/local/bin/start-container

RUN chmod +x /usr/local/bin/start-container \
    && mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    FILESYSTEM_DISK=local \
    PORT=8080

EXPOSE 8080

CMD ["start-container"]
