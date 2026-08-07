import { Head } from '@inertiajs/react';
import { useMemo } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { ReportFilterField } from '@/components/reports/ReportFilterField';
import { PushsaleDateRange, PushsaleExportButton, PushsaleSearchButton } from '@/components/reports/PushsaleReportChrome';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';
import { cleanInertiaFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout';
import { useT } from '@/providers/I18nProvider';

const routeUrl = '/admin/sales/revenue';

function formulaRows(t) {
    return [
        [t('reports.revenue_formula.closed_orders_label'), t('reports.revenue_formula.closed_orders_text')],
        [t('reports.revenue_formula.confirmed_delivery_label'), t('reports.revenue_formula.confirmed_delivery_text')],
        [t('reports.revenue_formula.canceled_shipping_label'), t('reports.revenue_formula.canceled_shipping_text')],
        [t('reports.revenue_formula.transferred_carrier_label'), t('reports.revenue_formula.transferred_carrier_text')],
        [t('reports.revenue_formula.returned_label'), t('reports.revenue_formula.returned_text')],
        [t('reports.revenue_formula.returning_label'), t('reports.revenue_formula.returning_text')],
        [t('reports.revenue_formula.delivered_label'), t('reports.revenue_formula.delivered_text')],
        [t('reports.revenue_formula.paid_label'), t('reports.revenue_formula.paid_text')],
        [t('reports.revenue_formula.successful_delivery_label'), t('reports.revenue_formula.successful_delivery_text')],
        [t('reports.revenue_formula.return_rate_label'), t('reports.revenue_formula.return_rate_text')],
        [t('reports.revenue_formula.shipping_cancel_rate_label'), t('reports.revenue_formula.shipping_cancel_rate_text')],
        [t('reports.revenue_formula.confirm_rate_label'), t('reports.revenue_formula.confirm_rate_text')],
        [t('reports.revenue_formula.success_rate_label'), t('reports.revenue_formula.success_rate_text')],
        [t('reports.revenue_formula.contact_label'), t('reports.revenue_formula.contact_text')],
        [t('reports.revenue_formula.close_rate_label'), t('reports.revenue_formula.close_rate_text')],
        [t('reports.revenue_formula.product_count_label'), t('reports.revenue_formula.product_count_text')],
        [t('reports.revenue_formula.upsale_label'), t('reports.revenue_formula.upsale_text')],
        [t('reports.revenue_formula.average_order_label'), t('reports.revenue_formula.average_order_text')],
        [t('reports.revenue_formula.revenue_return_rate_label'), t('reports.revenue_formula.revenue_return_rate_text')],
        [t('reports.revenue_formula.revenue_cancel_rate_label'), t('reports.revenue_formula.revenue_cancel_rate_text')],
    ];
}

function normalizeDraft(filters = {}) {
    return {
        date_type: filters.date_type ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        discount_mode: filters.discount_mode ?? 'after_discount',
        reconciliation_status: filters.reconciliation_status ?? '',
        team_leader_id: filters.team_leader_id ?? '',
        team_id: filters.team_id ?? '',
        parent_product_id: filters.parent_product_id ?? '',
        product_id: filters.product_id ?? '',
        delivery_status: filters.delivery_status ?? '',
        sale_id: filters.sale_id ?? '',
        per_page: filters.per_page ?? 20,
        no_closing_date_limit: filters.no_closing_date_limit ?? false,
    };
}

function FormulaLegend() {
    const t = useT();
    return (
        <div className="ps-sales-revenue-formulas">
            {formulaRows(t).map(([label, text]) => (
                <div className="ps-sales-revenue-formula" key={label}>
                    <span>{label}</span>{text}
                </div>
            ))}
        </div>
    );
}

export default function SaleRevenueReport({ filters, filterOptions = {}, report }) {
    const t = useT();
    const normalized = useMemo(() => normalizeDraft(filters), [filters]);
    const { draft, set, apply } = useInertiaFilters(routeUrl, normalized, { clean: true });
    const search = () => apply();

    const primaryFilters = (
        <div className="ps-sales-revenue-primary">
            <ReportFilterField field="date_type" draft={draft} onChange={set} filterOptions={filterOptions} />
            <PushsaleDateRange filters={draft} onChange={set} className="ps-sales-revenue-date-range" />
            <ReportFilterField field="discount_mode" draft={draft} onChange={set} filterOptions={filterOptions} />
            <ReportFilterField field="reconciliation_status" draft={draft} onChange={set} filterOptions={filterOptions} />
        </div>
    );

    const advancedFilters = (
        <div className="ps-sales-revenue-advanced-wrap ps-adv-filter-panel">
            <div className="ps-sales-revenue-advanced ps-adv-filter-row" style={{ '--ps-adv-cols': 4 }}>
                <ReportFilterField field="team_leader_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                <ReportFilterField field="team_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                <ReportFilterField field="parent_product_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                <ReportFilterField field="product_id" draft={draft} onChange={set} filterOptions={filterOptions} />
            </div>
            <div className="ps-sales-revenue-advanced ps-adv-filter-row" style={{ '--ps-adv-cols': 4 }}>
                <ReportFilterField field="delivery_status" draft={draft} onChange={set} filterOptions={filterOptions} />
                <ReportFilterField field="sale_id" draft={draft} onChange={set} filterOptions={filterOptions} />
                <ReportFilterField field="per_page" draft={draft} onChange={set} filterOptions={filterOptions} />
                <ReportFilterField field="no_closing_date_limit" draft={draft} onChange={set} filterOptions={filterOptions} className="ps-sales-revenue-check" />
            </div>
        </div>
    );

    const actions = (
        <>
            <PushsaleSearchButton onClick={search} />
            <PushsaleExportButton routeUrl={routeUrl} filters={cleanInertiaFilters(draft)} />
        </>
    );

    return (
        <AppLayout>
            <Head title={t('reports.revenue_sales.title')} />

            <PushsalePageShell
                title={t('reports.revenue_sales.detail_title')}
                className="ps-sales-revenue-page ps-report-toolbar-shell"
                headerClassName="ps-sales-revenue-header"
                bodyClassName="ps-sales-revenue-body"
                primaryFilters={primaryFilters}
                advancedFilters={advancedFilters}
                actions={actions}
            >
                <FormulaLegend />
                <RevenueMetricsTable
                    rows={report?.rows ?? []}
                    nameKey="saleName"
                    nameLabel={t('reports.revenue_sales.name_label')}
                />
            </PushsalePageShell>
        </AppLayout>
    );
}
