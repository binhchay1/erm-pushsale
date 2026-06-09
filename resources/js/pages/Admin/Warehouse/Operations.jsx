import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { WarehouseOrderTable } from '@/components/operations/WarehouseOrderTable';

export default function WarehouseOperations({
    filters,
    filterOptions,
    filterFields,
    report,
    pageTitle,
    routeUrl = '/admin/warehouse/operations',
    shippingApiBase = '/admin/shipping/orders',
    canDeleteOrder = false,
}) {
    return (
        <AppLayout>
            <Head title={pageTitle ?? 'Xuất kho & vận đơn'} />

            <div className="space-y-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {pageTitle ?? 'Xuất kho & vận đơn'}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Đơn đã chốt chờ xuất kho — tạo vận đơn, in vận đơn và theo dõi giao hàng.
                    </p>
                </div>

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <StatusTabs
                    routeUrl={routeUrl}
                    filters={filters}
                    tabs={report.statusTabs}
                    filterKey="delivery_status"
                />

                <WarehouseOrderTable
                    rows={report.rows}
                    apiBase={shippingApiBase}
                    canDeleteOrder={canDeleteOrder}
                />
            </div>
        </AppLayout>
    );
}
