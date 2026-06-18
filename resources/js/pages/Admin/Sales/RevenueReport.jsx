import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';
import { useT } from '@/providers/I18nProvider';

export default function SaleRevenueReport({ filters, filterOptions, filterFields, report }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('reports.revenue_sales.title')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('reports.revenue_sales.report_title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('reports.revenue_sales.report_desc')}</p>
                </div>

                <ReportFilterBar
                    routeUrl="/admin/sales/revenue"
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <RevenueMetricsTable
                    rows={report.rows}
                    nameKey="saleName"
                    nameLabel={t('reports.revenue_sales.name_label')}
                />
            </div>
        </AppLayout>
    );
}
