import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { SalesPerformanceTable } from '@/components/reports/SalesPerformanceTable';
import { TeamRevenueTable } from '@/components/reports/TeamRevenueTable';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useT } from '@/providers/I18nProvider';

export default function SalesPerformanceReport({
    filters,
    filterOptions,
    filterFields,
    report,
    routeUrl = '/admin/sales/performance',
}) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('reports.performance.title')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{t('reports.performance.report_title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('reports.performance.report_desc')}</p>
                    </div>
                    <ReportExportButton routeUrl={routeUrl} filters={filters} />
                </div>

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                {report?.teamTree && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle>{t('reports.performance.team_revenue')}</CardTitle>
                            <CardDescription>{t('reports.performance.team_desc')}</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-2">
                            <TeamRevenueTable
                                roots={report.teamTree.roots}
                                emptyText={t('reports.performance.team_empty')}
                            />
                        </CardContent>
                    </Card>
                )}

                <SalesPerformanceTable rows={report?.rows ?? []} />
            </div>
        </AppLayout>
    );
}
