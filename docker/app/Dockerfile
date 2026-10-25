# Stage 1: Build Node & NPM assets (Frontend)
FROM node:20-alpine as frontend
WORKDIR /app
COPY . .
RUN npm ci
RUN npm run build

# Stage 2: Build Composer Dependencies
FROM composer:latest as backend
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Stage 3: Production Image
FROM php:8.3-fpm

# Install exact required dependencies only (No NodeJS here)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip intl gd
RUN pecl install redis && docker-php-ext-enable redis

# Secure PHP Configurations
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/expose_php = On/expose_php = Off/' "$PHP_INI_DIR/php.ini"

WORKDIR /var/www

# Copy files from previous stages
COPY --from=backend /app /var/www
COPY --from=frontend /app/public/build /var/www/public/build

# Set strict permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
