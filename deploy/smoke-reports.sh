#!/usr/bin/env bash
set -euo pipefail
cd /var/www/erm-pushsale

php artisan tinker --execute="
\$checks = [];

// Shipping reconciliation
try {
    \$filter = ['tab' => 'overview', 'period_type' => 'month', 'month' => date('Y-m'), 'quarter' => 1, 'year' => (int) date('Y'), 'date_from' => null, 'date_to' => null, 'provider' => null, 'recon_status' => null, 'delivery_status' => null, 'search' => null];
    \$s = app(App\Services\Shipping\ShippingReconciliationService::class);
    \$summary = \$s->summary(\$filter);
    \$s->unmatchedSettlements(\$filter);
    \$checks[] = 'reconciliation_ok orders=' . (\$summary['total_orders'] ?? '?');
} catch (Throwable \$e) {
    \$checks[] = 'reconciliation_fail: ' . \$e->getMessage();
}

\$admin = App\Models\User::query()->where('role', 'admin')->first();
\$marketer = App\Models\User::query()->where('role', 'marketing')->first();
\$viewer = \$marketer ?? \$admin;

if (\$viewer) {
    \$req = fn (string \$path, array \$params = []) => App\Data\ReportFilterData::fromRequest(
        Illuminate\Http\Request::create(\$path, 'GET', \$params),
        \$viewer,
    );

    try {
        \$r = app(App\Services\Reports\RevenueReportService::class)->forMarketers(\$req('/marketing/revenue', ['preset' => 'last_7_days']), \$viewer);
        \$checks[] = 'marketing_revenue_ok rows=' . count(\$r['rows'] ?? []);
    } catch (Throwable \$e) {
        \$checks[] = 'marketing_revenue_fail: ' . \$e->getMessage();
    }

    try {
        \$r = app(App\Services\Reports\MarketingCampaignReportService::class)->build(\$req('/marketing/campaign-report', ['preset' => 'last_7_days']), \$viewer);
        \$checks[] = 'campaign_report_ok rows=' . count(\$r['rows'] ?? []);
    } catch (Throwable \$e) {
        \$checks[] = 'campaign_report_fail: ' . \$e->getMessage();
    }

    try {
        \$r = app(App\Services\Reports\MarketingDashboardService::class)->build(\$req('/marketing/dashboard', ['preset' => 'last_7_days']), \$viewer);
        \$checks[] = 'marketing_dashboard_ok tree=' . count(\$r['teamTree'] ?? []);
    } catch (Throwable \$e) {
        \$checks[] = 'marketing_dashboard_fail: ' . \$e->getMessage();
    }
}

if (\$admin) {
    try {
        \$r = app(App\Services\Reports\RevenueReportService::class)->forSales(\$req('/sales/revenue', ['preset' => 'last_7_days']), \$admin);
        \$checks[] = 'sales_revenue_ok rows=' . count(\$r['rows'] ?? []);
    } catch (Throwable \$e) {
        \$checks[] = 'sales_revenue_fail: ' . \$e->getMessage();
    }

    try {
        \$r = app(App\Services\Reports\ReportMetricService::class)->kpiSummary(\$admin, \$req('/admin/dashboard', ['preset' => 'last_7_days']));
        \$checks[] = 'admin_kpi_ok revenue=' . (\$r['revenue'] ?? '?');
    } catch (Throwable \$e) {
        \$checks[] = 'admin_kpi_fail: ' . \$e->getMessage();
    }
}

foreach (\$checks as \$line) {
    echo \$line . PHP_EOL;
}
"
