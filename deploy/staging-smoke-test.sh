#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
DOMAIN="${DOMAIN:-erm-pushsale.duckdns.org}"
BASE_URL="${BASE_URL:-http://${DOMAIN}}"
SECRET="${ERM_STAGING_TEST_SECRET:-}"

cd "$APP_DIR"

if [[ -z "${SECRET}" ]]; then
  SECRET="$(grep '^ERM_STAGING_TEST_SECRET=' .env | tail -1 | cut -d= -f2- || true)"
fi

if [[ -z "${SECRET}" ]]; then
  echo "Missing ERM_STAGING_TEST_SECRET" >&2
  exit 1
fi

php artisan optimize:clear
php artisan config:cache
php artisan staging:smoke --bootstrap --reset --landing-flow --flow --pages --campaigns=2 --per-campaign=8

echo ""
echo "Public endpoints for remote QA:"
echo "${BASE_URL}/__erm-test/health?secret=${SECRET}"
echo "${BASE_URL}/__erm-test/pages?secret=${SECRET}"
echo "${BASE_URL}/__erm-test/flow?secret=${SECRET}"
