import { Head } from '@inertiajs/react';
import { useMemo } from 'react';

import { PageHeader } from '@/components/layout/PageHeader';
import { PushsalePagination } from '@/components/pagination/PushsalePagination';
import { ReportFilterField } from '@/components/reports/ReportFilterField';
import { ReportProgressCell } from '@/components/reports/ReportProgressCell';
import { SaleNameCell } from '@/components/reports/SaleNameCell';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import {
    formatReportNumber,
    formatReportPercent,
} from '@/components/reports/reportFormat';
import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsaleSearchButton,
} from '@/components/reports/PushsaleReportChrome';
import { cleanInertiaFilters, readQueryFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout';

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
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.new_contacts)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.new_closed)}</td>
                                    <td className="text-center font-weight-bold">{formatReportPercent(totals.new_rate)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.new_products)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.new_revenue)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.old_contacts)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.old_closed)}</td>
                                    <td className="text-center font-weight-bold">{formatReportPercent(totals.old_rate)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.old_products)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.old_revenue)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.provisional_revenue)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.cod_fee)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.cod_support)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.discount)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.deposit)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.after_discount_revenue)}</td>
                                    <td className="text-center font-weight-bold">{formatReportNumber(totals.kpi_revenue)}</td>
                                    <td className="text-center font-weight-bold">{formatReportPercent(totals.kpi_rate)}</td>
                                </tr>
                            )}
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={`${row.sale_id}-${index}`}>
                                    <td className="text-center">{(pagination.from || 1) + index}</td>
                                    <td><SaleNameCell row={row} /></td>
                                    <ReportProgressCell value={row.new_contacts} fillWhenNoMax />
                                    <ReportProgressCell value={row.new_closed} fillWhenNoMax />
                                    <ReportProgressCell value={row.new_rate} format="percent" />
                                    <ReportProgressCell value={row.new_products} fillWhenNoMax />
                                    <ReportProgressCell value={row.new_revenue} fillWhenNoMax />
                                    <ReportProgressCell value={row.old_contacts} fillWhenNoMax />
                                    <ReportProgressCell value={row.old_closed} fillWhenNoMax />
                                    <ReportProgressCell value={row.old_rate} format="percent" />
                                    <ReportProgressCell value={row.old_products} fillWhenNoMax />
                                    <ReportProgressCell value={row.old_revenue} fillWhenNoMax />
                                    <ReportProgressCell value={row.provisional_revenue} fillWhenNoMax />
                                    <ReportProgressCell value={row.cod_fee} fillWhenNoMax />
                                    <ReportProgressCell value={row.cod_support} fillWhenNoMax />
                                    <ReportProgressCell value={row.discount} fillWhenNoMax />
                                    <ReportProgressCell value={row.deposit} fillWhenNoMax />
                                    <ReportProgressCell value={row.after_discount_revenue} fillWhenNoMax />
                                    <ReportProgressCell value={row.kpi_revenue} fillWhenNoMax />
                                    <ReportProgressCell value={row.kpi_rate} format="percent" />
                                </tr>
                            ))}
                            {!rows.length && <TableEmptyRow colSpan={20} />}
                        </tbody>
                    </table>
                </div>

                <PushsalePagination meta={pagination} routeUrl={routeUrl} filters={queryFilters} itemLabel="sale" perPageOptions={[20, 50, 100, 200, 500, 1000]} />
            </div>
        </AppLayout>
    );
}
