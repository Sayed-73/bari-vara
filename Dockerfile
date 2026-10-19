FROM bitnami/laravel:11

COPY . /var/www/html

WORKDIR /var/www/html

ENV APP_ENV production
ENV APP_DEBUG false

RUN composer install --no-dev --optimize-autoloader

# Laravel Caching
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]