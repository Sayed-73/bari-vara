FROM serversideup/php:8.3-fpm-nginx

# প্রজেক্টের সব ফাইল সার্ভারে কপি করা
COPY --chown=www-data:www-data . /var/www/html

# লারাভেল ডিরেক্টরি পারমিশন ঠিক করা
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

WORKDIR /var/www/html

ENV APP_ENV production
ENV APP_DEBUG false

# কম্পোজার ডিপেন্ডেন্সি ইনস্টল
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs

# Laravel Caching
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# রেন্ডারকে নির্দেশ দেওয়া যাতে সে ৮০৮০ পোর্টেই ট্রাফিক পাঠায়
ENV PORT=8080
EXPOSE 8080
