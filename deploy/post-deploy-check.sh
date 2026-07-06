#!/usr/bin/env bash
set -euo pipefail
cd /var/www/erm-pushsale

echo "=== COMMIT ==="
git log -1 --oneline

echo "=== SUPERVISOR QUEUE ==="
grep command /etc/supervisor/conf.d/pushsale-queue.conf || true
sudo supervisorctl status | head -5

echo "=== ROUTES marketing/leads ==="
php artisan route:list --path=marketing/leads | head -8

echo "=== HTTPS (expect 401 unauthenticated) ==="
for path in /marketing/leads /marketing/workspace /marketing/campaigns /admin/marketing/dashboard; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "https://erm-pushsale.duckdns.org${path}")
  echo "${path} -> ${code}"
done

echo "=== FRONTEND BUNDLE ==="
DASH=public/build/assets/Dashboard-acngp2xb.js
echo "dashboard_js=${DASH}"
if grep -q marketer_table "$DASH"; then
  echo "marketer_table_bundle=OK"
else
  echo "marketer_table_bundle=MISSING"
fi

DB_HOST=$(grep DB_HOST .env | cut -d= -f2)
DB_USER=$(grep DB_USERNAME .env | cut -d= -f2)
DB_PASS=$(grep DB_PASSWORD .env | cut -d= -f2)
DB_NAME=$(grep DB_DATABASE .env | cut -d= -f2)
DB_PORT=$(grep DB_PORT .env | cut -d= -f2)

echo "=== CAMPAIGNS ==="
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT id, name, is_approved, IF(rejected_at IS NULL, 0, 1) AS rejected, contacts, is_active
FROM marketing_sources WHERE parent_id IS NULL ORDER BY id DESC LIMIT 10;
"

echo "=== COUNTS ==="
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "
SELECT CONCAT('lead_ingestions=', COUNT(*)) FROM lead_ingestions;
SELECT CONCAT('pending_jobs=', COUNT(*)) FROM jobs;
SELECT CONCAT('failed_jobs=', COUNT(*)) FROM failed_jobs;
SELECT CONCAT('rejected_campaigns=', COUNT(*)) FROM marketing_sources WHERE rejected_at IS NOT NULL;
"

echo "=== WEBHOOK E2E ==="
TOKEN=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT webhook_token FROM marketing_sources WHERE parent_id IS NULL AND is_active=1 AND webhook_token IS NOT NULL ORDER BY id DESC LIMIT 1;")
PHONE="0909$(date +%H%M%S)"
echo "token_len=${#TOKEN} phone=${PHONE}"
if [ -n "$TOKEN" ]; then
  BEFORE=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM lead_ingestions;")
  HTTP=$(curl -s -o /tmp/webhook_resp.json -w '%{http_code}' -X POST "https://erm-pushsale.duckdns.org/api/v1/landing/${TOKEN}/receive" \
    -H 'Content-Type: application/json' \
    -d "{\"name\":\"Deploy Test\",\"phone\":\"${PHONE}\",\"products\":\"Test SP\"}")
  echo "webhook_http=${HTTP}"
  cat /tmp/webhook_resp.json
  echo
  sleep 5
  AFTER=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM lead_ingestions;")
  echo "leads_before=${BEFORE} leads_after=${AFTER}"
  mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT id,status,customer_phone,marketing_source_id,created_at FROM lead_ingestions ORDER BY id DESC LIMIT 3;"
fi

echo "=== DONE ==="
