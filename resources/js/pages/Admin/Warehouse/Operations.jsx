import { Head, router } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { WarehouseFilterPanel } from '@/components/operations/WarehouseFilterPanel';
import { WarehouseOrderTable } from '@/components/operations/WarehouseOrderTable';
import { PushsalePager } from '@/components/reports/PushsaleReportChrome';

function Pagination({ meta, routeUrl, filters }) {
    if (!meta) return null;
    const visit = (overrides) => router.get(routeUrl, { ...filters, ...overrides }, { preserveState: true, preserveScroll: true, replace: true });
    return <div className="ps-wh-pagination"><span>Hiển thị {meta.from ?? 0} - {meta.to ?? 0} / {meta.total ?? 0} đơn</span><PushsalePager current={meta.current_page ?? 1} totalPages={meta.last_page ?? 1} onPage={(page) => visit({ page })} /><label>Hiển thị <select value={meta.per_page ?? 20} onChange={(e) => visit({ page: 1, per_page: e.target.value })}><option>10</option><option>20</option><option>50</option><option>100</option></select> dòng</label></div>;
}

export default function WarehouseOperations({
    filters = {}, filterOptions = {}, report = { rows: { data: [], meta: null }, statusTabs: [], summary: {} },
    pageTitle = 'Thủ kho tác nghiệp', routeUrl = '/admin/warehouse/operations', shippingApiBase = '/admin/shipping/orders',
    actionApiBase = '/admin/warehouse/orders', canDeleteOrder = false, activeMenuCode = '5.1',
}) {
    const setTab = (value) => router.get(routeUrl, { ...filters, delivery_status: value === 'all' ? undefined : value, page: 1 }, { preserveState: true, preserveScroll: true, replace: true });
    const active = filters.delivery_status ?? 'all';

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={pageTitle} />
            <section className="ps-wh-page ps-wh-legacy-page">
                <WarehouseFilterPanel title={pageTitle} routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} />
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
                <WarehouseOrderTable rows={report.rows?.data ?? []} apiBase={shippingApiBase} actionApiBase={actionApiBase} filterOptions={filterOptions} canDeleteOrder={canDeleteOrder} />
                <Pagination meta={report.rows?.meta} routeUrl={routeUrl} filters={filters} />
            </section>
        </AppLayout>
    );
}
