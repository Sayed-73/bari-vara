FROM serversideup/php:8.3-fpm-nginx

# প্রজেক্টের সব ফাইল সার্ভারে কপি করা
COPY --chown=www-data:www-data . /var/www/html

# লারাভেল ডিরেক্টরি পারমিশন ঠিক করা
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

WORKDIR /var/www/html

ENV APP_ENV production
ENV APP_DEBUG false

# কম্পোজার ডিপেন্ডেন্সি ইনস্টল
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Laravel Caching
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# ট্রিক: Nginx এর পোর্টকে রেন্ডারের ইন্টারনাল পোর্টের সাথে ফরোয়ার্ড করার কমান্ড
CMD ["sh", "-c", "sed -i 's/listen 8080;/listen '\"${PORT:-10000}\"';/g' /etc/nginx/sites-available/default && exec php-fpm"]
