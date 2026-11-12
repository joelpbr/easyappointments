# Easy!Appointments image.
#
#   production  (default)  the application with its dependencies and compiled assets baked in
#   development            the PHP runtime plus Composer and Xdebug, the code is mounted from the host

# 1. PHP dependencies
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --ignore-platform-reqs

# 2. Frontend assets (vendor libraries, minified JS, compiled CSS)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json gulpfile.js babel.config.json ./
RUN npm ci
COPY assets ./assets
RUN npx gulp compile

# 3. PHP + Apache runtime shared by both targets
FROM php:8.3-apache-trixie AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd ldap mysqli opcache \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini $PHP_INI_DIR/conf.d/app.ini
COPY docker/apache.conf /etc/apache2/conf-enabled/app.conf
COPY --chmod=755 docker/cron.sh /usr/local/bin/ea-cron
ENV TZ=Europe/Madrid
WORKDIR /var/www/html

# 4. Development target
FROM base AS development
RUN install-php-extensions xdebug zip
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# 5. Production target (last stage, so it is the default)
FROM base AS production
COPY index.php ./
COPY application ./application
COPY system ./system
COPY --chown=www-data:www-data storage ./storage
COPY docker/config.php ./config.php
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/assets ./assets
