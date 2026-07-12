import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

import {
    PushsaleDateRange,
    PushsalePager,
    PushsaleSearchButton,
    PushsaleSelect,
    usePushsaleFilters,
} from '@/components/reports/PushsaleReportChrome';
import AppLayout from '@/layouts/AppLayout';
import { formatCurrency, formatNumber } from '@/lib/format';

function percent(value) {
    const number = Number(value ?? 0);
    if (!Number.isFinite(number)) return '0 %';
    return `${Number.isInteger(number) ? number : number.toFixed(2)} %`;
}

function totalRows(rows) {
    const parents = rows.filter((row) => !row.isChild);
    const sum = (key) => parents.reduce((total, row) => total + Number(row[key] ?? 0), 0);
    const contacts = sum('contacts');
    const interactions = sum('interactions');
    const closedOrders = sum('closedOrders');
    const productQuantity = sum('productQuantity');
    const totalRevenue = sum('totalRevenue');
    const revenueAfterDiscount = sum('revenueAfterDiscount');
    const budget = sum('budget');

    return {
        budget,
        interactions,
        contacts,
        contactRate: interactions > 0 ? (contacts / interactions) * 100 : 0,
        costPerContact: contacts > 0 ? budget / contacts : 0,
        closedOrders,
        closingRate: contacts > 0 ? (closedOrders / contacts) * 100 : 0,
        productQuantity,
        avgProductPerOrder: closedOrders > 0 ? productQuantity / closedOrders : 0,
        totalRevenue,
        revenueAfterDiscount,
        budgetRevenueRatio: totalRevenue > 0 ? (budget / totalRevenue) * 100 : 0,
        budgetNetRevenueRatio: revenueAfterDiscount > 0 ? (budget / revenueAfterDiscount) * 100 : 0,
    };
}

function MetricFill({ value, max, tone }) {
    const width = max > 0 ? Math.min(100, Math.max(0, (Number(value ?? 0) / max) * 100)) : 0;
    return (
        <span className={`ps-metric-fill ps-metric-fill-${tone}`}>
            <span style={{ width: `${width}%` }} />
            <b>{formatNumber(value ?? 0)}</b>
        </span>
    );
}

