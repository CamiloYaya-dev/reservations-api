FROM composer:2 AS composer
FROM php:8.3-apache-bookworm AS base
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libxml2-dev unzip \
    && for ext in pdo_mysql mbstring dom xml xmlwriter; do \
         php -r "exit(extension_loaded('$ext') ? 0 : 1);" || docker-php-ext-install -j2 "$ext"; \
       done \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/application.ini
WORKDIR /var/www/html

FROM base AS app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts
COPY src ./src
COPY public ./public
COPY bin ./bin
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts

FROM base AS test
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-scripts
