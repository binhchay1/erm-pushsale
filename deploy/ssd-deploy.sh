#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"

cd "$APP_DIR"

echo "==> Check env"
test -f .env

echo "==> Composer install"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "==> NPM build"
if [ -f package-lock.json ]; then
  npm ci
else
  npm install
fi
npm run build

echo "==> Prepare storage"
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
setfacl -R -m u:www-data:rwx -m u:deploy:rwx storage bootstrap/cache || true
setfacl -dR -m u:www-data:rwx -m u:deploy:rwx storage bootstrap/cache || true

echo "==> Laravel optimize"
php artisan optimize:clear

APP_KEY_VALUE="$(php artisan tinker --execute='echo config("app.key");' 2>/dev/null || true)"
if [ -z "$APP_KEY_VALUE" ]; then
  php artisan key:generate --force
fi

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Restart services"
php artisan horizon:terminate || true
sudo supervisorctl restart pushsale-horizon || true
sudo supervisorctl restart pushsale-reverb || true
sudo systemctl reload php8.5-fpm || sudo systemctl reload php8.4-fpm || true
sudo systemctl reload nginx || true

echo "==> Deploy done"
