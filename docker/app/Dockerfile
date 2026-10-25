FROM node:20-alpine AS frontend
WORKDIR /app
COPY . .
RUN npm ci
RUN npm run build

FROM composer:latest AS backend
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip intl gd
RUN pecl install redis && docker-php-ext-enable redis

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/expose_php = On/expose_php = Off/' "$PHP_INI_DIR/php.ini"

WORKDIR /var/www

COPY --from=backend /app /var/www
COPY --from=frontend /app/public/build /var/www/public/build

RUN mkdir -p /var/www/storage/framework/views /var/www/storage/framework/cache /var/www/storage/framework/sessions \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/public

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
