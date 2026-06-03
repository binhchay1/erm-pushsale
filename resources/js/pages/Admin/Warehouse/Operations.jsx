import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';

export default function WarehouseOperations({ filters, filterOptions, report, pageTitle }) {
    return (
        <AppLayout>
            <Head title={pageTitle ?? 'Thủ kho tác nghiệp'} />

            <div className="space-y-4">
                <h1 className="text-2xl font-bold tracking-tight">{pageTitle ?? 'Thủ kho tác nghiệp'}</h1>

                <ReportFilterBar
                    routeUrl="/admin/warehouse/operations"
                    filters={filters}
                    filterOptions={filterOptions}
                />

                <StatusTabs
                    routeUrl="/admin/warehouse/operations"
                    filters={filters}
                    tabs={report.statusTabs}
                    filterKey="delivery_status"
                />

                <OperationOrderTable rows={report.rows} enableDeleteOrder />
            </div>
        </AppLayout>
    );
}
