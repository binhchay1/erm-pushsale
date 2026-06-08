import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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

            <div className="space-y-8 pb-8">
                <div className="space-y-2">
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Sale tác nghiệp</h1>
                    <p className="max-w-2xl text-sm text-muted-foreground sm:text-base">
                        Hàng đợi lead, gọi khách và chốt đơn — pipeline tương tác khách hàng
                    </p>
                </div>

                <ReportFilterBar
                    routeUrl="/sales/workspace"
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <Card className="border-border/80 shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle>Pipeline tác nghiệp</CardTitle>
                        <CardDescription>
                            Lọc nhanh theo giai đoạn gọi — bảng chi tiết bên dưới
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
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
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
