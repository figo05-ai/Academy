# syntax=docker/dockerfile:1.7
# Multi-stage production image. Runtime credentials must NEVER be provided as build args.
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

FROM php:8.3-fpm-bookworm AS php-build
RUN apt-get update && apt-get install -y --no-install-recommends \
    $PHPIZE_DEPS \
    libfreetype6-dev libicu-dev libjpeg62-turbo-dev libonig-dev \
    libpng-dev libwebp-dev libxml2-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
       bcmath dom exif gd intl mbstring opcache pcntl pdo_mysql \
       simplexml xml xmlreader xmlwriter zip \
    && pecl install redis-6.2.0 \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear /var/lib/apt/lists/*

FROM php:8.3-fpm-bookworm AS php-runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6 libicu72 libjpeg62-turbo libonig5 \
    libpng16-16 libwebp7 libxml2 libzip4 \
    && rm -rf /var/lib/apt/lists/*
COPY --from=php-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' \
       'expose_php=Off' \
       'opcache.enable=1' \
       'opcache.enable_cli=0' \
       'opcache.memory_consumption=192' \
       'opcache.interned_strings_buffer=16' \
       'opcache.max_accelerated_files=20000' \
       'opcache.validate_timestamps=0' \
       'realpath_cache_size=4096K' \
       'realpath_cache_ttl=600' \
       > "$PHP_INI_DIR/conf.d/zz-production.ini"

FROM php-runtime AS backend
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-interaction

FROM php-runtime AS production
LABEL org.opencontainers.image.source="https://github.com/figo05-ai/Academy" \
      org.opencontainers.image.title="Academy Contabo Application"
WORKDIR /var/www
ENV APP_ENV=production APP_DEBUG=false
COPY --from=backend /app /var/www
COPY --from=frontend /app/public/build /var/www/public/build
RUN test -f /var/www/public/index.php \
    && test -s /var/www/public/build/manifest.json \
    && mkdir -p \
       /var/www/storage/framework/cache \
       /var/www/storage/framework/sessions \
       /var/www/storage/framework/views \
       /var/www/storage/logs \
       /var/www/bootstrap/cache \
    && chown -R root:root /var/www \
    && find /var/www -type d -exec chmod 755 {} \; \
    && find /var/www -type f -exec chmod 644 {} \; \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R ug+rwX /var/www/storage /var/www/bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
