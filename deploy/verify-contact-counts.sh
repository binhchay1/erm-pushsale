#!/usr/bin/env bash
set -euo pipefail
cd /var/www/erm-pushsale
DATE="${1:-$(date +%Y-%m-%d)}"

php artisan tinker --execute="
\$filter = App\Data\ReportFilterData::fromRequest(
    Illuminate\Http\Request::create('/x', 'GET', ['date_from' => '$DATE', 'date_to' => '$DATE']),
    App\Models\User::where('role', 'admin')->first(),
);
\$admin = App\Models\User::where('role', 'admin')->first();
\$work = app(App\Services\Reports\ExtraReportService::class)->build('marketing-3', \$admin, \$filter);
\$dash = app(App\Services\Reports\MarketingDashboardService::class)->build(\$filter);
echo 'marketing-3_total=' . (\$work['totals']['contacts'] ?? 0) . PHP_EOL;
echo 'mkt_dashboard_kpi=' . (\$dash['kpis']['contacts'] ?? 0) . PHP_EOL;
echo 'LeadContactMetrics_today=' . App\Support\LeadContactMetrics::countToday() . PHP_EOL;
foreach (\$work['rows'] as \$r) {
  echo '  ' . \$r['name'] . ' contacts=' . \$r['contacts'] . PHP_EOL;
}
"
