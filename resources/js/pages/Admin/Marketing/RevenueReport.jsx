import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';
import { useT } from '@/providers/I18nProvider';

export default function MarketingRevenueReport({
    filters,
    filterOptions,
    filterFields,
    report,
    routeUrl = '/admin/marketing/revenue',
}) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('reports.revenue_marketing.title')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('reports.revenue_marketing.report_title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('reports.revenue_marketing.report_desc')}</p>
                </div>

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <ul className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                    {report.formulaLegend?.map((f) => (
                        <li key={f.key} className="rounded-md bg-muted px-2 py-1">
                            <strong>{f.key}.</strong> {f.label}
                        </li>
                    ))}
                </ul>

                <RevenueMetricsTable
                    rows={report.rows}
                    nameKey="marketerName"
                    nameLabel={t('reports.revenue_marketing.name_label')}
                />
            </div>
        </AppLayout>
    );
}
