#!/usr/bin/env bash
# Read-only production readiness audit. Redacts secret values.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/erm-pushsale}"
cd "$APP_DIR"

pass=0
warn=0
fail=0

ok()   { pass=$((pass+1)); echo "  [OK]   $*"; }
warn() { warn=$((warn+1)); echo "  [WARN] $*"; }
bad()  { fail=$((fail+1)); echo "  [FAIL] $*"; }

env_get() {
  local key="$1"
  local line
  line=$(grep -E "^${key}=" .env 2>/dev/null | tail -n1 || true)
  if [[ -z "$line" ]]; then
    echo ""
    return
  fi
  echo "${line#*=}"
}

env_set() {
  local key="$1"
  local val
  val=$(env_get "$key")
  [[ -n "$val" ]]
}

echo "========================================"
echo " ERM PUSHSALE PRODUCTION AUDIT"
echo " Host: $(hostname)  Time: $(date -Is)"
echo "========================================"

echo
echo "## 1) APP / ENV CORE"
APP_ENV=$(env_get APP_ENV)
APP_DEBUG=$(env_get APP_DEBUG)
APP_URL=$(env_get APP_URL)
[[ "$APP_ENV" == "production" ]] && ok "APP_ENV=production" || bad "APP_ENV='$APP_ENV' (expect production)"
[[ "${APP_DEBUG,,}" == "false" || "$APP_DEBUG" == "0" ]] && ok "APP_DEBUG=false" || bad "APP_DEBUG='$APP_DEBUG' (must be false on prod)"
[[ "$APP_URL" == https://* ]] && ok "APP_URL uses https ($APP_URL)" || warn "APP_URL='$APP_URL' (prefer https)"
env_set APP_KEY && ok "APP_KEY set" || bad "APP_KEY missing"
env_set APP_NAME && ok "APP_NAME set" || warn "APP_NAME empty"

echo
echo "## 2) SECURITY / QA BYPASS FLAGS (must be OFF on real prod)"
AUTO=$(env_get ERM_AUTO_ADMIN_LOGIN)
STAGING=$(env_get ERM_STAGING_TEST_MODE)
[[ "${AUTO,,}" == "true" || "$AUTO" == "1" ]] && bad "ERM_AUTO_ADMIN_LOGIN=$AUTO (re-auth after logout; disable on prod)" || ok "ERM_AUTO_ADMIN_LOGIN off (${AUTO:-empty})"
[[ "${STAGING,,}" == "true" || "$STAGING" == "1" ]] && bad "ERM_STAGING_TEST_MODE=$STAGING (must be false on prod)" || ok "ERM_STAGING_TEST_MODE off (${STAGING:-empty})"
CSP=$(env_get SECURITY_CSP_ENABLED)
[[ -z "$CSP" || "${CSP,,}" == "true" || "$CSP" == "1" ]] && ok "SECURITY_CSP_ENABLED=${CSP:-default/on}" || warn "SECURITY_CSP_ENABLED=$CSP"
TELESCOPE=$(env_get TELESCOPE_ENABLED)
[[ -z "$TELESCOPE" || "${TELESCOPE,,}" == "false" || "$TELESCOPE" == "0" ]] && ok "TELESCOPE_ENABLED=${TELESCOPE:-off/default}" || warn "TELESCOPE_ENABLED=$TELESCOPE (prefer off on prod)"
PULSE=$(env_get PULSE_ENABLED)
[[ -z "$PULSE" || "${PULSE,,}" == "false" || "$PULSE" == "0" ]] && ok "PULSE_ENABLED=${PULSE:-off/default}" || warn "PULSE_ENABLED=$PULSE"

echo
echo "## 3) SESSION / COOKIE / HTTPS"
SESSION_DRIVER=$(env_get SESSION_DRIVER)
SESSION_SECURE=$(env_get SESSION_SECURE_COOKIE)
SESSION_DOMAIN=$(env_get SESSION_DOMAIN)
SESSION_SAME=$(env_get SESSION_SAME_SITE)
ok "SESSION_DRIVER=${SESSION_DRIVER:-default}"
[[ "${SESSION_SECURE,,}" == "true" || "$SESSION_SECURE" == "1" ]] && ok "SESSION_SECURE_COOKIE=true" || warn "SESSION_SECURE_COOKIE='${SESSION_SECURE:-empty}' (prefer true behind HTTPS)"
ok "SESSION_DOMAIN=${SESSION_DOMAIN:-empty/default} SAME_SITE=${SESSION_SAME:-default}"

echo
echo "## 4) DATABASE / REDIS / QUEUE / CACHE / MAIL"
DB_CONN=$(env_get DB_CONNECTION)
DB_HOST=$(env_get DB_HOST)
DB_NAME=$(env_get DB_DATABASE)
QUEUE=$(env_get QUEUE_CONNECTION)
CACHE=$(env_get CACHE_STORE)
CACHE_ALT=$(env_get CACHE_DRIVER)
REDIS_HOST=$(env_get REDIS_HOST)
MAIL=$(env_get MAIL_MAILER)
BROADCAST=$(env_get BROADCAST_CONNECTION)
ok "DB_CONNECTION=${DB_CONN:-?} host=${DB_HOST:-?} db=${DB_NAME:-?}"
[[ "$QUEUE" == "redis" || "$QUEUE" == "horizon" ]] && ok "QUEUE_CONNECTION=$QUEUE" || warn "QUEUE_CONNECTION=${QUEUE:-empty} (prod expects redis)"
ok "CACHE=${CACHE:-${CACHE_ALT:-default}} REDIS_HOST=${REDIS_HOST:-default}"
[[ "$MAIL" == "log" || -z "$MAIL" ]] && warn "MAIL_MAILER=${MAIL:-log} (mail may not send in prod)" || ok "MAIL_MAILER=$MAIL"
ok "BROADCAST_CONNECTION=${BROADCAST:-default}"

echo
echo "## 5) LARAVEL RUNTIME (artisan about)"
if sudo -u www-data php artisan about --only=environment,cache,drivers 2>/tmp/about.err; then
  sudo -u www-data php artisan about --only=environment,cache,drivers 2>/dev/null | sed 's/^/  | /' | head -40
  ok "artisan about OK"
else
  bad "artisan about failed: $(head -c 200 /tmp/about.err)"
fi

echo
echo "## 6) CACHES / OPTIMIZE ARTIFACTS"
for f in bootstrap/cache/config.php bootstrap/cache/routes-v7.php bootstrap/cache/routes.php bootstrap/cache/events.php; do
  if [[ -f "$f" ]]; then ok "present $f"; else warn "missing $f"; fi
done
[[ -d public/build/assets && -f public/build/manifest.json ]] && ok "frontend build present (manifest + assets)" || bad "public/build missing/incomplete"
ASSET_COUNT=$(find public/build/assets -type f 2>/dev/null | wc -l | tr -d ' ')
ok "build asset files=$ASSET_COUNT"

echo
echo "## 7) PERMISSIONS"
OWNER=$(stat -c '%U:%G' . 2>/dev/null || echo unknown)
ok "app owner=$OWNER"
for d in storage bootstrap/cache public/build; do
  if [[ -w "$d" ]]; then ok "$d writable by $(whoami)"; else warn "$d not writable by $(whoami)"; fi
done
if sudo -u www-data test -w storage/logs; then ok "www-data can write storage/logs"; else bad "www-data cannot write storage/logs"; fi

echo
echo "## 8) SERVICES: PHP-FPM / NGINX / SUPERVISOR / HORIZON / CRON"
systemctl is-active php8.5-fpm >/dev/null 2>&1 && ok "php8.5-fpm active" || systemctl is-active php8.4-fpm >/dev/null 2>&1 && ok "php8.4-fpm active" || systemctl is-active php-fpm >/dev/null 2>&1 && ok "php-fpm active" || warn "php-fpm status unknown"
systemctl is-active nginx >/dev/null 2>&1 && ok "nginx active" || warn "nginx not active/unknown"
if command -v supervisorctl >/dev/null 2>&1; then
  sudo supervisorctl status 2>/dev/null | sed 's/^/  | /' || true
  if sudo supervisorctl status 2>/dev/null | grep -qi horizon; then
    if sudo supervisorctl status 2>/dev/null | grep -i horizon | grep -qi RUNNING; then
      ok "Horizon supervisor RUNNING"
    else
      bad "Horizon supervisor not RUNNING"
    fi
  else
    warn "No horizon program in supervisor"
  fi
else
  warn "supervisorctl missing"
fi

if sudo -u www-data php artisan horizon:status >/tmp/horizon.status 2>&1; then
  cat /tmp/horizon.status | sed 's/^/  | /'
  grep -qi 'running' /tmp/horizon.status && ok "horizon:status running" || warn "horizon:status unexpected"
else
  bad "horizon:status failed: $(head -c 180 /tmp/horizon.status)"
fi

echo
echo "## 9) SCHEDULER (cron)"
CRON_OK=0
sudo crontab -u www-data -l 2>/dev/null | grep -q 'artisan schedule:run' && CRON_OK=1 || true
crontab -l 2>/dev/null | grep -q 'artisan schedule:run' && CRON_OK=1 || true
sudo grep -R "artisan schedule:run" /etc/cron.d /etc/crontab 2>/dev/null | grep -q . && CRON_OK=1 || true
[[ $CRON_OK -eq 1 ]] && ok "schedule:run found in cron" || bad "schedule:run NOT found (reports/archive/jobs will stall)"

echo
echo "## 10) QUEUE DEPTH / FAILED JOBS"
sudo -u www-data php artisan tinker --execute='
$failed = Schema::hasTable("failed_jobs") ? DB::table("failed_jobs")->count() : -1;
$jobs = Schema::hasTable("jobs") ? DB::table("jobs")->count() : -1;
echo "db_jobs={$jobs}\nfailed_jobs={$failed}\n";
' 2>/tmp/tinker.err | sed 's/^/  | /' || warn "tinker queue counts failed: $(head -c 120 /tmp/tinker.err)"

echo
echo "## 11) HTTPS ENDPOINTS (salesloop.vn)"
DOMAIN=$(echo "$APP_URL" | sed -E 's#https?://##; s#/.*##')
[[ -z "$DOMAIN" ]] && DOMAIN=salesloop.vn
for path in /login /admin/dashboard /admin/reports/ceo-dashboard-v2; do
  code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "https://${DOMAIN}${path}" || echo err)
  if [[ "$path" == "/login" ]]; then
    [[ "$code" == "200" ]] && ok "$path -> $code" || bad "$path -> $code"
  else
    [[ "$code" == "302" || "$code" == "401" || "$code" == "403" || "$code" == "200" ]] && ok "$path -> $code (auth gate)" || warn "$path -> $code"
  fi
done
# Logout must be POST-only
code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 -X POST "https://${DOMAIN}/logout" || echo err)
[[ "$code" == "302" || "$code" == "419" || "$code" == "401" || "$code" == "403" ]] && ok "POST /logout -> $code" || warn "POST /logout -> $code"

