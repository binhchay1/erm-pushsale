#!/usr/bin/env bash
set -euo pipefail
cd /var/www/erm-pushsale
php artisan tinker --execute="
\$f = new App\Data\ReportFilterData();
\$s = app(App\Services\Reports\MarketingDashboardService::class)->build(\$f);
echo 'rows=' . count(\$s['rows']) . PHP_EOL;
echo 'closed=' . \$s['kpis']['closedOrders'] . PHP_EOL;
echo 'tree_roots=' . count(\$s['teamTree']['roots']) . PHP_EOL;
"
