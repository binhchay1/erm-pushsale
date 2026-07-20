#!/usr/bin/env bash
set -euo pipefail

cd /var/www/erm-pushsale

echo "=== GIT PULL ==="
git pull --ff-only origin main

echo "=== COMPOSER ==="
composer install --no-dev --optimize-autoloader --no-interaction

echo "=== FRONTEND BUILD (PNPM) ==="
export HOME="${HOME:-/home/deploy}"
export PNPM_HOME="${PNPM_HOME:-$HOME/.local/share/pnpm}"
export COREPACK_HOME="${COREPACK_HOME:-$HOME/.cache/node/corepack}"
export PATH="$PNPM_HOME:$PATH"

if ! command -v pnpm >/dev/null 2>&1; then
  echo "ERROR: pnpm is required. Install/activate pnpm@9.15.9 for the deploy user before running this script." >&2
  exit 1
fi

pnpm install --frozen-lockfile
pnpm run build

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

echo "=== RELOAD HORIZON ==="
# Supervisor restarts Horizon with the new code/config after graceful termination.
sudo -u www-data php artisan horizon:terminate || true
sudo supervisorctl status erm-saleops-horizon || true

echo "=== DEPLOY DONE ==="
