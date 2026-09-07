#!/usr/bin/env bash
# Install deploy-user crontab when root cron.d is not yet available.
# Usage (as deploy): bash deploy/ops-install-deploy-cron.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
BACKUP_ROOT="${OPS_BACKUP_PATH:-/home/deploy/backups/erm-pushsale}"
LOG_DIR="${APP_DIR}/storage/logs"

mkdir -p "$BACKUP_ROOT" "$LOG_DIR"

# Merge without duplicating markers
TMP=$(mktemp)
crontab -l 2>/dev/null | grep -v 'erm-pushsale-scheduler' | grep -v 'erm-pushsale-backup-fallback' >"$TMP" || true

cat >>"$TMP" <<EOF
# erm-pushsale-scheduler
* * * * * cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> ${LOG_DIR}/scheduler.log 2>&1
EOF

crontab "$TMP"
rm -f "$TMP"

echo "Installed deploy crontab:"
crontab -l
echo
echo "Run first backup now:"
echo "  cd ${APP_DIR} && php artisan ops:backup --path=${BACKUP_ROOT}"
