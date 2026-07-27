#!/usr/bin/env bash
# Harden salesloop.vn toward real production settings.
# Safe to re-run. Does NOT touch DB credentials / APP_KEY / secrets except staging flags.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
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

echo "=== HARDEN PROD ENV ==="
set_env APP_ENV production
set_env APP_DEBUG false
set_env LOG_LEVEL warning
set_env SESSION_SECURE_COOKIE true
set_env ERM_AUTO_ADMIN_LOGIN false
set_env ERM_STAGING_TEST_MODE false
set_env ERM_STAGING_TEST_ALLOW_ARTISAN false
set_env REPORTING_ARCHIVE_DRIVER yearly_tables
set_env REPORTING_ARCHIVE_ALLOW_PURGE false
set_env REPORTING_ARCHIVE_COPY_CHUNK_SIZE 5000
set_env REPORTING_HOT_RETENTION_YEARS 2

echo "=== CLEAR + CACHE ==="
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "=== RESTART WORKERS / PHP-FPM ==="
sudo -n /usr/bin/supervisorctl restart pushsale-horizon || true
sudo -n /usr/bin/supervisorctl restart pushsale-reverb || true
sudo -n /usr/bin/systemctl reload php8.5-fpm || true

echo "=== VERIFY KEY FLAGS ==="
grep -E '^(APP_ENV|APP_DEBUG|LOG_LEVEL|SESSION_SECURE_COOKIE|ERM_AUTO_ADMIN_LOGIN|ERM_STAGING_TEST_MODE|ERM_STAGING_TEST_ALLOW_ARTISAN)=' .env
php artisan about --only=environment | sed 's/^/  | /'

echo "=== DONE ==="
echo "NOTE: Laravel schedule:run cron is MISSING on this host."
echo "Root must add: * * * * * www-data cd /var/www/erm-pushsale && php artisan schedule:run >> /dev/null 2>&1"
