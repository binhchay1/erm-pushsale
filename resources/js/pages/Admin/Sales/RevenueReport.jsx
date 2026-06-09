import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';

export default function SaleRevenueReport({ filters, filterOptions, filterFields, report }) {
    return (
        <AppLayout>
            <Head title="Doanh số Telesale" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Báo cáo doanh số Telesale</h1>
                    <p className="text-sm text-muted-foreground">
                        Tổng hợp số đơn, tỷ lệ chốt và doanh thu theo từng nhân viên Telesale
                    </p>
                </div>

                <ReportFilterBar
                    routeUrl="/admin/sales/revenue"
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <RevenueMetricsTable rows={report.rows} nameKey="saleName" nameLabel="Sale" />
            </div>
        </AppLayout>
    );
}
