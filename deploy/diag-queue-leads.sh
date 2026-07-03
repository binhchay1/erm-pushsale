#!/usr/bin/env bash
set -euo pipefail
cd /var/www/erm-pushsale

echo "=== ENV (queue/db) ==="
grep -E '^(QUEUE_|DB_)' .env | head -8 || true

echo "=== PENDING JOBS ==="
php artisan tinker --execute="echo 'jobs=' . \Illuminate\Support\Facades\DB::table('jobs')->count() . PHP_EOL;"

echo "=== INBOUND EVENTS (latest 3) ==="
php artisan tinker --execute="print_r(\Illuminate\Support\Facades\DB::table('inbound_events')->orderByDesc('id')->limit(3)->get(['id','status','channel','error_message'])->toArray());"

echo "=== LEAD INGESTIONS (latest 3) ==="
php artisan tinker --execute="print_r(\Illuminate\Support\Facades\DB::table('lead_ingestions')->orderByDesc('id')->limit(3)->get(['id','status','platform','customer_phone'])->toArray());"

echo "=== ORDERS (latest 3) ==="
php artisan tinker --execute="print_r(\Illuminate\Support\Facades\DB::table('orders')->orderByDesc('id')->limit(3)->get(['id','order_code','customer_phone','sale_user_id','operation_stage'])->toArray());"

echo "=== SUPERVISOR ==="
sudo supervisorctl status | grep pushsale || true
