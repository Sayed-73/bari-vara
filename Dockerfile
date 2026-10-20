FROM shinsenter/laravel:php8.3

# প্রজেক্টের সব ফাইল কনটেইনারে কপি করা
COPY . /var/www/html

WORKDIR /var/www/html

ENV APP_ENV production
ENV APP_DEBUG false

# কম্পোজার ডিপেন্ডেন্সি ইনস্টল (কোনো এক্সটেনশন এরর ছাড়া)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --ignore-platform-reqs

# Laravel Caching
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

# এই ইমেজটি রেন্ডারের দেওয়া পোর্ট অটোমেটিক ডিটেক্ট করে রান হতে পারে
