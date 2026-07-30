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

const formulaRows = [
    ['(1) Đơn chốt = ', 'Đơn chốt'],
    ['(2) Xác nhận giao hàng = ', '(1) - [Chờ vận đơn] - [Hoãn giao hàng] - [Hủy vận đơn]'],
    ['(3) Huỷ vận đơn = ', '[Huỷ vận đơn]'],
    ['(4) Tổng giao = ', '(1) - [Chờ vận đơn] - [Giao ngay] - [Hoãn giao hàng] - [Hủy vận đơn] - [Hủy đăng đơn] - [Không lấy được hàng]'],
    ['(5) Đã hoàn = ', '[Đã hoàn]'],
    ['(6) Đang hoàn = ', '[Đang hoàn]'],
    ['(7) Đã giao hàng = ', '[Đã giao hàng]'],
    ['(8) Đã thanh toán = ', '[Đã thanh toán]'],
    ['(9) Giao thành công = ', '[Đã giao hàng] + [Đã thanh toán] + [Giao hàng 1 phần]'],
    ['(10) % Đã hoàn = ', '(5) / (4)'],
    ['(11) % Huỷ VĐ = ', '(3) / (1)'],
    ['(12) % XNGH = ', '(2) / (1)'],
    ['(13) % Giao thành công = ', '(9) / (4)'],
    ['(14) Contact: ', 'Số contact'],
    ['(15) Tỷ lệ chốt = ', 'Số lượng đơn chốt / Số contact'],
    ['(16) Số sản phẩm = ', 'Số sản phẩm đơn chốt'],
    ['Upsale = ', 'Sản phẩm upsale nằm trong đơn chốt; doanh số upsale tính riêng và vẫn cộng vào doanh số đơn tổng'],
    ['(17) Giá trị đơn = ', 'Doanh số đơn chốt / Số lượng đơn chốt'],
    ['(18) % doanh số hoàn = ', '(doanh số đã hoàn / Xác nhận giao hàng) * 100%'],
    ['(19) % Doanh số huỷ = ', '((Doanh số huỷ vận đơn + Doanh số huỷ đăng đơn) / Doanh số đơn chốt) * 100%'],
];

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
    return (
        <div className="ps-sales-revenue-formulas">
            {formulaRows.map(([label, text]) => (
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
            <PushsaleSearchButton onClick={search} label="Tìm kiếm" />
            <PushsaleExportButton routeUrl={routeUrl} filters={cleanInertiaFilters(draft)} label="Xuất Excel" />
        </>
    );

    return (
        <AppLayout>
            <Head title={t('reports.revenue_sales.title')} />

            <PushsalePageShell
                title="Báo cáo doanh số chi tiết sale"
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
                    nameLabel="TÊN SALE"
                />
            </PushsalePageShell>
        </AppLayout>
    );
}
