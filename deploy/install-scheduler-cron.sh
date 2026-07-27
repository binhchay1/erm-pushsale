#!/usr/bin/env bash
# Install Laravel scheduler. Run as root:
#   sudo bash deploy/install-scheduler-cron.sh
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
CRON_FILE=/etc/cron.d/erm-pushsale-scheduler

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo bash $0" >&2
  exit 1
fi

# Ensure cron package exists
if ! command -v cron >/dev/null 2>&1 && ! systemctl list-unit-files | grep -q cron.service; then
  apt-get update -y
  apt-get install -y cron
  systemctl enable --now cron
fi

cat > "$CRON_FILE" <<EOF
# ERM Pushsale scheduler — reports/archive/notifications
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
* * * * * www-data cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
EOF

chmod 644 "$CRON_FILE"
systemctl reload cron 2>/dev/null || systemctl restart cron 2>/dev/null || true

echo "Installed ${CRON_FILE}"
cat "$CRON_FILE"
