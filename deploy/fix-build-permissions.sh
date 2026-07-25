#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
WEB_GROUP="${WEB_GROUP:-www-data}"
WEB_USER="${WEB_USER:-www-data}"

cd "$APP_DIR"

run_privileged() {
  if [ "$(id -u)" -eq 0 ]; then
    "$@"
    return 0
  fi

  if command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null; then
    sudo "$@"
    return 0
  fi

  return 1
}

ensure_dir() {
  mkdir -p "$1"
}

echo "==> Repair build/cache ownership contract"
ensure_dir public/build
ensure_dir storage/logs
ensure_dir storage/framework/cache
ensure_dir storage/framework/sessions
ensure_dir storage/framework/views
ensure_dir bootstrap/cache

# Vite empties public/build before every build. If one old chunk is owned by root/www-data,
# deploy user cannot unlink it and deployment stops with EACCES.
if ! [ -w public/build ] || find public/build -mindepth 1 -maxdepth 3 ! -writable -print -quit | grep -q .; then
  echo "public/build has non-writable files for $(id -un); repairing ownership..."
  if ! run_privileged chown -R "${DEPLOY_USER}:${WEB_GROUP}" public/build; then
    cat >&2 <<MSG
ERROR: public/build is not writable by $(id -un), and passwordless sudo is not available.
Run once as root:
  cd ${APP_DIR}
  chown -R ${DEPLOY_USER}:${WEB_GROUP} public/build
  chmod -R ug+rwX public/build
Then deploy again.
MSG
    exit 1
  fi
fi

chmod -R ug+rwX public/build storage bootstrap/cache || true

# Keep runtime-writable dirs safe for PHP-FPM and deploy user.
if command -v setfacl >/dev/null 2>&1; then
  setfacl -R -m "u:${DEPLOY_USER}:rwx" -m "u:${WEB_USER}:rwx" public/build storage bootstrap/cache || true
  setfacl -dR -m "u:${DEPLOY_USER}:rwx" -m "u:${WEB_USER}:rwx" public/build storage bootstrap/cache || true
fi

echo "==> Build permission contract OK"
