#!/usr/bin/env bash
# E2E test luồng landing mới: đơn ngay + chờ upsale + gộp upsell + khóa khi sale gọi.
set -euo pipefail
cd /var/www/erm-pushsale

PHONE="${1:-0911$(date +%H%M%S)}"
echo "=== TEST LUONG LANDING MOI — phone=$PHONE ==="

php artisan demo:ui-flow --phone="$PHONE" --skip-ship 2>&1 | tee /tmp/landing-flow-test.log || true

echo ""
echo "=== Kiem tra hold / upsell tren DB ==="
DB_HOST=$(grep DB_HOST .env | cut -d= -f2)
DB_USER=$(grep DB_USERNAME .env | cut -d= -f2)
DB_PASS=$(grep DB_PASSWORD .env | cut -d= -f2)
DB_NAME=$(grep DB_DATABASE .env | cut -d= -f2)
DB_PORT=$(grep DB_PORT .env | cut -d= -f2)

mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "
SELECT CONCAT('order=', o.order_code,
  ' delivery=', o.delivery_status,
  ' hold_until=', IFNULL(o.landing_upsell_hold_until,'null'),
  ' locked=', o.landing_upsell_locked,
  ' items=', (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id=o.id),
  ' total=', o.total)
FROM orders o WHERE o.customer_phone='${PHONE}' ORDER BY o.id DESC LIMIT 1;

SELECT CONCAT('lead=', l.id, ' status=', l.status, ' order_id=', IFNULL(l.order_id,'null'))
FROM lead_ingestions l WHERE l.customer_phone='${PHONE}' ORDER BY l.id DESC LIMIT 1;
"

echo ""
echo "=== Audit bao cao ==="
php artisan audit:reports --phone="$PHONE" --skip-flow
