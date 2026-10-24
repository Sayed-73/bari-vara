#!/bin/sh
set -e

# 1) Listen on the port the platform gives us (Render: PORT=10000)
PORT="${PORT:-10000}"
sed -ri "s/Listen [0-9]+/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html

# 2) Fresh environment, no stale caches from an earlier container
php artisan package:discover --ansi || true
php artisan config:clear || true

# 3) Database: wait for it (free databases/containers wake slowly), then migrate.
#    If it still fails after ~1 minute we stop on purpose: never run on a half-migrated schema.
ok=0
i=1
while [ "$i" -le 15 ]; do
  if php artisan migrate --force; then
    ok=1
    break
  fi
  echo "Database not ready or migration failed (try $i/15), retrying in 4s..."
  i=$((i + 1))
  sleep 4
done

if [ "$ok" -ne 1 ]; then
  echo "Migration failed, not starting the app."
  exit 1
fi

# 4) Storage symlink, Filament assets + production caches (each is optional: a failure here must not stop the site)
php artisan storage:link --force || true
php artisan filament:assets || true
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan event:cache || true

# artisan ran as root: give the web server (www-data) back its writable folders, or logging/sessions fail
chown -R www-data:www-data storage bootstrap/cache || true

# 5) Start the web server (foreground = the container's main process)
exec apache2-foreground
