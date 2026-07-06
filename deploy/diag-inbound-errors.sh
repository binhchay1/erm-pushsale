#!/usr/bin/env bash
set -euo pipefail
cd /var/www/erm-pushsale
DB_HOST=$(grep '^DB_HOST=' .env | cut -d= -f2-)
DB_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2-)
DB_PASS=$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)
DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d= -f2-)
DB_PORT=$(grep '^DB_PORT=' .env | cut -d= -f2-)

echo "=== INBOUND EVENTS HÔM NAY (theo status) ==="
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT status, COUNT(*) AS cnt
FROM inbound_events
WHERE created_at >= CURDATE()
GROUP BY status
ORDER BY cnt DESC;
"

echo ""
echo "=== TOP 15 LỖI (failed/rejected) HÔM NAY ==="
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT LEFT(COALESCE(error_message,'(null)'), 120) AS err, status, COUNT(*) AS cnt
FROM inbound_events
WHERE created_at >= CURDATE()
  AND status IN ('failed','rejected')
GROUP BY err, status
ORDER BY cnt DESC
LIMIT 15;
"

echo ""
echo "=== LEAD INGESTIONS HÔM NAY ==="
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT status, COUNT(*) AS cnt
FROM lead_ingestions
WHERE created_at >= CURDATE()
GROUP BY status;
"

echo ""
echo "=== PENDING JOBS ==="
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SELECT COUNT(*) FROM jobs;"
