import { Head, usePage } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { OperationOrderTable } from '@/components/operations/OperationOrderTable';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useRealtimeReload } from '@/hooks/useRealtimeReload';
import { useT } from '@/providers/I18nProvider';

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
}) {
    const t = useT();
    const authId = usePage().props.auth?.user?.id;

    useRealtimeReload('dashboard.sales', '.workspace.changed', ['report'], {
        shouldReload: (payload) => Number(payload?.sale_user_id) === Number(authId),
    });

    return (
        <AppLayout>
            <Head title={t('pages.workspace.title')} />

            <div className="space-y-8 pb-8">
                <PageHeader
                    title={t('pages.workspace.title')}
                    description={t('pages.workspace.desc_detail')}
                />

                <ReportFilterBar
                    routeUrl="/sales/workspace"
                    filters={filters}
                    filterOptions={filterOptions}
                    filterFields={filterFields}
                />

                <Card className="border-border/80 shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle>{t('pages.workspace.pipeline')}</CardTitle>
                        <CardDescription>{t('pages.workspace.pipeline_desc')}</CardDescription>
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
                            carrierOptions={carrierOptions}
                            shippingServiceOptions={shippingServiceOptions}
                            itemTypeOptions={itemTypeOptions}
                            warehouseOptions={warehouseOptions}
                            productOptions={productOptions}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
