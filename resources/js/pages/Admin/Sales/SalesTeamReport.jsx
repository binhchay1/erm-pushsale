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
import { translateReportText } from '@/lib/reportI18n';
import { useT } from '@/providers/I18nProvider';

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
    const t = useT();
    const title = translateReportText(t, schema?.title ?? 'Báo cáo nhóm sale', schema?.title ?? t('reports.extra.sale-team.title'));
    const tt = (value, fallback = value) => translateReportText(t, value, fallback);
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
                            <PushsaleSearchButton onClick={search} label={t('reports.pushsale.search')} />
                            <PushsaleExportButton routeUrl={routeUrl} filters={queryFilters} label={t('reports.pushsale.export_excel')} />
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
                            <div className="ps-sales-team-card__label">{tt(card.label)}</div>
                            <div className="ps-sales-team-card__value">
                                {formatReportNumber(card.count)}
                                {card.rate !== undefined ? <small> ({formatReportPercent(card.rate)})</small> : null}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="dragscroll1 tableFixHead ps-sales-leader-table-wrap">
                    <table className="table table-bordered table-striped" id="tblData">
                        <thead>
                            <tr>
                                <th className="text-center" rowSpan="2">{t('reports.pushsale.stt')}</th>
                                <th className="text-center" rowSpan="2">{t('reports.pushsale.sale')}</th>
                                <th className="text-center" colSpan="5">{t('reports.ceo_report.new_customers_group')}</th>
                                <th className="text-center" colSpan="5">{t('reports.ceo_report.old_customers_group')}</th>
                                <th className="text-center" colSpan="8">{t('reports.ceo_report.total_group')}</th>
                            </tr>
                            <tr>
                                <th className="text-center">{t('reports.pushsale.contact')}</th>
                                <th className="text-center">{t('reports.pushsale.closed_orders')}</th>
                                <th className="text-center">{t('reports.pushsale.close_rate')}</th>
                                <th className="text-center">{t('reports.pushsale.product_qty')}</th>
                                <th className="text-center">{t('reports.pushsale.expected_revenue')}</th>
                                <th className="text-center">{t('reports.pushsale.contact')}</th>
                                <th className="text-center">{t('reports.pushsale.closed_orders')}</th>
                                <th className="text-center">{t('reports.pushsale.close_rate')}</th>
                                <th className="text-center">{t('reports.pushsale.product_qty')}</th>
                                <th className="text-center">{t('reports.pushsale.expected_revenue')}</th>
                                <th className="text-center">{t('reports.pushsale.expected_revenue')}</th>
                                <th className="text-center">{t('reports.ceo_report.cod_fee')}</th>
                                <th className="text-center">{t('reports.ceo_report.cod_support')}</th>
                                <th className="text-center">{t('reports.ceo_report.discount')}</th>
                                <th className="text-center">{t('reports.ceo_report.deposit')}</th>
                                <th className="text-center">{t('reports.pushsale.actual_revenue')}</th>
                                <th className="text-center">{t('reports.pushsale.target')}</th>
                                <th className="text-center">{t('reports.pushsale.rate')}</th>
                            </tr>
                            {totals && (
                                <tr className="rowsum">
                                    <td colSpan="2" className="text-center font-weight-bold">{t('reports.pushsale.total_colon')}</td>
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
