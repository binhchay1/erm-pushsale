#!/usr/bin/env bash
set -euo pipefail

cd /var/www/erm-pushsale

echo "=== RESTART WORKERS ==="
sudo supervisorctl restart pushsale-queue:* || true
sleep 2
sudo supervisorctl status

echo "=== MIGRATE STATUS (latest) ==="
php artisan migrate:status | tail -5

echo "=== QUEUE FAILED ==="
php artisan queue:failed || true

echo "=== PENDING JOBS ==="
php artisan tinker --execute="echo 'pending=' . \Illuminate\Support\Facades\DB::table('jobs')->count() . PHP_EOL;"

echo "=== SCHEMA CHECK ==="
php artisan tinker --execute="echo (\Illuminate\Support\Facades\Schema::hasTable('activity_logs') ? 'activity_logs OK' : 'activity_logs MISSING') . PHP_EOL; echo (\Illuminate\Support\Facades\Schema::hasColumn('marketing_sources','approved_by_user_id') ? 'approval cols OK' : 'approval cols MISSING') . PHP_EOL;"

echo "=== HTTP SMOKE (nginx local) ==="
for path in / /login /admin/dashboard /admin/activity-logs /admin/landing-approvals /marketing/campaigns; do
  code=$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: erm-pushsale.duckdns.org' "http://127.0.0.1${path}")
  echo "${path} -> ${code}"
done

echo "=== PUBLIC HTTPS SMOKE ==="
for path in /login /admin/dashboard; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "https://erm-pushsale.duckdns.org${path}")
  echo "https${path} -> ${code}"
done

echo "=== INSTALL DEV DEPS FOR TEST ==="
composer install --optimize-autoloader --no-interaction

echo "=== PHPUNIT ==="
php artisan test
