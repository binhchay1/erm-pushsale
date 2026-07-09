import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { ReportPagination } from '@/components/reports/ReportPagination';
import { useT } from '@/providers/I18nProvider';

export default function CustomerProfile({ filters, filterOptions, filterFields, report, routeUrl = '/customers' }) {
    const t = useT();
    const rows = report?.rows?.data ?? report?.rows ?? [];
    const pagination = report?.rows?.meta ?? null;

    return (
        <AppLayout>
            <Head title={t('pages.customer_profile.title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t('pages.customer_profile.title')}
                    description={t('pages.customer_profile.desc_detail')}
                />

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <div id="customer-profile-table" className="scroll-mt-4">
                    <OperationOrderTable rows={rows} />
                </div>

                <ReportPagination
                    routeUrl={routeUrl}
                    filters={filters}
                    meta={pagination}
                    scrollTargetId="customer-profile-table"
                />
            </div>
        </AppLayout>
    );
}
