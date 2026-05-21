import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';

export default function AccountingOperations({ filters, filterOptions, report }) {
    return (
        <AppLayout>
            <Head title="Kế toán tác nghiệp" />

            <div className="space-y-4">
                <h1 className="text-2xl font-bold tracking-tight">Kế toán tác nghiệp</h1>

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

                <OperationOrderTable rows={report.rows} />
            </div>
        </AppLayout>
    );
}
