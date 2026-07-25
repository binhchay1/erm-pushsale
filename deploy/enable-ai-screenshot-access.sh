#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
DOMAIN="${DOMAIN:-salesloop.vn}"
EMAIL="${ERM_AUTO_ADMIN_LOGIN_EMAIL:-admin@saleops.local}"

cd "$APP_DIR"

set_env() {
  local key="$1"
  local value="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s#^${key}=.*#${key}=${value}#" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
}

set_env ERM_AUTO_ADMIN_LOGIN true
set_env ERM_AUTO_ADMIN_LOGIN_EMAIL "$EMAIL"
set_env ERM_AUTO_ADMIN_LOGIN_HOSTS "$DOMAIN"

php artisan optimize:clear

echo "AI screenshot access enabled for ${DOMAIN} as ${EMAIL}. Disable it after capture: bash deploy/disable-ai-screenshot-access.sh"
