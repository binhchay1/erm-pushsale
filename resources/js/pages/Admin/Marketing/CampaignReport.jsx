import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { MarketingCampaignTable } from '@/components/reports/MarketingCampaignTable';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';

export default function MarketingCampaignReport({
    filters,
    filterOptions,
    filterFields,
    report,
    routeUrl = '/admin/marketing/campaign-report',
    budgetUpdateUrl = '/admin/marketing/campaigns',
    canEditBudget = true,
}) {
    return (
        <AppLayout>
            <Head title="BC chiến dịch Marketing" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Báo cáo chiến dịch Marketing</h1>
                        <p className="text-sm text-muted-foreground">
                            Lead sinh ra · lead rác · chi phí quảng cáo (nhập tay) · doanh thu thực tế
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

                <MarketingCampaignTable
                    rows={report?.rows ?? []}
                    budgetUpdateUrl={budgetUpdateUrl}
                    canEditBudget={canEditBudget}
                />
            </div>
        </AppLayout>
    );
}
