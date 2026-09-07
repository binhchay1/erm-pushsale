#!/usr/bin/env bash
set +e
echo "=== NGINX SITES ==="
ls -la /etc/nginx/sites-enabled/ /etc/nginx/conf.d/ 2>/dev/null
for f in /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*; do
  [ -e "$f" ] || continue
  echo "---- $f ----"
  cat "$f" 2>/dev/null
done
echo
echo "=== LISTEN (no sudo) ==="
ss -tln
echo
echo "=== REDIS ==="
redis-cli INFO server 2>/dev/null | head -20
redis-cli CONFIG GET bind 2>/dev/null
redis-cli CONFIG GET protected-mode 2>/dev/null
redis-cli CONFIG GET requirepass 2>/dev/null | sed 's/./*/g'
echo
echo "=== CRON ==="
cat /etc/cron.d/php 2>/dev/null
ls -la /etc/cron.d/
grep -R "schedule:run\|erm-pushsale\|backup" /etc/cron* 2>/dev/null | head -30
echo
echo "=== UFW CONF (readable?) ==="
cat /etc/ufw/ufw.conf 2>/dev/null
ls /etc/ufw/ 2>/dev/null
echo
echo "=== FAIL2BAN PKG ==="
dpkg -l fail2ban 2>/dev/null | tail -2
ls /etc/fail2ban 2>/dev/null
echo
echo "=== CERTBOT ==="
sudo -n certbot certificates 2>&1 | head -40
ls /etc/letsencrypt/live/ 2>/dev/null
echo
echo "=== APP DB VIA ARTISAN ==="
cd /var/www/erm-pushsale || exit 1
php artisan db:show 2>&1 | head -50
echo
echo "=== STORAGE SIZE ==="
du -sh storage bootstrap/cache public/build 2>/dev/null
du -sh storage/app 2>/dev/null
find storage/app -maxdepth 2 -type d 2>/dev/null
echo
echo "=== MYSQL CLIENT TO REMOTE ==="
# connectivity only — no password printed
DB_HOST=$(grep '^DB_HOST=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_PORT=$(grep '^DB_PORT=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
DB_PORT=${DB_PORT:-3306}
echo "DB_HOST=$DB_HOST DB_PORT=$DB_PORT"
timeout 3 bash -c "echo >/dev/tcp/$DB_HOST/$DB_PORT" && echo "TCP $DB_HOST:$DB_PORT OPEN" || echo "TCP $DB_HOST:$DB_PORT CLOSED/TIMEOUT"
echo
echo "=== PUBLIC IP / CF ==="
curl -4 -s ifconfig.me; echo
curl -sI https://salesloop.vn | grep -iE 'cf-|cloudflare|server:|x-cache'
echo DONE