echo
echo "## 12) WEBHOOK / API SMOKE"
code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 -X POST "https://${DOMAIN}/api/v1/webhooks/ladipage" -H 'Content-Type: application/json' -d '{}' || echo err)
[[ "$code" == "401" || "$code" == "422" || "$code" == "403" ]] && ok "ladipage webhook no key -> $code" || warn "ladipage webhook -> $code"

echo
echo "## 13) LOG RECENCY / DISK"
if [[ -f storage/logs/laravel.log ]]; then
  ok "laravel.log exists ($(du -h storage/logs/laravel.log | awk '{print $1}'))"
  echo "  | last errors (if any):"
  grep -E 'ERROR|CRITICAL|Exception' storage/logs/laravel.log 2>/dev/null | tail -n 5 | sed 's/^/  | /' || echo "  | (none in tail)"
else
  warn "storage/logs/laravel.log missing"
fi
df -h / /var/www 2>/dev/null | sed 's/^/  | /' || true

echo
echo "## 14) DANGEROUS ENV KEYS PRESENT?"
for key in ERM_STAGING_TEST_SECRET APP_DEBUG DEBUGBAR_ENABLED IGNITION_EDITOR CLOCKWORK_ENABLE; do
  val=$(env_get "$key")
  if [[ -n "$val" ]]; then
    echo "  | $key=${val:0:24}$( [[ ${#val} -gt 24 ]] && echo '…' )"
  fi
done

echo
echo "========================================"
echo " SUMMARY: OK=$pass  WARN=$warn  FAIL=$fail"
echo "========================================"
if [[ $fail -gt 0 ]]; then
  exit 2
fi
exit 0
