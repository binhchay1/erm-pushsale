import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { AccountingOperationFilters } from '@/components/operations/AccountingOperationFilters';
import { AccountingReconTable } from '@/components/operations/AccountingReconTable';

export default function AccountingOperations({ filters = {}, filterOptions = {}, report = { rows: [], totals: {}, statusTabs: [] } }) {
    return (
        <AppLayout activeMenuCode="6.1">
            <Head title="Kế toán tác nghiệp" />
            <section className="ps-acc-page ps-operation-page">
                <AccountingOperationFilters
                    routeUrl="/admin/accounting"
                    filters={filters}
                    filterOptions={filterOptions}
                    statusTabs={report.statusTabs ?? []}
                />
                <AccountingReconTable rows={report.rows ?? []} totals={report.totals} enableDeleteOrder />
            </section>
        </AppLayout>
    );
}
