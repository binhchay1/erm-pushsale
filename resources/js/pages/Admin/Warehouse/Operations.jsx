import { Head, router } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { PushsalePager } from '@/components/reports/PushsaleReportChrome';
import { StatusTabs } from '@/components/operations/StatusTabs';
import { WarehouseOrderTable } from '@/components/operations/WarehouseOrderTable';
import { useT } from '@/providers/I18nProvider';

function WarehousePagination({ meta, routeUrl, filters }) {
    if (!meta) return null;

    const visit = (overrides = {}) => {
        router.get(routeUrl, {
            ...filters,
            ...overrides,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <div className="ps-warehouse-pagination">
            <div>Hiển thị {meta.from ?? 0} - {meta.to ?? 0} / {meta.total ?? 0} đơn hàng</div>
            <PushsalePager
                current={meta.current_page ?? 1}
                totalPages={meta.last_page ?? 1}
                onPage={(page) => visit({ page })}
            />
            <label>
                Hiển thị
                <select
                    value={meta.per_page ?? 20}
                    onChange={(event) => visit({ page: 1, per_page: event.target.value })}
                >
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                dòng
            </label>
        </div>
    );
}

export default function WarehouseOperations({
    filters = {},
    filterOptions = {},
    filterFields = [],
    report = { rows: { data: [], meta: null }, statusTabs: [] },
    pageTitle,
    routeUrl = '/admin/warehouse/operations',
    shippingApiBase = '/admin/shipping/orders',
    canDeleteOrder = false,
    activeMenuCode = '5.1',
}) {
    const t = useT();
    const title = pageTitle ?? t('pages.warehouse_ops.title');
    const rows = report?.rows?.data ?? [];
    const meta = report?.rows?.meta ?? null;

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={title} />

            <section className="ps-warehouse-operations-page">
                <PageHeader title={title} description={t('pages.warehouse_ops.desc')} />

                <div className="ps-warehouse-operation-content">
                    <ReportFilterBar
                        routeUrl={routeUrl}
                        filters={filters}
                        filterOptions={filterOptions}
                        filterFields={filterFields}
                    />

                    <StatusTabs
                        routeUrl={routeUrl}
                        filters={filters}
                        tabs={report.statusTabs ?? []}
                        filterKey="delivery_status"
                    />

                    <WarehouseOrderTable
                        rows={rows}
                        apiBase={shippingApiBase}
                        canDeleteOrder={canDeleteOrder}
                    />

                    <WarehousePagination meta={meta} routeUrl={routeUrl} filters={filters} />
                </div>
            </section>
        </AppLayout>
    );
}
