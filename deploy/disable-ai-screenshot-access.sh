#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
cd "$APP_DIR"

if grep -q '^ERM_AUTO_ADMIN_LOGIN=' .env; then
  sed -i 's#^ERM_AUTO_ADMIN_LOGIN=.*#ERM_AUTO_ADMIN_LOGIN=false#' .env
else
  printf '\nERM_AUTO_ADMIN_LOGIN=false\n' >> .env
fi

php artisan optimize:clear

echo "AI screenshot access disabled."
