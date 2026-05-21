import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';

export default function SaleRevenueReport({ filters, filterOptions, report }) {
    return (
        <AppLayout>
            <Head title="BC doanh số Sale" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Báo cáo doanh số Sale chi tiết</h1>
                </div>

                <ReportFilterBar
                    routeUrl="/admin/sales/revenue"
                    filters={filters}
                    filterOptions={filterOptions}
                />

                <RevenueMetricsTable rows={report.rows} nameKey="saleName" nameLabel="Sale" />
            </div>
        </AppLayout>
    );
}
