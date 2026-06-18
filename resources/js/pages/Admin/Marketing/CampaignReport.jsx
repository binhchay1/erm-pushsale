import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { MarketingCampaignTable } from '@/components/reports/MarketingCampaignTable';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { useT } from '@/providers/I18nProvider';

export default function MarketingCampaignReport({
    filters,
    filterOptions,
    filterFields,
    report,
    routeUrl = '/admin/marketing/campaign-report',
    budgetUpdateUrl = '/admin/marketing/campaigns',
    canEditBudget = true,
}) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('reports.campaign_report.title')} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{t('reports.campaign_report.report_title')}</h1>
                        <p className="text-sm text-muted-foreground">{t('reports.campaign_report.report_desc')}</p>
                    </div>
                    <ReportExportButton routeUrl={routeUrl} filters={filters} />
                </div>

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <MarketingCampaignTable
                    rows={report?.rows ?? []}
                    budgetUpdateUrl={budgetUpdateUrl}
                    canEditBudget={canEditBudget}
                />
            </div>
        </AppLayout>
    );
}
