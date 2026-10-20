# syntax=docker/dockerfile:1
#
# Laravel 13 + Filament 5 on Render (or any Docker host).
# IMPORTANT: PHP_VERSION must satisfy the "php" line in your composer.json, and should be the same
# major.minor you use locally (run `php -v`). If they differ, `composer install` can fail with
# "Your lock file does not contain a compatible set of packages".
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_NO_INTERACTION=1 \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

# PHP extensions Laravel + Filament need (intl is required by Filament; pdo_pgsql for Render Postgres)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
 && install-php-extensions intl zip bcmath opcache pdo_mysql pdo_pgsql \
 && apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: serve /public, allow .htaccess, small worker count (Render free = 512 MB RAM)
RUN a2enmod rewrite headers \
 && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
 && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && sed -ri -e "s/AllowOverride None/AllowOverride All/g" /etc/apache2/apache2.conf \
 && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache-tuning.conf /etc/apache2/conf-available/tuning.conf
RUN a2enconf tuning

WORKDIR /var/www/html

# 1) Dependencies first. This layer is rebuilt ONLY when composer.json / composer.lock change.
#    --no-scripts: package scripts boot Laravel, which needs .env / database and would fail during build.
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader

# 2) Application code (.dockerignore keeps your local vendor/, .env and bootstrap/cache/*.php out)
COPY . .

RUN composer dump-autoload --no-dev --optimize --no-scripts \
 && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN sed -i 's/\r$//' /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Render sends traffic to $PORT (default 10000); start.sh points Apache at it.
EXPOSE 10000
CMD ["start.sh"]
