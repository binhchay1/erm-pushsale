import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';
import { useT } from '@/providers/I18nProvider';

export default function SaleRevenueReport({ filters, filterOptions, filterFields, report }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('reports.revenue_sales.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('reports.revenue_sales.report_title')}
                    description={t('reports.revenue_sales.report_desc')}
                />

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
