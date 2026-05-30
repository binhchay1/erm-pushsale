import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';

export default function CustomerProfile({ filters, filterOptions, filterFields, report }) {
    return (
        <AppLayout>
            <Head title="Hồ sơ khách hàng" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Hồ sơ khách hàng</h1>
                    <p className="text-sm text-muted-foreground">360° theo SĐT — lịch sử mua & tác nghiệp</p>
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
