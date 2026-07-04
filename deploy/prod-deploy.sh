#!/usr/bin/env bash
set -euo pipefail

cd /var/www/erm-pushsale

echo "=== GIT PULL ==="
git pull --ff-only origin main

echo "=== COMPOSER ==="
composer install --no-dev --optimize-autoloader --no-interaction

echo "=== FRONTEND BUILD ==="
npm ci --no-audit --no-fund
npm run build

echo "=== MIGRATE ==="
php artisan migrate --force

echo "=== DATA AUDIT (fix dữ liệu cũ không khớp business logic) ==="
php artisan data:audit-business --fix

echo "=== CACHE ==="
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== RESTART PHP-FPM ==="
sudo systemctl restart php8.5-fpm

echo "=== QUEUE WORKERS (drain pending) ==="
for i in 1 2 3 4 5; do
  sudo -u www-data php artisan queue:work database --once --quiet || true
done

echo "=== DEPLOY DONE ==="
