#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
DOMAIN="${DOMAIN:-erm-pushsale.duckdns.org}"
BASE_URL="${BASE_URL:-http://${DOMAIN}}"
SECRET="${ERM_STAGING_TEST_SECRET:-}"
SEED_MODE="${STAGING_SEED_MODE:-full}"

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

# Seed demo data creates inventory/order events. During seed we turn reporting dirty-date
# tracking off so demo bootstrap cannot fail because a reporting table is mid-upgrade.
set_env REPORTING_FACTS_ENABLED false
set_env REPORTING_ARCHIVE_ENABLED false

php artisan optimize:clear
php artisan config:cache
php artisan route:clear || true
php artisan migrate --force

case "$SEED_MODE" in
  accounts|auth)
    php artisan db:seed --class=Database\\Seeders\\StagingAuthSeeder --force
    ;;
  none|skip)
    echo "Skipping database seed because STAGING_SEED_MODE=${SEED_MODE}."
    ;;
  full)
    php artisan db:seed --force
    ;;
  *)
    echo "Unknown STAGING_SEED_MODE=${SEED_MODE}. Use full, accounts, or none." >&2
    exit 1
    ;;
esac

# Re-enable reporting after seed. Test endpoints and schedulers can then aggregate facts normally.
set_env REPORTING_FACTS_ENABLED true
set_env REPORTING_ARCHIVE_ENABLED true

php artisan optimize:clear
php artisan config:cache
php artisan horizon:terminate || true

echo ""
echo "Staging test mode enabled."
echo "Seed mode: ${SEED_MODE}"
echo "Secret: ${SECRET}"
echo "Health: ${BASE_URL}/__erm-test/health?secret=${SECRET}"
echo "Page scan: ${BASE_URL}/__erm-test/pages?secret=${SECRET}"
echo "Bootstrap demo: ${BASE_URL}/__erm-test/bootstrap?secret=${SECRET}&reset=1&campaigns=2&per_campaign=8"
echo "Full flow: ${BASE_URL}/__erm-test/flow?secret=${SECRET}"
echo "Landing connection flow: ${BASE_URL}/__erm-test/landing-flow?secret=${SECRET}"
