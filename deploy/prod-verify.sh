#!/usr/bin/env bash
set -euo pipefail

cd /var/www/erm-pushsale

echo "=== HORIZON / REDIS QUEUE HEALTH ==="
sudo -u www-data php artisan horizon:status
sudo -u www-data php artisan queue:wait-empty --timeout=60 || true
php artisan tinker --execute="
\$redis = Redis::connection(config('queue.connections.redis.connection', 'queue'));
foreach (collect(config('saleops.queues'))->unique() as \$queue) {
    \$key = 'queues:'.\$queue;
    echo \$queue.': ready='.(int) \$redis->llen(\$key)
        .' delayed='.(int) \$redis->zcard(\$key.':delayed')
        .' reserved='.(int) \$redis->zcard(\$key.':reserved').PHP_EOL;
}
echo 'legacy_database_jobs='.(Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0).PHP_EOL;
echo 'failed_jobs='.(Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0).PHP_EOL;
"

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
for path in /login /admin/activity-logs /admin/marketing/landing-approvals /marketing/campaigns /admin/shipping/reconciliation; do
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
