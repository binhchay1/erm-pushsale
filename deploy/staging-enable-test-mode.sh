#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
DOMAIN="${DOMAIN:-erm-pushsale.duckdns.org}"
BASE_URL="${BASE_URL:-http://${DOMAIN}}"
SECRET="${ERM_STAGING_TEST_SECRET:-}"

if [[ -z "${SECRET}" ]]; then
  SECRET="$(php -r 'echo bin2hex(random_bytes(24));')"
fi

cd "$APP_DIR"

touch .env
set_env() {
  local key="$1" value="$2"
  if grep -q "^${key}=" .env; then
    sed -i "s#^${key}=.*#${key}=${value}#" .env
  else
    printf '\n%s=%s\n' "$key" "$value" >> .env
  fi
}

set_env APP_URL "$BASE_URL"
set_env ERM_AUTO_ADMIN_LOGIN true
set_env ERM_AUTO_ADMIN_LOGIN_HOSTS "$DOMAIN"
set_env ERM_STAGING_TEST_MODE true
set_env ERM_STAGING_TEST_HOSTS "$DOMAIN"
set_env ERM_STAGING_TEST_BASE_URL "$BASE_URL"
set_env ERM_STAGING_TEST_SECRET "$SECRET"
set_env ERM_STAGING_TEST_ALLOW_ARTISAN true
set_env ERM_STAGING_TEST_HTTP_TIMEOUT 25
set_env QUEUE_CONNECTION "${QUEUE_CONNECTION:-sync}"
set_env SESSION_DRIVER "${SESSION_DRIVER:-file}"

php artisan optimize:clear
php artisan config:cache
php artisan route:clear || true
php artisan migrate --force
php artisan db:seed --force
php artisan horizon:terminate || true

echo ""
echo "Staging test mode enabled."
echo "Secret: ${SECRET}"
echo "Health: ${BASE_URL}/__erm-test/health?secret=${SECRET}"
echo "Page scan: ${BASE_URL}/__erm-test/pages?secret=${SECRET}"
echo "Bootstrap demo: ${BASE_URL}/__erm-test/bootstrap?secret=${SECRET}&reset=1&campaigns=2&per_campaign=8"
echo "Full flow: ${BASE_URL}/__erm-test/flow?secret=${SECRET}"
echo "Landing connection flow: ${BASE_URL}/__erm-test/landing-flow?secret=${SECRET}"
