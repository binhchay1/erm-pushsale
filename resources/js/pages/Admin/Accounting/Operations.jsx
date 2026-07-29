import { Head, router } from '@inertiajs/react';

import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import AppLayout from '@/layouts/AppLayout';
import { WarehouseFilterPanel } from '@/components/operations/WarehouseFilterPanel';
import { WarehouseOrderTable } from '@/components/operations/WarehouseOrderTable';

export default function AccountingOperations({
    filters = {},
    filterOptions = {},
    report = { rows: { data: [], meta: null }, statusTabs: [], summary: {} },
    pageTitle = 'Kế toán tác nghiệp',
    routeUrl = '/admin/accounting',
    shippingApiBase = '/admin/shipping/orders',
    actionApiBase = '/admin/warehouse/orders',
    canDeleteOrder = false,
    activeMenuCode = '6.1',
}) {
    const setTab = (value) => router.get(routeUrl, { ...filters, delivery_status: value === 'all' ? undefined : value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
    const active = filters.delivery_status ?? 'all';

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={pageTitle} />
            <section className="ps-wh-page ps-wh-legacy-page ps-acc-page">
                <WarehouseFilterPanel title={pageTitle} routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} pageCode={activeMenuCode} />
                <div className="row ttgh-acc ps-wh-status-row">
                    <div className="col-sm-12">
                        {(report.statusTabs ?? []).map((tab) => (
                            <button
                                key={tab.value}
                                type="button"
                                className={`dm-tac-nghiep dm-tac-nghiep${tab.code ?? tab.value} ${active === tab.value ? 'selected' : ''}`}
                                onClick={() => setTab(tab.value)}
                            >
                                <span className={`flag level-${tab.level ?? 1}`} />
                                <span className="text">{tab.label}</span>
                                <span className="count">{tab.count ? `(${Number(tab.count).toLocaleString('vi-VN')})` : ''}</span>
                                <span className="live-stream" />
                                <i className="fa fa-angle-double-right" />
                            </button>
                        ))}
                    </div>
                </div>
                <WarehouseOrderTable
                    rows={report.rows?.data ?? []}
                    apiBase={shippingApiBase}
                    actionApiBase={actionApiBase}
                    filterOptions={filterOptions}
                    canDeleteOrder={canDeleteOrder}
                    variant="accounting"
                />
                <PushsalePagination meta={report.rows?.meta} routeUrl={routeUrl} filters={filters} itemLabel="đơn" />
            </section>
        </AppLayout>
    );
}
