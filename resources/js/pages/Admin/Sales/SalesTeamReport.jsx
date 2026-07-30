import { Head } from '@inertiajs/react';
import { useMemo } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { ReportFilterField } from '@/components/reports/ReportFilterField';
import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsaleSearchButton,
} from '@/components/reports/PushsaleReportChrome';
import { cleanInertiaFilters, readQueryFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout';

const numberFormatter = new Intl.NumberFormat('vi-VN');

function todayIso() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function buildInitialFilters() {
    return readQueryFilters({
        date_type: 'sale_received_data',
        date_from: todayIso(),
        date_to: todayIso(),
        discount_mode: 'after_discount',
        delivery_status: '',
        sale_leader_id: '',
        sale_team_id: '',
        parent_product_id: '',
        product_id: '',
        reconciliation_status: '',
        per_page: '20',
    });
}

function num(value) {
    return numberFormatter.format(Number(value) || 0);
}

function pct(value) {
    if (value === null || value === undefined || Number.isNaN(Number(value))) return '∞ %';
    const numeric = Number(value);
    return `${Number.isInteger(numeric) ? numeric : numeric.toFixed(2)} %`;
}

function ProgressCell({ value, format = 'number' }) {
    const display = format === 'percent' ? pct(value) : num(value);
    const width = format === 'percent' ? Math.min(100, Math.max(0, Number(value) || 0)) : 100;
    return (
        <td className="tdProgress">
            <div className="box-progress">
                <div className="progress">
                    <div className="progress-bar" style={{ width: `${width}%` }} />
                </div>
                <span className="progress-text">{display}</span>
            </div>
        </td>
    );
}

function SaleName({ row }) {
    const sale = String(row.sale ?? '').trim() || 'Chưa phân sale';
    const account = String(row.sale_account ?? '').trim();
    return (
        <span>
            {sale}
            {account ? <small> ({account})</small> : null}
        </span>
    );
}

export default function SalesTeamReport({
    schema,
    rows = [],
    pagination = {},
    summary = {},
    filterOptions = {},
    routeUrl = '/admin/sales/reports/teams',
    pageRuntimeError = null,
}) {
    const title = schema?.title ?? 'Báo cáo nhóm sale';
    const { draft, set, apply: search } = useInertiaFilters(routeUrl, buildInitialFilters(), {
        sync: false,
        clean: true,
    });
    const queryFilters = useMemo(() => cleanInertiaFilters(draft), [draft]);
    const totals = summary?.totals || null;
    const cards = Array.isArray(summary?.delivery_cards) ? summary.delivery_cards : [];

    return (
        <AppLayout activeMenuCode="4.6.3">
            <Head title={title} />
            <div className="pushsale-page ps-sales-leader-report ps-sales-team-report" data-page-code="4.6.3">
                {pageRuntimeError && (
                    <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div>
                )}

                <PageHeader
                    title={title}
                    pageCode="4.6.3"
                    className="ps-sales-leader-header"
                    defaultCollapsed={false}
                    filters={(
                        <div className="ps-sales-leader-primary">
                            <ReportFilterField field="date_type" draft={draft} onChange={set} filterOptions={filterOptions} />
                            <PushsaleDateRange filters={draft} onChange={set} />
                            <ReportFilterField field="discount_mode" draft={draft} onChange={set} filterOptions={filterOptions} />
                            <ReportFilterField field="delivery_status" draft={draft} onChange={set} filterOptions={filterOptions} />
                        </div>
                    )}
                    actions={(
                        <>
                            <PushsaleSearchButton onClick={search} label="Tìm kiếm" />
                            <PushsaleExportButton routeUrl={routeUrl} filters={queryFilters} label="Xuất Excel" />
                        </>
                    )}
                    advanced={(
                        <div className="ps-sales-leader-advanced ps-adv-filter-panel">
                            <div className="ps-adv-filter-row" style={{ '--ps-adv-cols': 6 }}>
                                <ReportFilterField field="sale_leader_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="sale_team_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="parent_product_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="product_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="reconciliation_status" draft={draft} onChange={set} filterOptions={filterOptions} />
                                <ReportFilterField field="per_page" draft={draft} onChange={set} filterOptions={filterOptions} />
                            </div>
                        </div>
                    )}
                />

                <div className="ps-sales-team-cards">
                    {cards.map((card) => (
                        <div key={card.key} className={`ps-sales-team-card tone-${card.tone}`}>
                            <div className="ps-sales-team-card__label">{card.label}</div>
                            <div className="ps-sales-team-card__value">
                                {num(card.count)}
                                {card.rate !== undefined ? <small> ({pct(card.rate)})</small> : null}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="dragscroll1 tableFixHead ps-sales-leader-table-wrap">
                    <table className="table table-bordered table-striped" id="tblData">
                        <thead>
                            <tr>
                                <th className="text-center" rowSpan="2">STT</th>
                                <th className="text-center" rowSpan="2">SALE</th>
                                <th className="text-center" colSpan="5">KHÁCH HÀNG MỚI</th>
                                <th className="text-center" colSpan="5">KHÁCH HÀNG CŨ</th>
                                <th className="text-center" colSpan="8">TỔNG CHUNG</th>
                            </tr>
                            <tr>
                                <th className="text-center">Contact</th>
                                <th className="text-center">Chốt đơn</th>
                                <th className="text-center">Tỷ lệ chốt (%)</th>
                                <th className="text-center">Số sản phẩm</th>
                                <th className="text-center">Doanh số tạm tính</th>
                                <th className="text-center">Contact</th>
                                <th className="text-center">Chốt đơn</th>
                                <th className="text-center">Tỷ lệ chốt (%)</th>
                                <th className="text-center">Số sản phẩm</th>
                                <th className="text-center">Doanh số tạm tính</th>
                                <th className="text-center">Doanh số tạm tính</th>
                                <th className="text-center">Phí COD</th>
                                <th className="text-center">Hỗ trợ COD</th>
                                <th className="text-center">CK</th>
                                <th className="text-center">Đặt cọc</th>
                                <th className="text-center">Doanh số tạm tính sau chiết khấu</th>
                                <th className="text-center">KPI doanh số</th>
                                <th className="text-center">Tỷ lệ (%)</th>
                            </tr>
                            {totals && (
                                <tr className="rowsum">
                                    <td colSpan="2" className="text-center font-weight-bold">Tổng:</td>
                                    <td className="text-center font-weight-bold">{num(totals.new_contacts)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.new_closed)}</td>
                                    <td className="text-center font-weight-bold">{pct(totals.new_rate)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.new_products)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.new_revenue)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.old_contacts)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.old_closed)}</td>
                                    <td className="text-center font-weight-bold">{pct(totals.old_rate)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.old_products)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.old_revenue)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.provisional_revenue)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.cod_fee)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.cod_support)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.discount)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.deposit)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.after_discount_revenue)}</td>
                                    <td className="text-center font-weight-bold">{num(totals.kpi_revenue)}</td>
                                    <td className="text-center font-weight-bold">{pct(totals.kpi_rate)}</td>
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={`${row.sale_id}-${index}`}>
                                    <td className="text-center">{(pagination.from || 1) + index}</td>
                                    <td><SaleName row={row} /></td>
                                    <ProgressCell value={row.new_contacts} />
                                    <ProgressCell value={row.new_closed} />
                                    <ProgressCell value={row.new_rate} format="percent" />
                                    <ProgressCell value={row.new_products} />
                                    <ProgressCell value={row.new_revenue} />
                                    <ProgressCell value={row.old_contacts} />
                                    <ProgressCell value={row.old_closed} />
                                    <ProgressCell value={row.old_rate} format="percent" />
                                    <ProgressCell value={row.old_products} />
                                    <ProgressCell value={row.old_revenue} />
                                    <ProgressCell value={row.provisional_revenue} />
                                    <ProgressCell value={row.cod_fee} />
                                    <ProgressCell value={row.cod_support} />
                                    <ProgressCell value={row.discount} />
                                    <ProgressCell value={row.deposit} />
                                    <ProgressCell value={row.after_discount_revenue} />
                                    <ProgressCell value={row.kpi_revenue} />
                                    <ProgressCell value={row.kpi_rate} format="percent" />
                                </tr>
                            ))}
                            {!rows.length && (
                                <tr><td colSpan={20} className="text-center">Chưa có dữ liệu phù hợp với bộ lọc.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={queryFilters} itemLabel="sale" perPageOptions={[20, 50, 100, 200, 500, 1000]} />
            </div>
        </AppLayout>
    );
}
