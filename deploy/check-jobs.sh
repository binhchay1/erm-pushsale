#!/usr/bin/env bash
cd /var/www/erm-pushsale
php artisan tinker --execute="
\$jobs = DB::table('jobs')->get(['id','queue','attempts','reserved_at','available_at','created_at']);
foreach (\$jobs as \$j) {
    echo json_encode(\$j) . PHP_EOL;
}
"
