import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { useT } from '@/providers/I18nProvider';

export default function AccountingOperations({ filters, filterOptions, report }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('pages.accounting.ops_title')} />

            <div className="space-y-4">
                <h1 className="text-2xl font-bold tracking-tight">{t('pages.accounting.ops_title')}</h1>

                <ReportFilterBar
                    routeUrl="/admin/accounting"
                    filters={filters}
                    filterOptions={filterOptions}
                />

                <StatusTabs
                    routeUrl="/admin/accounting"
                    filters={filters}
                    tabs={report.statusTabs}
                    filterKey="delivery_status"
                />

                <OperationOrderTable rows={report.rows} enableDeleteOrder />
            </div>
        </AppLayout>
    );
}
