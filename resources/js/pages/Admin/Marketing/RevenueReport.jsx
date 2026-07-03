import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';
import { MarketingKpiTable } from '@/components/reports/MarketingKpiTable';
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
                <PageHeader
                    title={t('reports.revenue_marketing.report_title')}
                    description={t('reports.revenue_marketing.report_desc')}
                />

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

                {report.marketingKpiLegend && (
                    <>
                        <ul className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                            {report.marketingKpiLegend.map((item) => (
                                <li key={item.key} className="rounded-md bg-muted px-2 py-1">
                                    {item.label}
                                </li>
                            ))}
                        </ul>
                        <MarketingKpiTable
                            rows={report.rows}
                            nameKey="marketerName"
                            nameLabel={t('reports.revenue_marketing.name_label')}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
