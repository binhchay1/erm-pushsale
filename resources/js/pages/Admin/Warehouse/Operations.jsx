import { Head, router } from '@inertiajs/react';
import { Banknote, Boxes, CircleDollarSign, RotateCcw, Truck } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { WarehouseFilterPanel } from '@/components/operations/WarehouseFilterPanel';
import { WarehouseOrderTable } from '@/components/operations/WarehouseOrderTable';
import { PushsalePager } from '@/components/reports/PushsaleReportChrome';
import { formatCurrency, formatNumber } from '@/lib/format';

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
    const summary = report.summary ?? {};

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={pageTitle} />
            <section className="ps-wh-page">
                <header className="ps-wh-titlebar"><h1>{pageTitle}</h1><p>Điều phối xuất kho, vận đơn, webhook, hàng hoàn, COD và phí giao vận trên cùng một luồng.</p></header>
                <WarehouseFilterPanel routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} />

                <div className="ps-wh-summary">
                    <div><Boxes /><span>Đơn trong bộ lọc</span><strong>{formatNumber(summary.orders ?? 0)}</strong></div>
                    <div><CircleDollarSign /><span>Tổng giá trị đơn</span><strong>{formatCurrency(summary.grossRevenue ?? 0)}</strong></div>
                    <div><Banknote /><span>COD dự kiến / đã thu</span><strong>{formatCurrency(summary.codExpected ?? 0)} / {formatCurrency(summary.codSettled ?? 0)}</strong></div>
                    <div><Truck /><span>Chi phí giao vận</span><strong>{formatCurrency(summary.carrierCost ?? 0)}</strong></div>
                    <div><RotateCcw /><span>Đơn hoàn</span><strong>{formatNumber(summary.returns ?? 0)}</strong></div>
                </div>

                <div className="ps-wh-tabs">{(report.statusTabs ?? []).map((tab) => <button key={tab.value} className={active === tab.value ? 'active' : ''} onClick={() => setTab(tab.value)}>{tab.label} <b>({tab.count})</b></button>)}</div>
                <WarehouseOrderTable rows={report.rows?.data ?? []} apiBase={shippingApiBase} actionApiBase={actionApiBase} filterOptions={filterOptions} canDeleteOrder={canDeleteOrder} />
                <Pagination meta={report.rows?.meta} routeUrl={routeUrl} filters={filters} />
            </section>
        </AppLayout>
    );
}
