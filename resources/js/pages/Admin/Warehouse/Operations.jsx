import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { WarehouseOrderTable } from '@/components/operations/WarehouseOrderTable';
import { useT } from '@/providers/I18nProvider';

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
    const t = useT();
    const title = pageTitle ?? t('pages.warehouse_ops.title');

    return (
        <AppLayout>
            <Head title={title} />

            <div className="space-y-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                    <p className="text-sm text-muted-foreground">{t('pages.warehouse_ops.desc')}</p>
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
