FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json ./
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM php:8.3-apache

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip curl default-mysql-client zlib1g-dev libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN php artisan package:discover --ansi \
    && php artisan view:clear \
    && php artisan config:clear \
    && php artisan route:clear

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://localhost/up || exit 1

CMD ["apache2-foreground"]
