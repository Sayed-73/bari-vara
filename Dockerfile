FROM richarvey/nginx-php-fpm:3.1.6-php8.3

COPY . /var/www/html

ENV WEBROOT /var/www/html/public
ENV APP_ENV production
ENV APP_DEBUG false

RUN composer install --no-dev --optimize-autoloader

# Laravel Caching
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

EXPOSE 80
