#!/usr/bin/env bash
set -euo pipefail
cd /var/www/erm-pushsale
php artisan tinker --execute="
echo 'orders_count=' . DB::table('orders')->count() . PHP_EOL;
\$orders = DB::table('orders')->orderByDesc('id')->limit(5)->get(['id','customer_name','customer_phone','marketing_source_id','data_arrived_at','created_at']);
foreach (\$orders as \$o) { echo json_encode(\$o) . PHP_EOL; }
\$li = DB::table('lead_ingestions')->orderByDesc('id')->limit(5)->get();
foreach (\$li as \$x) { echo json_encode(\$x) . PHP_EOL; }
\$ms = DB::table('marketing_sources')->whereNull('parent_id')->get(['id','name','contacts','interactions','webhook_token']);
foreach (\$ms as \$m) { echo json_encode(\$m) . PHP_EOL; }
"

