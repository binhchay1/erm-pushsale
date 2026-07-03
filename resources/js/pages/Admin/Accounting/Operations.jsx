import { Head } from '@inertiajs/react';
import { Calculator } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { AccountingReconTable } from '@/components/operations/AccountingReconTable';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { useT } from '@/providers/I18nProvider';

export default function AccountingOperations({ filters, filterOptions, report }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('pages.accounting.ops_title')} />

            <div className="space-y-4">
                <PageHeader icon={Calculator} title={t('pages.accounting.ops_title')} />

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

                <AccountingReconTable rows={report.rows} totals={report.totals} enableDeleteOrder />
            </div>
        </AppLayout>
    );
}
