import { Head, usePage } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { CreateSaleOrderDialog } from '@/components/operations/CreateSaleOrderDialog';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { Card, CardContent } from '@/components/ui/card';
import { useRealtimeReload } from '@/hooks/useRealtimeReload';
import { useLandingUpsellHoldRefresh } from '@/hooks/useLandingUpsellHoldRefresh';

export default function Workspace({
    filters,
    filterOptions,
    filterFields,
    report,
    operationStatusOptions,
    carrierOptions = [],
    shippingServiceOptions = {},
    itemTypeOptions = ['product', 'combo', 'upsell', 'gift'],
    warehouseOptions = [],
    productOptions = [],
    sourceOptions = [],
    routeUrl = '/sales/workspace',
    actionBaseUrl = '/sales',
    manualUrl = '/sales/leads/manual',
}) {
    const authId = usePage().props.auth?.user?.id;

    useRealtimeReload('dashboard.sales', '.workspace.changed', ['report'], {
        shouldReload: (payload) => Number(payload?.sale_user_id) === Number(authId),
    });

    useLandingUpsellHoldRefresh(report?.rows);

    return (
        <AppLayout>
            <Head title="Sale tác nghiệp" />

            <div className="space-y-8 pb-8">
                <PageHeader title="Sale tác nghiệp" />

                <ReportFilterBar
                    routeUrl={routeUrl}
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <Card>
                    <CardContent>
                        <StatusTabs
                            routeUrl={routeUrl}
                            filters={filters}
                            tabs={report.statusTabs}
                        />
                        <OperationOrderTable
                            rows={report.rows}
                            enableSaleActions
                            enableCloseOrder
                            operationStatusOptions={operationStatusOptions}
                            carrierOptions={carrierOptions}
                            shippingServiceOptions={shippingServiceOptions}
                            itemTypeOptions={itemTypeOptions}
                            warehouseOptions={warehouseOptions}
                            productOptions={productOptions}
                            actionBaseUrl={actionBaseUrl}
                        />
                    </CardContent>
                </Card>

                <CreateSaleOrderDialog
                    manualUrl={manualUrl}
                    sources={sourceOptions}
                    productOptions={productOptions}
                    warehouseOptions={warehouseOptions}
                />
            </div>
        </AppLayout>
    );
}
