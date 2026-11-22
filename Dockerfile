FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-reqs

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* vite.config.js ./
COPY resources ./resources
COPY --from=vendor /app/vendor ./vendor
RUN npm install --no-audit --no-fund && npm run build

FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql zip bcmath \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && php -r "require 'vendor/autoload.php';" \
    && chmod +x docker/entrypoint.sh
EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
