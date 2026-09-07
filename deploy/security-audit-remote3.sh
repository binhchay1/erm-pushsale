#!/usr/bin/env bash
set +e
echo "=== UFW RULES ==="
cat /etc/ufw/user.rules 2>/dev/null | head -80
echo "=== SUPERVISOR ==="
ls -la /etc/supervisor/conf.d/ 2>/dev/null
for f in /etc/supervisor/conf.d/*; do echo "---- $f ----"; cat "$f"; done
echo "=== HOME DEPLOY ==="
ls -la /home/deploy/
echo "=== WHO LISTENS 8080 ==="
ss -tlnp | grep 8080 || true
echo "=== MYSQL FROM APP (version) ==="
cd /var/www/erm-pushsale
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
  $v = DB::select("select version() v")[0]->v ?? "?";
  echo "mysql_ok version=$v\n";
  $sz = DB::select("SELECT ROUND(SUM(data_length+index_length)/1024/1024,1) mb FROM information_schema.tables WHERE table_schema=DATABASE()")[0]->mb ?? "?";
  echo "db_size_mb=$sz\n";
} catch (Throwable $e) { echo "mysql_err=".$e->getMessage()."\n"; }
'
echo "=== SCHEDULE LIST ==="
php artisan schedule:list 2>&1 | head -40
echo DONE
