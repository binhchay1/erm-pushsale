import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { SalesPerformanceTable } from '@/components/reports/SalesPerformanceTable';

export default function SalesPerformanceReport({
    filters,
    filterOptions,
    filterFields,
    report,
    routeUrl = '/admin/sales/performance',
}) {
    return (
        <AppLayout>
            <Head title="BC hiệu suất Sale" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Báo cáo hiệu suất Telesale</h1>
                        <p className="text-sm text-muted-foreground">
                            Lead nhận · cuộc gọi · tỷ lệ bắt máy · chốt đơn · doanh thu theo từng sale
                        </p>
                    </div>
                    <ReportExportButton routeUrl={routeUrl} filters={filters} />
                </div>

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <SalesPerformanceTable rows={report?.rows ?? []} />
            </div>
        </AppLayout>
    );
}
