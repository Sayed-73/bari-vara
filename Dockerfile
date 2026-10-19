FROM php:8.3-apache

# প্রয়োজনীয় সিস্টেম লাইব্রেরি এবং PHP এক্সটেনশন ইনস্টল করা
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql

# Apache এর জন্য Mod_Rewrite এনাবল করা (Laravel এর রাউটিং এর জন্য জরুরি)
RUN a2enmod rewrite

# Composer ইনস্টল করা
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# প্রজেক্ট ফাইল কপি করা
COPY . /var/www/html

# Apache এর রুট ডিরেক্টরি Laravel public ফোল্ডারে সেট করা
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# লারাভেল ডিরেক্টরি পারমিশন ঠিক করা
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

WORKDIR /var/www/html

ENV APP_ENV production
ENV APP_DEBUG false

# কম্পোজার ডিপেন্ডেন্সি ইনস্টল
RUN composer install --no-dev --optimize-autoloader

# Laravel Caching
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

EXPOSE 80