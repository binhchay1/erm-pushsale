#!/usr/bin/env bash
# So sánh số lead/contact giữa TẤT CẢ báo cáo trong 1 kỳ — tìm chỗ lệch.
set -euo pipefail
cd /var/www/erm-pushsale

FROM="${1:-$(date +%Y-%m-%d)}"
TO="${2:-$FROM}"

echo "commit=$(git rev-parse --short HEAD)"

php artisan tinker --execute="
\$from = '$FROM'; \$to = '$TO';
\$admin = App\Models\User::where('role','admin')->first();
\$req = Illuminate\Http\Request::create('/x','GET',['date_from'=>\$from,'date_to'=>\$to,'date_type'=>'data_arrived']);
\$filter = App\Data\ReportFilterData::fromRequest(\$req, \$admin);

\$start = Illuminate\Support\Carbon::parse(\$from)->startOfDay();
\$end = Illuminate\Support\Carbon::parse(\$to)->endOfDay();

echo '--- GROUND TRUTH (lead_ingestions) ---' . PHP_EOL;
\$raw = App\Models\LeadIngestion::whereBetween('created_at',[\$start,\$end]);
echo 'raw_all_rows_by_created=' . (clone \$raw)->count() . PHP_EOL;
echo 'countable_by_created=' . App\Support\LeadContactMetrics::countableQuery(\$filter)->count() . PHP_EOL;
echo 'unique_phone_primary=' . (clone \$raw)->whereNotIn('status',['duplicate','failed'])->where(fn(\$q)=>\$q->whereNull('external_id')->orWhere('external_id','not like','%:upsell'))->distinct('customer_phone')->count('customer_phone') . PHP_EOL;
echo 'orders_data_arrived=' . App\Models\Order::whereBetween('data_arrived_at',[\$start,\$end])->count() . PHP_EOL;

echo PHP_EOL . '--- ADMIN DASHBOARD ---' . PHP_EOL;
\$adminSnap = App\Services\DashboardStatsService::adminSnapshot(\$admin, \$filter);
echo 'kpi_summary_leads=' . (\$adminSnap['summary']['leads'] ?? 'n/a') . PHP_EOL;
echo 'leads_today_card=' . (\$adminSnap['leads_today'] ?? 'n/a') . PHP_EOL;
\$series = collect(\$adminSnap['lead_series'] ?? []);
echo 'lead_series_sum=' . \$series->sum('value') . ' today_bar=' . (\$series->last()['value'] ?? 'n/a') . PHP_EOL;
echo 'donut_sum=' . collect(\$adminSnap['lead_sources'] ?? [])->sum('value') . PHP_EOL;

echo PHP_EOL . '--- MARKETING DASHBOARD (admin view) ---' . PHP_EOL;
\$mkt = app(App\Services\Reports\MarketingDashboardService::class)->build(\$filter);
echo 'kpi_contacts=' . (\$mkt['kpis']['contacts'] ?? 'n/a') . PHP_EOL;
echo 'rows_contacts_sum=' . collect(\$mkt['rows'])->where('isChild',false)->sum('contacts') . PHP_EOL;
\$tree = collect(\$mkt['teamTree']['roots'] ?? []);
\$treeContacts = 0; \$walk = function(\$nodes) use (&\$walk, &\$treeContacts){ foreach(\$nodes as \$n){ if((\$n['type']??'')==='marketer'){ \$treeContacts += \$n['contacts']??0; } if(!empty(\$n['children'])) \$walk(\$n['children']); } }; \$walk(\$tree->all());
echo 'teamtree_marketer_contacts_sum=' . \$treeContacts . PHP_EOL;

echo PHP_EOL . '--- MARKETING WORK REPORT (marketing-3) ---' . PHP_EOL;
\$work = app(App\Services\Reports\ExtraReportService::class)->build('marketing-3', \$admin, \$filter);
echo 'total_contacts=' . (\$work['totals']['contacts'] ?? 'n/a') . PHP_EOL;
foreach (\$work['rows'] as \$r) { if ((\$r['contacts'] ?? 0) > 0) echo '  ' . \$r['name'] . '=' . \$r['contacts'] . PHP_EOL; }

echo PHP_EOL . '--- CEO REPORT ---' . PHP_EOL;
\$ceo = app(App\Services\Reports\CeoReportService::class)->build(\$filter, \$admin);
echo 'marketing_contacts_sum=' . collect(\$ceo['marketingRows'] ?? [])->sum('contacts') . PHP_EOL;

echo PHP_EOL . '--- TEAM LEADER STATS ---' . PHP_EOL;
\$tls = app(App\Services\Reports\TeamLeaderStatsService::class)->build(\$admin, \$filter);
\$tlsContacts = 0; foreach ((\$tls['rows'] ?? []) as \$t) { foreach ((\$t['children'] ?? []) as \$c) { \$tlsContacts += \$c['contacts'] ?? 0; } }
echo 'members_contacts_sum=' . \$tlsContacts . PHP_EOL;
"
