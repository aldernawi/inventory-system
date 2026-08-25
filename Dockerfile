FROM node:24-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build


FROM php:8.4-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath curl gd intl mbstring opcache pdo_mysql zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY . ./
COPY --from=frontend /app/public/build ./public/build
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/entrypoint/entrypoint.sh /usr/local/bin/inventory-entrypoint

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/inventory-entrypoint

ENTRYPOINT ["inventory-entrypoint"]

HEALTHCHECK --interval=10s --timeout=5s --start-period=60s --retries=6 \
    CMD curl --fail --silent --show-error http://localhost/up || exit 1

CMD ["apache2-foreground"]
