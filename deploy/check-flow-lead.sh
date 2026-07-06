#!/usr/bin/env bash
set -euo pipefail
PHONE="${1:-}"
cd /var/www/erm-pushsale
DB_HOST=$(grep DB_HOST .env | cut -d= -f2)
DB_USER=$(grep DB_USERNAME .env | cut -d= -f2)
DB_PASS=$(grep DB_PASSWORD .env | cut -d= -f2)
DB_NAME=$(grep DB_DATABASE .env | cut -d= -f2)
DB_PORT=$(grep DB_PORT .env | cut -d= -f2)
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
SELECT id,status,customer_phone,marketing_source_id,order_id,created_at
FROM lead_ingestions WHERE customer_phone='${PHONE}' ORDER BY id DESC LIMIT 3;
SELECT id,order_code,customer_phone,delivery_status,operation_stage,closed_at
FROM orders WHERE customer_phone='${PHONE}' ORDER BY id DESC LIMIT 3;
SELECT COUNT(*) AS pending_jobs FROM jobs;
"
