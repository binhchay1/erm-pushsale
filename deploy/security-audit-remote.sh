#!/usr/bin/env bash
# Read-only production security / backup audit (no secrets dumped).
set +e
echo "========== 1. SYSTEM =========="
hostname; uptime; date -u
df -h / /var /var/www /tmp 2>/dev/null | head -20
free -h | head -3
echo
echo "========== 2. FIREWALL =========="
sudo -n ufw status verbose 2>&1 | head -40
echo "--- iptables INPUT ---"
sudo -n iptables -L INPUT -n -v --line-numbers 2>&1 | head -35
echo
echo "========== 3. FAIL2BAN =========="
systemctl is-active fail2ban 2>&1
sudo -n fail2ban-client status 2>&1 | head -25
echo
echo "========== 4. SSH =========="
sudo -n ss -tlnp 2>/dev/null | grep -E ':22|:2222' || ss -tln | grep -E ':22|:2222'
grep -E '^(PermitRootLogin|PasswordAuthentication|PubkeyAuthentication|Port|MaxAuthTries|AllowUsers|ChallengeResponse|KbdInteractive|X11Forwarding)' /etc/ssh/sshd_config 2>/dev/null
sudo -n grep -rhE '^(PermitRootLogin|PasswordAuthentication|PubkeyAuthentication|Port|MaxAuthTries|AllowUsers)' /etc/ssh/sshd_config.d/ 2>/dev/null
echo
echo "========== 5. LISTENING PORTS =========="
sudo -n ss -tulnp 2>&1 | head -80
echo
echo "========== 6. NGINX / TLS =========="
nginx -v 2>&1
sudo -n nginx -T 2>&1 | grep -E 'server_name|listen |ssl_certificate|limit_req|add_header|client_max_body|server_tokens|ssl_protocols|ssl_ciphers' | head -100
echo
echo "========== 7. BACKUPS EXISTING =========="
ls -la /var/backups 2>/dev/null | head -25
for d in /backup /backups /var/www/backups /home/deploy/backups /opt/backups /var/backups/erm-pushsale; do
  if [ -d "$d" ]; then echo "DIR $d:"; ls -lah "$d" | head -15; fi
done
find /var /home /opt -maxdepth 3 \( -iname '*backup*' -o -iname '*snapshot*' \) 2>/dev/null | head -50
echo '--- deploy crontab ---'
crontab -l 2>/dev/null
echo '--- root crontab ---'
sudo -n crontab -l 2>/dev/null
echo '--- cron.d ---'
ls /etc/cron.d/ 2>/dev/null
ls /etc/cron.daily/ 2>/dev/null
echo
echo "========== 8. DB SERVICES =========="
systemctl is-active mysql mariadb postgresql redis-server redis 2>&1
which mysqldump pg_dump 2>/dev/null
echo
echo "========== 9. APP ENV (safe) =========="
cd /var/www/erm-pushsale || exit 1
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|SESSION_SECURE_COOKIE|LOG_LEVEL|ERM_AUTO_ADMIN_LOGIN|ERM_STAGING_TEST_MODE|ERM_STAGING_TEST_ALLOW_ARTISAN|DB_CONNECTION|DB_HOST|QUEUE_CONNECTION|CACHE_STORE|SESSION_DRIVER)=' .env
echo
echo "========== 10. HTTP HEADERS =========="
curl -sI https://salesloop.vn 2>/dev/null | head -30
echo
echo "========== 11. PACKAGES =========="
systemctl is-active unattended-upgrades 2>&1
dpkg -l 2>/dev/null | grep -E 'fail2ban|ufw|unattended|certbot|nginx|crowdsec|clamav' | awk '{print $2,$3}'
echo
echo "========== 12. STORAGE / TOOLS =========="
lsblk -o NAME,SIZE,TYPE,MOUNTPOINT 2>/dev/null | head -25
which restic borg rclone aws snap 2>/dev/null
id; groups
sudo -n -l 2>&1 | head -40
echo DONE
