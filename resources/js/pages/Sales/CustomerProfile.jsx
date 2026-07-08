import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { useT } from '@/providers/I18nProvider';

export default function CustomerProfile({ filters, filterOptions, filterFields, report, routeUrl = '/customers' }) {
    const t = useT();

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

                <OperationOrderTable rows={report.rows} />
            </div>
        </AppLayout>
    );
}
