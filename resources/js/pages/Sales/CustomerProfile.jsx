import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { useT } from '@/providers/I18nProvider';

export default function CustomerProfile({ filters, filterOptions, filterFields, report }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('pages.customer_profile.title')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('pages.customer_profile.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('pages.customer_profile.desc_detail')}</p>
                </div>

                <ReportFilterBar
                    routeUrl="/sales/customers"
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <OperationOrderTable rows={report.rows} />
            </div>
        </AppLayout>
    );
}
