import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { PushsaleExportButton, PushsaleSearchButton, PushsaleSelect } from '@/components/reports/PushsaleReportChrome';
import { RevenueMetricsTable } from '@/components/reports/RevenueMetricsTable';
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

function cleanPayload(values) {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== false),
    );
}

function formatDateLabel(value) {
    if (!value) return '';
    const [year, month, day] = String(value).split('-');
    if (!year || !month || !day) return String(value);
    return `${day}/${month}/${year}`;
}

function DateRangeInline({ draft, setDraft }) {
    const label = `${formatDateLabel(draft.date_from) || '...'} 00:00 - ${formatDateLabel(draft.date_to) || '...'} 23:59`;

    return (
        <div className="ps-sales-revenue-date-range" title="Bấm nửa trái để chọn từ ngày, nửa phải để chọn đến ngày">
            <input
                type="text"
                className="ps-control ps-sales-revenue-date-label"
                value={label}
                readOnly
            />
            <div className="ps-sales-revenue-date-native" aria-hidden="false">
                <input
                    type="date"
                    value={draft.date_from ?? ''}
                    onChange={(event) => setDraft((current) => ({ ...current, date_from: event.target.value }))}
                    aria-label="Từ ngày"
                />
                <input
                    type="date"
                    value={draft.date_to ?? ''}
                    onChange={(event) => setDraft((current) => ({ ...current, date_to: event.target.value }))}
                    aria-label="Đến ngày"
                />
            </div>
        </div>
    );
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
    const [draft, setDraft] = useState(() => normalizeDraft(filters));

    useEffect(() => {
        setDraft(normalizeDraft(filters));
    }, [filters]);

    const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const search = () => router.get(routeUrl, { ...cleanPayload(draft), page: 1 }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });

    const primaryFilters = (
        <div className="ps-sales-revenue-primary">
            <PushsaleSelect
                value={draft.date_type}
                options={filterOptions.dateTypes ?? []}
                placeholder="--Chuẩn Pushsale--"
                onChange={(value) => set('date_type', value)}
            />
            <DateRangeInline draft={draft} setDraft={setDraft} />
            <PushsaleSelect
                value={draft.discount_mode}
                options={filterOptions.discountModes ?? []}
                placeholder="Sau chiết khấu"
                onChange={(value) => set('discount_mode', value)}
            />
            <PushsaleSelect
                value={draft.reconciliation_status}
                options={filterOptions.reconciliationStatuses ?? []}
                placeholder="-- Đối soát --"
                onChange={(value) => set('reconciliation_status', value)}
            />
        </div>
    );

    const advancedFilters = (
        <div className="ps-sales-revenue-advanced">
            <PushsaleSelect
                value={draft.team_leader_id}
                options={filterOptions.teamLeaders ?? []}
                placeholder="--Trưởng nhóm--"
                onChange={(value) => set('team_leader_id', value)}
            />
            <PushsaleSelect
                value={draft.team_id}
                options={filterOptions.salesTeams ?? filterOptions.teams ?? []}
                placeholder="--Chọn nhóm--"
                onChange={(value) => set('team_id', value)}
            />
            <PushsaleSelect
                value={draft.parent_product_id}
                options={filterOptions.parentProducts ?? []}
                placeholder="-- Sản phẩm cha --"
                onChange={(value) => set('parent_product_id', value)}
            />
            <PushsaleSelect
                value={draft.product_id}
                options={filterOptions.products ?? []}
                placeholder="-- Sản phẩm --"
                onChange={(value) => set('product_id', value)}
            />
            <PushsaleSelect
                value={draft.delivery_status}
                options={filterOptions.deliveryStatuses ?? []}
                placeholder="-- Chọn trạng thái giao hàng --"
                onChange={(value) => set('delivery_status', value)}
            />
            <label className="ps-sales-revenue-check">
                <input
                    type="checkbox"
                    checked={Boolean(draft.no_closing_date_limit)}
                    onChange={(event) => set('no_closing_date_limit', event.target.checked)}
                />
                <span>Không giới hạn ngày chốt</span>
            </label>
            <PushsaleSelect
                value={draft.sale_id}
                options={filterOptions.salesUsers ?? []}
                placeholder="--Chọn sale--"
                onChange={(value) => set('sale_id', value)}
            />
            <PushsaleSelect
                value={draft.per_page}
                options={[20, 50, 100].map((value) => ({ value, label: String(value) }))}
                placeholder="20"
                onChange={(value) => set('per_page', value)}
            />
        </div>
    );

    const actions = (
        <>
            <PushsaleSearchButton onClick={search} label="Tìm kiếm" />
            <PushsaleExportButton routeUrl={routeUrl} filters={cleanPayload(draft)} label="Xuất Excel" />
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
