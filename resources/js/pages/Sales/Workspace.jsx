import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';

export default function Workspace({ filters, filterOptions, filterFields, report, operationStatusOptions }) {
    useEffect(() => {
        console.info(
            '[ERM SaleOps] Telesale tác nghiệp — URL: /sales/workspace\n' +
                '• Đăng nhập: sales@saleops.local / password\n' +
                '• Nút Gọi + Chuyển trạng thái: cột Hành động (đơn đang mở)\n' +
                '• Modal chuyển trạng thái: OperationStatusDialog\n' +
                '• Chốt đơn: CloseOrderButton + POST /sales/orders/{id}/close'
        );
    }, []);

    return (
        <AppLayout>
            <Head title="Sale tác nghiệp" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Sale tác nghiệp</h1>
                    <p className="text-sm text-muted-foreground">
                        Hàng đợi lead, gọi khách và chốt đơn — pipeline tương tác KH
                    </p>
                </div>

                <ReportFilterBar
                    routeUrl="/sales/workspace"
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <StatusTabs
                    routeUrl="/sales/workspace"
                    filters={filters}
                    tabs={report.statusTabs}
                />

                <OperationOrderTable
                    rows={report.rows}
                    enableSaleActions
                    enableCloseOrder
                    operationStatusOptions={operationStatusOptions}
                />
            </div>
        </AppLayout>
    );
}