function MarketingTable({ rows, filterTotal, pageTotal }) {
    const maxRevenue = Math.max(...rows.map((row) => Number(row.totalRevenue ?? 0)), 1);
    const maxNetRevenue = Math.max(...rows.map((row) => Number(row.revenueAfterDiscount ?? 0)), 1);
    const renderTotal = (label, total) => (
        <tr className="ps-total-row" key={label}>
            <td colSpan={6}>{label}:</td>
            <td>{formatNumber(total.budget)}</td>
            <td>{formatNumber(total.interactions)}</td>
            <td>{formatNumber(total.contacts)}</td>
            <td>{total.interactions ? percent(total.contactRate) : '∞ %'}</td>
            <td>{formatNumber(total.costPerContact)}</td>
            <td>{formatNumber(total.closedOrders)}</td>
            <td>{percent(total.closingRate)}</td>
            <td>{formatNumber(total.productQuantity)}</td>
            <td>{Number(total.avgProductPerOrder ?? 0).toFixed(2)}</td>
            <td>{formatNumber(total.totalRevenue)}</td>
            <td>{formatNumber(total.revenueAfterDiscount)}</td>
            <td>{percent(total.budgetRevenueRatio)}</td>
            <td>{percent(total.budgetNetRevenueRatio)}</td>
            <td />
        </tr>
    );

    return (
        <div className="ps-table-scroll ps-marketing-table-wrap">
            <table className="ps-table ps-marketing-table">
                <thead>
                    <tr className="ps-group-head">
                        <th colSpan={6}>THÔNG TIN NGUỒN DỮ LIỆU</th>
                        <th colSpan={14}>THÔNG TIN HIỆU QUẢ MARKETING</th>
                    </tr>
                    <tr>
                        <th>STT</th>
                        <th>Tên Nguồn dữ liệu</th>
                        <th>Sản phẩm</th>
                        <th>Kênh quảng cáo</th>
                        <th>UTM Source</th>
                        <th>UTM<br />Campaign</th>
                        <th>Ngân sách (1)</th>
                        <th>Số tương tác<br />(2)</th>
                        <th>Số contact<br />(3)</th>
                        <th>Tỷ lệ<br />contact/tương<br />tác (4=3/2)<br />(%)</th>
                        <th>Giá contact<br />(5=1/3)</th>
                        <th>Chốt đơn<br />(6)</th>
                        <th>Tỷ lệ chốt<br />đơn (7=6/3)<br />(%)</th>
                        <th>Số sản phẩm<br />(8)</th>
                        <th>Sản phẩm/<br />đơn (9=8/6)<br />(%)</th>
                        <th>Doanh số<br />(10=8*Giá SP)</th>
                        <th>Doanh số sau<br />CK (11)</th>
                        <th>NS/Doanh số<br />(12=1/10) (%)</th>
                        <th>NS/Doanh số trừ<br />CK (13=1/(11)) (%)</th>
                        <th>Biểu đồ</th>
                    </tr>
                </thead>
                <tbody>
                    {renderTotal('Tổng bộ lọc', filterTotal)}
                    {renderTotal('Tổng theo trang', pageTotal)}
                    {rows.length === 0 && (
                        <tr><td colSpan={20} className="ps-empty">Không có dữ liệu trong khoảng thời gian đã chọn.</td></tr>
                    )}
                    {rows.map((row, index) => (
                        <tr key={`${row.id}-${row.parentId ?? 'root'}`} className={row.isChild ? 'is-child' : ''}>
                            <td>{row.isChild ? '' : index + 1}</td>
                            <td className="ps-text-left ps-source-cell">
                                <span>{row.sourceName || '—'}</span>
                                {!row.isChild && <i className="fa fa-plus" aria-hidden="true" />}
                            </td>
                            <td className="ps-text-left ps-product-cell">
                                <span>{row.productName || '—'}</span>
                                <i className="fa fa-angle-down" aria-hidden="true" />
                            </td>
                            <td className="ps-text-left">{row.adChannel || '—'}</td>
                            <td>{row.utmSource || ''}</td>
                            <td>{row.utmCampaign || ''}</td>
                            <td>{formatNumber(row.budget)}</td>
                            <td>{formatNumber(row.interactions)}</td>
                            <td>{formatNumber(row.contacts)}</td>
                            <td>{row.interactions ? percent(row.contactRate) : '∞ %'}</td>
                            <td>{formatNumber(row.costPerContact)}</td>
                            <td>{formatNumber(row.closedOrders)}</td>
                            <td className="ps-rate-cell">
                                <span style={{ width: `${Math.max(2, Math.min(50, Number(row.closingRate ?? 0) / 2))}%` }} />
                                <b>{percent(row.closingRate)}</b>
                            </td>
                            <td>{formatNumber(row.productQuantity)}</td>
                            <td>{Number(row.avgProductPerOrder ?? 0).toFixed(2).replace(/\.00$/, '')}</td>
                            <td><MetricFill value={row.totalRevenue} max={maxRevenue} tone="green" /></td>
                            <td><MetricFill value={row.revenueAfterDiscount} max={maxNetRevenue} tone="blue" /></td>
                            <td>{percent(row.budgetRevenueRatio)}</td>
                            <td>{percent(row.budgetNetRevenueRatio)}</td>
                            <td><button type="button" className="ps-chart-button" title="Xem biểu đồ"><i className="fa fa-bar-chart" /></button></td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Dashboard({ filters = {}, filterOptions = {}, report = {}, filterRouteUrl }) {
    const routeUrl = filterRouteUrl ?? '/admin/marketing/dashboard';
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const [collapsed, setCollapsed] = useState(false);
    const [page, setPage] = useState(1);
    const perPage = 10;
    const allRows = report.rows ?? [];
    const totalPages = Math.max(1, Math.ceil(allRows.length / perPage));

    useEffect(() => setPage(1), [allRows]);

    const visibleRows = useMemo(
        () => allRows.slice((page - 1) * perPage, page * perPage),
        [allRows, page],
    );
    const computedFilterTotal = useMemo(() => ({ ...totalRows(allRows), ...(report.filterTotal ?? {}) }), [allRows, report.filterTotal]);
    const computedPageTotal = useMemo(() => totalRows(visibleRows), [visibleRows]);

    const products = filterOptions.products ?? [];
    const marketers = filterOptions.marketingUsers ?? [];
    const teams = filterOptions.teams ?? [];

    return (
        <AppLayout>
            <Head title="Marketing dashboard" />

            <section className="ps-report-page ps-marketing-dashboard">
                <div className="ps-report-topbar">
                    <h1>Marketing dashboard</h1>
                    <div className="ps-topbar-filters">
                        <PushsaleSelect placeholder="--Chọn trưởng nhóm--" options={teams} />
                        <PushsaleSelect placeholder="--Chọn nhóm--" options={teams} />
                        <PushsaleSelect
                            placeholder="--Marketing--"
                            value={draft.marketer_id ?? ''}
                            options={marketers}
                            onChange={(value) => set('marketer_id', value)}
                        />
                    </div>
                    <div className="ps-topbar-actions">
                        <button type="button" className="ps-collapse-filter" onClick={() => setCollapsed((value) => !value)} title="Thu gọn bộ lọc">
                            <i className={`fa ${collapsed ? 'fa-angle-double-down' : 'fa-angle-double-up'}`} />
                        </button>
                        <PushsaleSearchButton onClick={() => apply()} />
                        <button type="button" className="ps-square-button" title="Cấu hình"><i className="fa fa-cog" /></button>
                        <button type="button" className="ps-help-button" title="Trợ giúp"><i className="fa fa-question-circle" /></button>
                    </div>
                </div>

                {!collapsed && (
                    <div className="ps-marketing-filters">
                        <div className="ps-filter-row ps-filter-row-first">
                            <PushsaleSelect placeholder="--Chuẩn Pushsale--" />
                            <PushsaleDateRange filters={draft} onChange={set} />
                            <PushsaleSelect placeholder="Tác nghiệp cần" />
                            <PushsaleSelect placeholder="Khách mới" />
                            <PushsaleSelect placeholder="Có Contact (Hoặc chốt đơn)" />
                            <PushsaleSelect placeholder="--Nguồn dữ liệu--" />
                            <PushsaleSelect placeholder="--Kênh quảng cáo--" />
                        </div>
                        <div className="ps-filter-row ps-filter-row-second">
                            <PushsaleSelect placeholder="--Sản phẩm cha--" options={filterOptions.parentProducts ?? []} />
                            <PushsaleSelect
                                placeholder="-- Sản phẩm --"
                                value={draft.product_id ?? ''}
                                options={products}
                                onChange={(value) => set('product_id', value)}
                            />
                            <input className="ps-control" placeholder="Mã Utm" />
                            <input className="ps-control" placeholder="Tên nguồn dữ liệu" />
                            <PushsaleSelect placeholder="Số contact" />
                            <PushsaleSelect placeholder="1.Doanh số tổng" />
                            <label className="ps-utm-check"><input type="checkbox" /> UTM Nâng cao</label>
                            <Link className="ps-history-link" href="/notifications"><i className="fa fa-history" />Lịch sử hoạt động</Link>
                        </div>
                    </div>
                )}

                <div className="ps-report-table-area">
                    <MarketingTable rows={visibleRows} filterTotal={computedFilterTotal} pageTotal={computedPageTotal} />
                    <PushsalePager current={page} totalPages={totalPages} onPage={setPage} />
                </div>
            </section>
        </AppLayout>
    );
}
