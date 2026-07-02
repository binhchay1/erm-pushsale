#!/usr/bin/env bash
set -euo pipefail

cd /var/www/erm-pushsale

echo "=== PROCESS PENDING QUEUE JOBS ==="
before=$(php artisan tinker --execute="echo \Illuminate\Support\Facades\DB::table('jobs')->count();")
echo "pending_before=${before}"
for i in 1 2 3 4 5 6 7 8; do
  sudo -u www-data php artisan queue:work database --once --quiet || true
done
after=$(php artisan tinker --execute="echo \Illuminate\Support\Facades\DB::table('jobs')->count();")
echo "pending_after=${after}"
failed=$(php artisan tinker --execute="echo \Illuminate\Support\Facades\DB::table('failed_jobs')->count();")
echo "failed_jobs=${failed}"

echo "=== APP HEALTH ==="
php artisan about --only=environment,cache,drivers | head -20

echo "=== FEATURE SPOT CHECKS (read-only) ==="
php artisan tinker --execute="
\$campaigns = \App\Models\MarketingSource::query()->whereNull('parent_id')->count();
\$logs = \App\Models\ActivityLog::query()->count();
\$usersWithCreator = \App\Models\User::query()->whereNotNull('created_by_user_id')->count();
echo \"root_campaigns={\$campaigns}\n\";
echo \"activity_logs={\$logs}\n\";
echo \"users_with_creator={\$usersWithCreator}\n\";
echo 'approval_service=' . (class_exists(\App\Services\Marketing\CampaignApprovalService::class) ? 'OK' : 'MISSING') . PHP_EOL;
echo 'marketing_metrics=' . (class_exists(\App\Support\MarketingMetrics::class) ? 'OK' : 'MISSING') . PHP_EOL;
"

echo "=== ROUTE CHECK (new routes registered) ==="
php artisan route:list --path=activity-logs | head -10
php artisan route:list --path=landing-approvals | head -15

echo "=== HTTPS ENDPOINTS ==="
for path in /login /admin/activity-logs /admin/landing-approvals /marketing/campaigns /admin/shipping/reconciliation; do
  code=$(curl -s -o /dev/null -w '%{http_code}' "https://erm-pushsale.duckdns.org${path}")
  echo "${path} -> ${code}"
done

echo "=== WEBHOOK SMOKE (expect 401/422 without token) ==="
code=$(curl -s -o /dev/null -w '%{http_code}' -X POST "https://erm-pushsale.duckdns.org/api/v1/webhooks/ladipage" -H 'Content-Type: application/json' -d '{}')
echo "ladipage_webhook_no_key -> ${code}"

echo "=== RESTORE PROD COMPOSER (no-dev) ==="
composer install --no-dev --optimize-autoloader --no-interaction
sudo rm -f bootstrap/cache/packages.php bootstrap/cache/services.php 2>/dev/null || true
sudo mkdir -p bootstrap/cache
sudo chown -R ubuntu:www-data bootstrap/cache storage
sudo chmod -R ug+rwx bootstrap/cache storage
composer install --no-dev --optimize-autoloader --no-interaction
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

echo "=== DONE PRODUCTION VERIFY ==="
