import { Head } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';

import {
    PushsaleActionMenu,
    PushsaleDateRange,
    PushsaleExportButton,
    PushsalePager,
    PushsaleSearchButton,
    PushsaleSelect,
    usePushsaleFilters,
} from '@/components/reports/PushsaleReportChrome';
import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { useLabels } from '@/hooks/use-labels';
import AppLayout from '@/layouts/AppLayout';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function psText(t, key, fallback) {
    const path = `reports.pushsale.${key}`;
    const translated = t(path);
    return translated !== path ? translated : fallback;
}

function reportText(t, key, field, fallback) {
    const path = `reports.extra.${key}.${field}`;
    const translated = t(path);
    return translated !== path ? translated : fallback;
}

function resolveColumnLabel(col, t, labels) {
    if (col.label_type === 'operation_stage' && col.label_key) {
        return labels.operation_stage?.[col.label_key] ?? col.label;
    }
    if (col.label_key) {
        const translated = t(`reports.columns.${col.label_key}`);
        if (translated !== `reports.columns.${col.label_key}`) return translated;
    }
    return col.label;
}

function formatCell(value, format) {
    if (value === null || value === undefined || value === '') return '';
    if (format === 'currency') return formatCurrency(value);
    if (format === 'number') return formatNumber(value);
    if (format === 'percent') {
        const number = Number(value);
        return `${Number.isInteger(number) ? number : number.toFixed(2)}%`;
    }
    return value;
}

function PER_PAGE_OPTIONS(t) {
    return [20, 50, 100, 200, 500, 1000, 999999].map((value) => ({
        value: String(value),
        label: value === 999999 ? psText(t, 'per_page_all', '--Hiển thị tất--') : String(value),
    }));
}

function hasValue(value) {
    return value !== null && value !== undefined && value !== '' && value !== false;
}

function cleanReportPayload(values = {}) {
    return Object.fromEntries(Object.entries(values).filter(([, value]) => hasValue(value)));
}

function ReportField({ field, draft, set, filterOptions, t }) {
    switch (field) {
        case 'date_type':
            return <PushsaleSelect placeholder={psText(t, 'date_standard', '--Chuẩn Pushsale--')} value={draft.date_type ?? ''} options={filterOptions.dateTypes ?? []} onChange={(value) => set('date_type', value)} />;
        case 'date_from':
            return <PushsaleDateRange filters={draft} onChange={set} />;
        case 'date_to':
            return null;
        case 'parent_product_id':
            return <PushsaleSelect placeholder={psText(t, 'parent_product_placeholder', '--Sản phẩm cha--')} value={draft.parent_product_id ?? ''} options={filterOptions.parentProducts ?? []} onChange={(value) => set('parent_product_id', value)} />;
        case 'product_id':
            return <PushsaleSelect placeholder={psText(t, 'choose_product', '-- Sản phẩm --')} value={draft.product_id ?? ''} options={filterOptions.products ?? []} onChange={(value) => set('product_id', value)} />;
        case 'discount_mode':
            return <PushsaleSelect placeholder={psText(t, 'discount_after', 'Sau chiết khấu')} value={draft.discount_mode ?? 'after_discount'} options={filterOptions.discountModes ?? []} onChange={(value) => set('discount_mode', value)} />;
        case 'customer_type':
            return <PushsaleSelect placeholder={psText(t, 'all_customers', '-- Tất cả --')} value={draft.customer_type ?? ''} options={filterOptions.customerTypes ?? []} onChange={(value) => set('customer_type', value)} />;
        case 'delivery_status':
            return <PushsaleSelect placeholder={psText(t, 'delivery_status_placeholder', '-- Trạng thái giao hàng --')} value={draft.delivery_status ?? ''} options={filterOptions.deliveryStatuses ?? []} onChange={(value) => set('delivery_status', value)} />;
        case 'reconciliation_status':
            return <PushsaleSelect placeholder={psText(t, 'reconciliation_placeholder', '-- Đối soát --')} value={draft.reconciliation_status ?? ''} options={filterOptions.reconciliationStatuses ?? []} onChange={(value) => set('reconciliation_status', value)} />;
        case 'warehouse_id':
            return <PushsaleSelect placeholder={psText(t, 'choose_warehouse', '--Chọn kho--')} value={draft.warehouse_id ?? ''} options={filterOptions.warehouses ?? []} onChange={(value) => set('warehouse_id', value)} />;
        case 'team_leader_id':
            return <PushsaleSelect placeholder={psText(t, 'choose_team_leader', '--Trưởng nhóm--')} value={draft.team_leader_id ?? ''} options={filterOptions.teamLeaders ?? []} onChange={(value) => set('team_leader_id', value)} />;
        case 'marketing_team_leader_id':
            return <PushsaleSelect placeholder={psText(t, 'choose_team_leader', '--Trưởng nhóm--')} value={draft.marketing_team_leader_id ?? ''} options={filterOptions.marketingTeamLeaders ?? []} onChange={(value) => set('marketing_team_leader_id', value)} />;
        case 'team_id':
            return <PushsaleSelect placeholder={psText(t, 'choose_sales_team', '--Chọn nhóm--')} value={draft.team_id ?? ''} options={filterOptions.salesTeams ?? filterOptions.teams ?? []} onChange={(value) => set('team_id', value)} />;
        case 'sale_id':
            return <PushsaleSelect placeholder={psText(t, 'choose_sale', '--Chọn sale--')} value={draft.sale_id ?? ''} options={filterOptions.salesUsers ?? []} onChange={(value) => set('sale_id', value)} />;
        case 'marketer_id':
            return <PushsaleSelect placeholder={psText(t, 'choose_marketing', '--Marketing--')} value={draft.marketer_id ?? ''} options={filterOptions.marketingUsers ?? []} onChange={(value) => set('marketer_id', value)} />;
        case 'marketing_team_id':
            return <PushsaleSelect placeholder={psText(t, 'choose_marketing_team', '--Nhóm marketing--')} value={draft.marketing_team_id ?? ''} options={filterOptions.marketingTeams ?? []} onChange={(value) => set('marketing_team_id', value)} />;
        case 'per_page':
            return <PushsaleSelect placeholder="20" value={String(draft.per_page ?? '20')} options={PER_PAGE_OPTIONS(t)} onChange={(value) => set('per_page', value)} />;
        case 'search':
            return <input className="ps-control" value={draft.search ?? ''} placeholder={psText(t, 'search_placeholder', 'Từ khóa tìm kiếm')} onChange={(event) => set('search', event.target.value)} />;
        case 'no_closing_date_limit':
            return <label className="ps-report-check"><input type="checkbox" checked={Boolean(draft.no_closing_date_limit)} onChange={(event) => set('no_closing_date_limit', event.target.checked ? 1 : 0)} /><span>{psText(t, 'no_closing_date_limit', 'Không giới hạn ngày chốt')}</span></label>;
        default:
            return null;
    }
}

function PushsaleReportToolbar({ title, routeUrl, filters, filterOptions, filterFields = [], className = '', primary = [], advanced = [], actionsExtra = null, exportLabel = null }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    const render = (field) => (fields.has(field) ? <ReportField key={field} field={field} draft={draft} set={set} filterOptions={filterOptions} t={t} /> : null);
    const visiblePrimary = primary.filter((field) => fields.has(field));
    const visibleAdvanced = advanced.filter((field) => fields.has(field));

    const primaryFilters = visiblePrimary.length > 0 ? (
        <div className="ps-report-v2-primary ps-report-toolbar-controls">
            {visiblePrimary.map(render)}
        </div>
    ) : null;

    const advancedFilters = visibleAdvanced.length > 0 ? (
        <div className="ps-report-v2-advanced ps-report-toolbar-controls">
            {visibleAdvanced.map(render)}
        </div>
    ) : null;

    return (
        <PushsalePageShell
            title={title}
            className={`ps-report-toolbar-shell ps-extra-toolbar ps-report-v2-toolbar ${className}`.trim()}
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            actions={(
                <div className="ps-report-toolbar-actions">
                    <PushsaleSearchButton onClick={() => apply()} />
                    <PushsaleExportButton routeUrl={routeUrl} filters={cleanReportPayload(draft)} label={exportLabel} />
                    {actionsExtra}
                </div>
            )}
        />
    );
}

function CommonToolbar({ title, routeUrl, filters, filterOptions, filterFields = [], compact = false }) {
    return (
        <PushsaleReportToolbar
            title={title}
            routeUrl={routeUrl}
            filters={filters}
            filterOptions={filterOptions}
            filterFields={filterFields}
            className={compact ? 'is-compact' : ''}
            primary={['date_type', 'date_from', 'date_to', 'warehouse_id', 'sale_id', 'marketer_id', 'search']}
            advanced={['team_leader_id', 'team_id', 'marketing_team_id', 'parent_product_id', 'product_id', 'delivery_status', 'discount_mode', 'reconciliation_status', 'per_page', 'no_closing_date_limit']}
        />
    );
}

const SALE_STAGES = [
    ['new_customer', 'new_customer', 'Gọi lần 1'],
    ['call_2', 'call_2', 'Gọi lần 2'],
    ['call_3', 'call_3', 'Gọi lần 3'],
    ['call_4', 'call_4', 'Gọi lần 4'],
    ['call_5', 'call_5', 'Gọi lần 5'],
    ['call_6', 'call_6', 'Gọi lần 6'],
    ['care_1', 'care_1', 'Chăm sóc lần 1'],
    ['care_2', 'care_2', 'Chăm sóc lần 2'],
    ['care_3', 'care_3', 'Chăm sóc lần 3'],
    ['skipped', 'skipped', 'Bỏ qua'],
];

function SaleWorkReport({ title, rows, totals, filters, filterOptions, filterFields, routeUrl }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    const [pageSize, setPageSize] = useState('50');
    const [page, setPage] = useState(1);
    const operationOptions = SALE_STAGES.map(([value, key, label]) => ({ value, label: psText(t, key, label) }));

    const totalWithUntouched = useMemo(() => {
        const base = { ...(totals ?? {}) };
        SALE_STAGES.forEach(([key]) => {
            base[`stage_${key}_untouched`] = rows.reduce(
                (sum, row) => sum + Number(row[`stage_${key}_untouched`] ?? 0),
                0,
            );
        });
        return base;
    }, [rows, totals]);

    const perPage = Number(pageSize) || 50;
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
    const safePage = Math.min(page, totalPages);
    const visibleRows = rows.slice((safePage - 1) * perPage, safePage * perPage);
    const fromRow = rows.length === 0 ? 0 : (safePage - 1) * perPage + 1;
    const toRow = Math.min(rows.length, safePage * perPage);

    const submit = () => {
        setPage(1);
        apply();
    };

    return (
        <section className="ps-report-page ps-sale-work-report">
            <div className="ps-report-topbar ps-extra-toolbar ps-sale-work-toolbar">
                <h1>{title}</h1>
                <div className="ps-extra-toolbar-controls">
                    {fields.has('date_type') && (
                        <PushsaleSelect
                            placeholder={psText(t, 'date_sale_received', 'Ngày sale nhận data')}
                            value={draft.date_type ?? ''}
                            options={filterOptions.dateTypes ?? []}
                            onChange={(value) => set('date_type', value)}
                        />
                    )}
                    <PushsaleDateRange filters={draft} onChange={set} />
                    {fields.has('operation_stage') && (
                        <PushsaleSelect
                            placeholder={psText(t, 'choose_operation', 'Chọn tác nghiệp')}
                            value={draft.operation_stage ?? ''}
                            options={operationOptions}
                            onChange={(value) => set('operation_stage', value)}
                        />
                    )}
                    {fields.has('product_id') && (
                        <PushsaleSelect
                            placeholder={psText(t, 'choose_product', '--Chọn sản phẩm--')}
                            value={draft.product_id ?? ''}
                            options={filterOptions.products ?? []}
                            onChange={(value) => set('product_id', value)}
                        />
                    )}
                    <div className="ps-topbar-actions">
                        <button type="button" className="ps-collapse-filter" title={psText(t, 'collapse_filter', 'Thu gọn bộ lọc')} aria-label={psText(t, 'collapse_filter', 'Thu gọn bộ lọc')}>
                            <i className="fa fa-angle-double-up" aria-hidden="true" />
                        </button>
                        <PushsaleSearchButton onClick={submit} />
                        <PushsaleExportButton routeUrl={routeUrl} filters={draft} />
                    </div>
                </div>
            </div>
            <div className="ps-sale-work-secondary">
                {fields.has('sale_id') && (
                    <PushsaleSelect
                        placeholder={psText(t, 'choose_sale', '--Chọn sale--')}
                        value={draft.sale_id ?? ''}
                        options={filterOptions.salesUsers ?? []}
                        onChange={(value) => set('sale_id', value)}
                    />
                )}
                {fields.has('team_id') && (
                    <PushsaleSelect
                        placeholder="--Chọn nhóm sale--"
                        value={draft.team_id ?? ''}
                        options={filterOptions.salesTeams ?? filterOptions.teams ?? []}
                        onChange={(value) => set('team_id', value)}
                    />
                )}
                <PushsaleSelect
                    placeholder="50"
                    value={pageSize}
                    options={[
                        { value: '50', label: '50' },
                        { value: '100', label: '100' },
                        { value: '200', label: '200' },
                    ]}
                    onChange={(value) => { setPageSize(value); setPage(1); }}
                />
            </div>
            {totalPages > 1 ? (
                <div className="ps-grid-count ps-grid-count-top">
                    {fromRow} - {toRow} / {rows.length}
                    <button type="button" disabled={safePage <= 1} onClick={() => setPage(Math.max(1, safePage - 1))}>‹</button>
                    <button type="button" disabled={safePage >= totalPages} onClick={() => setPage(Math.min(totalPages, safePage + 1))}>›</button>
                </div>
            ) : null}
            <div className="ps-table-scroll">
                <table className="ps-table ps-sale-work-table">
                    <thead>
                        <tr>
                            <th colSpan={4} />
                            {SALE_STAGES.map(([key, labelKey, label]) => <th key={key} colSpan={2}>{psText(t, labelKey, label)}</th>)}
                        </tr>
                        <tr>
                            <th>{psText(t, 'stt', 'STT')}</th>
                            <th>{psText(t, 'sale', 'SALE')}</th>
                            <th>{psText(t, 'total_contact', 'Tổng contact')}</th>
                            <th>{psText(t, 'total_contact_untouched', 'Tổng contact chưa tác nghiệp')}</th>
                            {SALE_STAGES.flatMap(([key]) => [
                                <th key={`${key}-contact`}>{psText(t, 'contact_count', 'Số contact')}</th>,
                                <th key={`${key}-untouched`}>{psText(t, 'untouched', 'Chưa tác nghiệp')}</th>,
                            ])}
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="ps-total-row">
                            <td>1</td>
                            <td className="ps-text-left">{psText(t, 'total', 'Tổng')}: <span>()</span></td>
                            <td>{formatNumber(totalWithUntouched.contacts)}</td>
                            <td>{formatNumber(totalWithUntouched.untouched)}</td>
                            {SALE_STAGES.flatMap(([key]) => [
                                <td key={`${key}-total`}>{formatNumber(totalWithUntouched[`stage_${key}`])}</td>,
                                <td key={`${key}-untouched-total`}>{formatNumber(totalWithUntouched[`stage_${key}_untouched`])}</td>,
                            ])}
                        </tr>
                        {visibleRows.map((row, index) => (
                            <tr key={`${row.name}-${index}`}>
                                <td>{(safePage - 1) * perPage + index + 2}</td>
                                <td className="ps-text-left">{row.name}</td>
                                <td>{formatNumber(row.contacts)}</td>
                                <td>{formatNumber(row.untouched)}</td>
                                {SALE_STAGES.flatMap(([key]) => [
                                    <td key={`${key}-value`}>{formatNumber(row[`stage_${key}`])}</td>,
                                    <td key={`${key}-untouched-value`}>{formatNumber(row[`stage_${key}_untouched`])}</td>,
                                ])}
                            </tr>
                        ))}
                        {visibleRows.length === 0 && (
                            <tr><td colSpan={24} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
            {totalPages > 1 ? <PushsalePager current={safePage} totalPages={totalPages} onPage={setPage} /> : null}
        </section>
    );
}

function dateProgress(filters) {
    const from = filters.date_from ? new Date(`${filters.date_from}T00:00:00`) : new Date();
    const to = filters.date_to ? new Date(`${filters.date_to}T00:00:00`) : from;
    const milliseconds = Math.max(0, to.getTime() - from.getTime());
    const totalDays = Math.max(1, Math.floor(milliseconds / 86400000) + 1);
    const today = new Date();
    const worked = Math.min(totalDays, Math.max(1, Math.floor((today.getTime() - from.getTime()) / 86400000) + 1));
    const remaining = Math.max(0, totalDays - worked);
    return { totalDays, worked, remaining, progress: Math.min(100, Math.round((worked / totalDays) * 100)) };
}

function SaleKpiReport({ rows, totals, filters, filterOptions, filterFields, routeUrl }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    const achieved = filters.sale_id ? (rows[0] ?? totals ?? {}) : (totals ?? rows[0] ?? {});
    const time = dateProgress(draft);
    const cells = [
        ['new_contacts', 'number'], ['new_closed', 'number'], ['new_rate', 'percent'],
        ['old_contacts', 'number'], ['old_closed', 'number'], ['old_rate', 'percent'],
        ['total_closed', 'number'], ['expected_rev', 'currency'], ['base_salary', 'currency'],
        ['bonus', 'currency'], ['income', 'currency'], ['actual_rev', 'currency'],
        ['upsell_qty', 'number'], ['upsell_rev', 'currency'],
    ];

    return (
        <section className="ps-report-page ps-sale-kpi-report">
            <div className="ps-report-topbar ps-extra-toolbar">
                <h1>{psText(t, 'sale_kpi_title', 'Sale KPI 2')}</h1>
                <div className="ps-kpi-toolbar-controls">
                    {fields.has('sale_id') && (
                        <PushsaleSelect
                            placeholder={psText(t, 'choose_sale', '--Chọn sale--')}
                            value={draft.sale_id ?? ''}
                            options={filterOptions.salesUsers ?? []}
                            onChange={(value) => set('sale_id', value)}
                        />
                    )}
                    <PushsaleDateRange filters={draft} onChange={set} />
                    <PushsaleSearchButton onClick={() => apply()} />
                    <PushsaleExportButton routeUrl={routeUrl} filters={draft} />
                </div>
            </div>

            <div className="ps-kpi-layout">
                <div>
                    <table className="ps-kpi-table" data-table-theme="neutral">
                        <thead>
                            <tr>
                                <th />
                                <th>{psText(t, 'contact_new', 'Contact mới')}</th><th>{psText(t, 'closed_orders', 'Chốt đơn')}</th><th>{psText(t, 'rate', 'Tỉ lệ')}</th>
                                <th>{psText(t, 'contact_old', 'Contact cũ')}</th><th>{psText(t, 'closed_orders', 'Chốt đơn')}</th><th>{psText(t, 'rate', 'Tỉ lệ')}</th>
                                <th>{psText(t, 'total_closed', 'Tổng đơn chốt')}</th><th>{psText(t, 'expected_revenue', 'Doanh số dự kiến')}</th><th>{psText(t, 'base_salary', 'Lương cứng')}</th>
                                <th>{psText(t, 'expected_bonus', 'Thưởng dự kiến')}</th><th>{psText(t, 'total_income', 'Tổng thu nhập')}</th><th>{psText(t, 'actual_revenue', 'Doanh số thực')}</th>
                                <th>{psText(t, 'upsale_qty', 'Upsale (SL)')}</th><th>{psText(t, 'upsale_revenue', 'Upsale (DS)')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><th>{psText(t, 'target', 'Target')}</th>{cells.map(([key, format]) => {
                                const targetKey = `target_${key}`;
                                const target = achieved[targetKey] ?? null;
                                return <td key={key}>{target === null ? '—' : formatCell(target, format)}</td>;
                            })}</tr>
                            <tr><th>{psText(t, 'achieved', 'Đã đạt')}</th>{cells.map(([key, format]) => <td key={key}>{formatCell(achieved[key], format) || (format === 'percent' ? '0%' : '0')}</td>)}</tr>
                            <tr><th>{psText(t, 'progress', 'Tiến độ')}</th>{cells.map(([key]) => {
                                const target = Number(achieved[`target_${key}`] ?? 0);
                                const value = Number(achieved[key] ?? 0);
                                return <td key={key}>{target > 0 ? `${Math.min(999, Math.round(value * 100 / target))}%` : '—'}</td>;
                            })}</tr>
                            <tr><th>{psText(t, 'status', 'Tình trạng')}</th>{cells.map(([key]) => {
                                const target = Number(achieved[`target_${key}`] ?? 0);
                                const value = Number(achieved[key] ?? 0);
                                return <td key={key} className={target > 0 && value >= target ? 'ps-kpi-ok' : 'ps-kpi-pending'}>{target > 0 ? (value >= target ? psText(t, 'ok', 'Đạt') : psText(t, 'running', 'Đang chạy')) : '—'}</td>;
                            })}</tr>
                        </tbody>
                    </table>
                </div>
                <div>
                    <table className="ps-time-table" data-table-theme="neutral">
                        <thead><tr><th colSpan={2}>{psText(t, 'time_progress', 'TIẾN ĐỘ THỜI GIAN')}</th></tr></thead>
                        <tbody>
                            <tr><td>{psText(t, 'total_days', 'Tổng số ngày')}</td><td>{time.totalDays}</td></tr>
                            <tr><td>{psText(t, 'working_days', 'Số ngày làm việc')}</td><td>{time.worked}</td></tr>
                            <tr><td>{psText(t, 'remaining_days', 'Số ngày còn lại')}</td><td>{time.remaining}</td></tr>
                            <tr><td>{psText(t, 'time_rate', 'Tiến độ thời gian')}</td><td>{time.progress}%</td></tr>
                        </tbody>
                    </table>
                    <div className="ps-kpi-time-note small-tip">{psText(t, 'actual_revenue_formula', '* Doanh số thực = [Doanh số] - [Chiết khấu] - [Giá dịch vụ COD]')}</div>
                </div>
            </div>
            <div className="ps-kpi-notes small-tip">
                <div>{psText(t, 'kpi_note_contacts', '* Số contact tính theo [ngày sale nhận data] nằm trong khoảng ngày đã chọn')}</div>
                <div>{psText(t, 'kpi_note_closed', '* Số chốt đơn tính theo [ngày chốt đơn] nằm trong khoảng ngày đã chọn')}</div>
                <div>{psText(t, 'kpi_note_upsale', '* Upsale cộng doanh thu và số lượng sản phẩm nhưng không tạo thêm contact/KPI lead.')}</div>
            </div>
        </section>
    );
}


const REVENUE_DETAIL_FORMULAS = [
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
    ['(17) Giá trị đơn = ', 'Doanh số đơn chốt / Số lượng đơn chốt'],
    ['(18) % doanh số hoàn = ', '(doanh số đã hoàn / Xác nhận giao hàng) * 100%'],
    ['(19) % Doanh số huỷ = ', '((Doanh số huỷ vận đơn + Doanh số huỷ đăng đơn) / Doanh số đơn chốt) * 100%'],
    ['Upsale = ', 'Tách riêng SL/DS upsale trong cùng đơn chốt, nhưng không nhân đôi contact/đơn gốc'],
];

function formatReportDateLabel(value) {
    if (!value) return '';
    const [year, month, day] = String(value).split('-');
    if (!year || !month || !day) return String(value);
    return `${day}/${month}/${year}`;
}

function RevenueDetailDateRange({ draft, set }) {
    const label = `${formatReportDateLabel(draft.date_from) || '...'} 00:00 - ${formatReportDateLabel(draft.date_to) || '...'} 23:59`;

    return (
        <div className="ps-revenue-detail-date-range" title="Bấm nửa trái để chọn từ ngày, nửa phải để chọn đến ngày">
            <input className="ps-control ps-revenue-detail-date-label" value={label} readOnly />
            <div className="ps-revenue-detail-date-native" aria-hidden="false">
                <input
                    type="date"
                    value={draft.date_from ?? ''}
                    onChange={(event) => set('date_from', event.target.value)}
                    aria-label="Từ ngày"
                />
                <input
                    type="date"
                    value={draft.date_to ?? ''}
                    onChange={(event) => set('date_to', event.target.value)}
                    aria-label="Đến ngày"
                />
            </div>
        </div>
    );
}

function RevenueDetailFormulaLegend() {
    return (
        <div className="ps-revenue-detail-formulas">
            {REVENUE_DETAIL_FORMULAS.map(([label, text]) => (
                <div className="ps-revenue-detail-formula" key={label}>
                    <span>{label}</span>{text}
                </div>
            ))}
        </div>
    );
}

const REVENUE_GROUPS = [
    ['Chốt đơn', 'closed_qty', 'closed_rev'],
    ['XNGH', 'xngh_qty', 'xngh_rev'],
    ['Hủy chốt', 'cancel_qty', 'cancel_rev'],
    ['Chuyển ĐVGH', 'transfer_qty', 'transfer_rev'],
    ['Đã hoàn', 'returned_qty', 'returned_rev'],
    ['Đang hoàn', 'returning_qty', 'returning_rev'],
    ['Đã giao', 'delivered_qty', 'delivered_rev'],
    ['Đã thanh toán', 'paid_qty', 'paid_rev'],
    ['Giao thành công', 'success_qty', 'success_rev'],
];

function RevenueDetailReport({ title, rows, totals, filters, filterOptions, filterFields, routeUrl }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    const isMarketingReport = fields.has('marketer_id') || fields.has('marketing_team_id') || fields.has('marketing_team_leader_id') || title.toLowerCase().includes('marketing');
    const personLabel = isMarketingReport ? psText(t, 'marketing_name', 'TÊN MARKETING') : psText(t, 'sale_name', 'TÊN SALE');
    const renderIf = (field, node) => (fields.has(field) ? node : null);

    const primaryFilters = (
        <div className="ps-revenue-detail-primary ps-report-toolbar-controls">
            {renderIf('date_type', <ReportField field="date_type" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {(fields.has('date_from') || fields.has('date_to')) ? <RevenueDetailDateRange draft={draft} set={set} /> : null}
            {renderIf('discount_mode', <ReportField field="discount_mode" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {renderIf('reconciliation_status', <ReportField field="reconciliation_status" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
        </div>
    );

    const advancedFilters = (
        <div className="ps-revenue-detail-advanced ps-report-toolbar-controls">
            {isMarketingReport ? renderIf('marketing_team_leader_id', <ReportField field="marketing_team_leader_id" draft={draft} set={set} filterOptions={filterOptions} t={t} />) : renderIf('team_leader_id', <ReportField field="team_leader_id" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {isMarketingReport ? renderIf('marketing_team_id', <ReportField field="marketing_team_id" draft={draft} set={set} filterOptions={filterOptions} t={t} />) : renderIf('team_id', <ReportField field="team_id" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {renderIf('parent_product_id', <ReportField field="parent_product_id" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {renderIf('product_id', <ReportField field="product_id" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {renderIf('delivery_status', <ReportField field="delivery_status" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {isMarketingReport ? renderIf('marketer_id', <ReportField field="marketer_id" draft={draft} set={set} filterOptions={filterOptions} t={t} />) : renderIf('sale_id', <ReportField field="sale_id" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {renderIf('no_closing_date_limit', <ReportField field="no_closing_date_limit" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
            {renderIf('per_page', <ReportField field="per_page" draft={draft} set={set} filterOptions={filterOptions} t={t} />)}
        </div>
    );

    const actions = (
        <div className="ps-revenue-detail-actions ps-report-toolbar-actions">
            <PushsaleSearchButton onClick={() => apply()} />
            <PushsaleExportButton routeUrl={routeUrl} filters={cleanReportPayload(draft)} />
        </div>
    );

    return (
        <PushsalePageShell
            title={title}
            className={`ps-report-page ps-revenue-detail-page ps-report-toolbar-shell ${isMarketingReport ? 'ps-revenue-detail-marketing' : 'ps-revenue-detail-sale'}`}
            headerClassName="ps-revenue-detail-header"
            bodyClassName="ps-revenue-detail-body"
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            actions={actions}
        >
            <RevenueDetailFormulaLegend />
            <div className="ps-table-scroll ps-revenue-detail-scroll">
                <table className="table table-bordered table-striped ps-revenue-table ps-revenue-detail-table" id="tableReport">
                    <thead>
                        <tr className="drags-area">
                            <th className="text-center" rowSpan={2}>{psText(t, 'stt', 'STT')}</th>
                            <th className="text-center ps-revenue-person-col" rowSpan={2}>{personLabel}</th>
                            {REVENUE_GROUPS.map(([label], index) => <th className="text-center" key={label} colSpan={2}>{label.toUpperCase()} ({index + 1})</th>)}
                            <th className="text-center" rowSpan={2}>{psText(t, 'return_rate', '% ĐÃ HOÀN (10)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'cancel_rate', '% HỦY (11)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'xngh_rate', '% XNGH (12)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'success_rate', '% GH Thành công (13)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'contact', 'Contact (14)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'close_rate', 'Tỷ lệ chốt (%) (15)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'product_qty', 'Số sản phẩm (16)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'avg_order', 'Giá trị đơn (17)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'returned_revenue_rate', '% DS ĐÃ HOÀN (18)')}</th>
                            <th className="text-center" rowSpan={2}>{psText(t, 'cancelled_revenue_rate', '% DS HỦY (19)')}</th>
                            <th className="text-center ps-upsell-group" colSpan={6}>UPSALE</th>
                        </tr>
                        <tr>
                            {REVENUE_GROUPS.flatMap(([label]) => [
                                <th className="text-center" key={`${label}-q`}>{psText(t, 'quantity', 'Số lượng')}</th>,
                                <th className="text-center" key={`${label}-r`}>{psText(t, 'revenue', 'Doanh số')}</th>,
                            ])}
                            <th className="text-center">SP gốc</th>
                            <th className="text-center">DS gốc</th>
                            <th className="text-center">SL upsale</th>
                            <th className="text-center">DS upsale</th>
                            <th className="text-center">% đơn upsale</th>
                            <th className="text-center">% DS upsale</th>
                        </tr>
                    </thead>
                    <tbody>
                        {totals && (
                            <tr className="ps-total-row">
                                <td>1</td><td className="ps-text-left">Tổng:</td>
                                {REVENUE_GROUPS.flatMap(([, qty, rev]) => [<td key={qty}>{formatNumber(totals[qty])}</td>, <td key={rev}>{formatCurrency(totals[rev])}</td>])}
                                {['pct_returned','pct_cancel','pct_xngh','pct_success','contacts','close_rate','product_count','avg_order','pct_rev_returned','pct_rev_cancel'].map((key) => (
                                    <td key={key}>{formatCell(totals[key], key.includes('pct') || key === 'close_rate' ? 'percent' : key === 'avg_order' ? 'currency' : 'number')}</td>
                                ))}
                                <td>{formatNumber(totals.base_qty)}</td><td>{formatCurrency(totals.base_rev)}</td>
                                <td>{formatNumber(totals.upsell_qty)}</td><td>{formatCurrency(totals.upsell_rev)}</td>
                                <td>{formatCell(totals.upsell_order_rate, 'percent')}</td><td>{formatCell(totals.upsell_revenue_share, 'percent')}</td>
                            </tr>
                        )}
                        {rows.map((row, index) => (
                            <tr key={`${row.name}-${index}`}>
                                <td>{index + 2}</td><td className="ps-text-left">{row.name}</td>
                                {REVENUE_GROUPS.flatMap(([, qty, rev]) => [<td key={qty}>{formatNumber(row[qty])}</td>, <td key={rev}>{formatCurrency(row[rev])}</td>])}
                                {['pct_returned','pct_cancel','pct_xngh','pct_success','contacts','close_rate','product_count','avg_order','pct_rev_returned','pct_rev_cancel'].map((key) => (
                                    <td key={key}>{formatCell(row[key], key.includes('pct') || key === 'close_rate' ? 'percent' : key === 'avg_order' ? 'currency' : 'number')}</td>
                                ))}
                                <td>{formatNumber(row.base_qty)}</td><td>{formatCurrency(row.base_rev)}</td>
                                <td>{formatNumber(row.upsell_qty)}</td><td>{formatCurrency(row.upsell_rev)}</td>
                                <td>{formatCell(row.upsell_order_rate, 'percent')}</td><td>{formatCell(row.upsell_revenue_share, 'percent')}</td>
                            </tr>
                        ))}
                        {rows.length === 0 && <tr><td colSpan={2 + REVENUE_GROUPS.length * 2 + 10 + 6} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
                    </tbody>
                </table>
            </div>
        </PushsalePageShell>
    );
}

function WarehousePendingReport({ rows, filters, filterOptions, routeUrl }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const [movementOnly, setMovementOnly] = useState('changed');
    const [page, setPage] = useState(1);
    const perPage = 50;
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
    const visibleRows = rows.slice((page - 1) * perPage, page * perPage);

    return (
        <section className="ps-report-page ps-warehouse-pending-report">
            <div className="ps-report-topbar ps-warehouse-pending-title">
                <h1>{psText(t, 'warehouse_pending_title', 'Bảng tổng hợp chờ xuất theo ngày')}</h1>
            </div>
            <div className="ps-warehouse-pending-filters">
                <PushsaleDateRange filters={draft} onChange={set} />
                <PushsaleSelect
                    placeholder={psText(t, 'choose_warehouse', '--Chọn kho--')}
                    value={draft.warehouse_id ?? ''}
                    options={filterOptions.warehouses ?? []}
                    onChange={(value) => set('warehouse_id', value)}
                />
                <PushsaleSelect
                    placeholder={psText(t, 'choose_product', '--Chọn sản phẩm--')}
                    value={draft.product_id ?? ''}
                    options={filterOptions.products ?? []}
                    onChange={(value) => set('product_id', value)}
                />
                <PushsaleSelect
                    placeholder={psText(t, 'changed_only', 'Có biến động')}
                    value={movementOnly}
                    options={[
                        { value: 'changed', label: psText(t, 'changed_only', 'Có biến động') },
                        { value: 'all', label: psText(t, 'all', 'Tất cả') },
                    ]}
                    onChange={setMovementOnly}
                />
                <div className="ps-topbar-actions">
                    <PushsaleSearchButton onClick={() => apply()} />
                    <PushsaleExportButton routeUrl={routeUrl} filters={draft} />
                </div>
            </div>
            <div className="ps-table-scroll">
                <table className="ps-table ps-warehouse-pending-table">
                    <thead>
                        <tr>
                            <th>{psText(t, 'stt', 'STT')}</th><th>{psText(t, 'warehouse', 'Kho')}</th><th>{psText(t, 'product', 'Sản phẩm')}</th><th>{psText(t, 'batch_code', 'Mã lô')}</th>
                            <th>{psText(t, 'opening', 'Đầu kỳ')}</th><th>{psText(t, 'pending_export', 'Chờ xuất')}</th><th>{psText(t, 'sales_export', 'Xuất bán hàng')}</th><th>{psText(t, 'ending', 'Cuối kỳ')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRows.length === 0 && (
                            <tr><td colSpan={8} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>
                        )}
                        {visibleRows.map((row, index) => (
                            <tr key={`${row.warehouse}-${row.product}-${row.batch ?? ''}-${index}`}>
                                <td>{(page - 1) * perPage + index + 1}</td>
                                <td>{row.warehouse}</td>
                                <td className="ps-text-left">{row.product}</td>
                                <td>{row.batch ?? ''}</td>
                                <td>{formatNumber(row.opening)}</td>
                                <td className={Number(row.pending) > 0 ? 'ps-positive-number' : ''}>{formatNumber(row.pending)}</td>
                                <td>{formatNumber(row.sales_export)}</td>
                                <td>{formatNumber(row.ending)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {totalPages > 1 ? <PushsalePager current={page} totalPages={totalPages} onPage={setPage} /> : null}
        </section>
    );
}


function ReportTotalRow({ totals, fields, label = 'Tổng' }) {
    if (!totals) return null;
    return (
        <tr className="ps-total-row">
            <td>1</td><td className="ps-text-left">{label}</td>
            {fields.map(([key, format]) => <td key={key}>{formatCell(totals[key], format)}</td>)}
        </tr>
    );
}

function ShortRecordPager({ current, totalPages, from, to, total, onPage }) {
    return (
        <div className="ps-short-record-pager btn-group">
            <button type="button" className="btn btn-default btn-sm" disabled>
                <span>{from} - {to} <span style={{ fontWeight: 'normal' }}> / </span> {total}</span>
            </button>
            <button type="button" className="btn btn-default btn-sm" disabled={current <= 1} onClick={() => onPage?.(current - 1)} title="Trang trước">
                <i className="fa fa-caret-left" aria-hidden="true" />
            </button>
            <button type="button" className="btn btn-default btn-sm" disabled={current >= totalPages} onClick={() => onPage?.(current + 1)} title="Trang sau">
                <i className="fa fa-caret-right" aria-hidden="true" />
            </button>
        </div>
    );
}

function SaleClosingSummaryReport({ rows, totals, filters, filterOptions, filterFields, routeUrl }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = [
        ['new_contacts', 'number'], ['new_closed', 'number'], ['new_rate', 'percent'], ['new_gross', 'currency'], ['new_discount', 'currency'], ['new_net', 'currency'],
        ['old_closed', 'number'], ['old_gross', 'currency'], ['old_discount', 'currency'], ['old_net', 'currency'],
        ['total_closed', 'number'], ['total_rate', 'percent'], ['total_gross', 'currency'], ['total_discount', 'currency'], ['total_net', 'currency'],
    ];
    const perPage = Math.max(1, Number(draft.per_page || filters.per_page || 20));
    const allRows = rows ?? [];
    const totalRows = allRows.length;
    const totalPages = perPage >= 999999 ? 1 : Math.max(1, Math.ceil(totalRows / perPage));
    const [page, setPage] = useState(1);
    const safePage = Math.min(totalPages, Math.max(1, page));
    const visibleRows = perPage >= 999999 ? allRows : allRows.slice((safePage - 1) * perPage, safePage * perPage);
    const recordFrom = totalRows === 0 ? 0 : (safePage - 1) * perPage + 1;
    const recordTo = perPage >= 999999 ? totalRows : Math.min(totalRows, safePage * perPage);
    const primaryFilters = (
        <div className="ps-report-v2-primary ps-report-toolbar-controls ps-closing-summary-filters">
            <PushsaleDateRange filters={draft} onChange={set} />
            {filterFields.includes('search') ? (
                <input
                    className="ps-control ps-closing-sale-search"
                    value={draft.search ?? ''}
                    placeholder={psText(t, 'sale_name_placeholder', 'Tên sale')}
                    onChange={(event) => set('search', event.target.value)}
                />
            ) : null}
            {filterFields.includes('sale_id') ? (
                <PushsaleSelect
                    placeholder={psText(t, 'choose_sale', '--Chọn sale--')}
                    value={draft.sale_id ?? ''}
                    options={filterOptions.salesUsers ?? []}
                    onChange={(value) => set('sale_id', value)}
                />
            ) : null}
            {filterFields.includes('per_page') ? (
                <PushsaleSelect
                    placeholder="20"
                    value={String(draft.per_page ?? '20')}
                    options={PER_PAGE_OPTIONS(t)}
                    onChange={(value) => set('per_page', value)}
                />
            ) : null}
        </div>
    );

    return (
        <section className="ps-report-page ps-closing-summary-report">
            <PushsalePageShell
                title="Bảng tổng hợp chốt đơn"
                className="ps-report-toolbar-shell ps-extra-toolbar ps-report-v2-toolbar ps-closing-summary-toolbar"
                primaryFilters={primaryFilters}
                actions={(
                    <div className="ps-report-toolbar-actions">
                        <PushsaleSearchButton onClick={() => apply()} />
                        <PushsaleExportButton routeUrl={routeUrl} filters={cleanReportPayload(draft)} />
                    </div>
                )}
            />
            <div className="ps-closing-summary-pager-row">
                <ShortRecordPager current={safePage} totalPages={totalPages} from={recordFrom} to={recordTo} total={totalRows} onPage={setPage} />
            </div>
            <div className="ps-table-scroll ps-closing-summary-scroll">
                <table className="table table-bordered ps-grouped-report-table ps-closing-summary-table" id="tblData">
                    <thead>
                        <tr>
                            <th rowSpan={2}>Tài khoản</th>
                            <th colSpan={6}>Contact nhận mới</th>
                            <th colSpan={4}>Contact nhận trước đó</th>
                            <th colSpan={5}>Tổng hợp</th>
                        </tr>
                        <tr>
                            <th>Số contact</th><th>Chốt đơn</th><th>Tỉ lệ</th><th>Doanh số</th><th>CK</th><th>Doanh số sau CK</th>
                            <th>Chốt đơn</th><th>Doanh số</th><th>CK</th><th>Doanh số sau CK</th>
                            <th>Chốt đơn</th><th>Tỉ lệ</th><th>Doanh số</th><th>CK</th><th>Doanh số sau CK</th>
                        </tr>
                        <tr className="ps-closing-formula-row">
                            <th>(1)</th>
                            <th>(2)</th><th>(3)</th><th>(4) = (3) / (2)</th><th>(5)</th><th>(6)</th><th>(7) = (5) - (6)</th>
                            <th>(8)</th><th>(9)</th><th>(10)</th><th>(11) = (9) - (10)</th>
                            <th>(12) = (3) + (8)</th><th>(13) = (12) / (2)</th><th>(14)</th><th>(15)</th><th>(16) = (14) - (15)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRows.map((row, index) => (
                            <tr key={`${row.sale_id ?? row.account}-${index}`}>
                                <td className="ps-account-cell">
                                    <strong>{row.account || '—'}</strong>
                                    <span>{row.name || '—'}</span>
                                </td>
                                {fields.map(([key, format]) => <td key={key}>{formatCell(row[key], format)}</td>)}
                            </tr>
                        ))}
                        {visibleRows.length === 0 && <tr><td colSpan={16} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
                    </tbody>
                </table>
            </div>
            <div className="ps-closing-summary-notes small-tip">
                <div>* Contact nhận mới: [Số contact] = số đơn có ngày sale nhận data trong khoảng ngày đã chọn, [Số chốt đơn] = số đơn có ngày sale nhận data và ngày chốt đơn đều nằm trong khoảng ngày đã chọn.</div>
                <div>** Contact nhận trước đó: [Số chốt đơn] = số đơn có ngày nhận data nhỏ hơn khoảng ngày đã chọn và ngày chốt đơn nằm trong khoảng ngày đã chọn.</div>
                <div>** Các đơn đã chốt ở trạng thái "Hủy vận đơn", "Đã hoàn" không được tính vào chỉ số giao thành công, nhưng vẫn được đối soát ở báo cáo doanh số chi tiết.</div>
            </div>
        </section>
    );
}

function RevenueGroupSelector({ groups, selectedKeys, onChange, defaultKeys = [] }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const selected = new Set(selectedKeys);
    const setAll = () => onChange(groups.map((group) => group.key));
    const reset = () => onChange(defaultKeys.length > 0 ? defaultKeys : groups.slice(0, 4).map((group) => group.key));
    const toggle = (key) => {
        const next = new Set(selectedKeys);
        if (next.has(key)) next.delete(key);
        else next.add(key);
        onChange(groups.filter((group) => next.has(group.key)).map((group) => group.key));
    };

    return (
        <div className="ps-revenue-group-bar">
            <div className="ps-revenue-group-summary">
                <strong>{reportText(t, 'warehouse_sales', 'group_title', 'Nhóm doanh số')}</strong>
                <span>
                    {reportText(t, 'warehouse_sales', 'group_summary', 'Đang hiển thị {visible}/{total} nhóm; dữ liệu xuất Excel vẫn gồm đầy đủ.')
                        .replace('{visible}', selectedKeys.length)
                        .replace('{total}', groups.length)}
                </span>
            </div>
            <div className={`ps-revenue-group-picker ${open ? 'is-open' : ''}`}>
                <button type="button" className="ps-revenue-group-trigger" onClick={() => setOpen((value) => !value)}>
                    <span>{reportText(t, 'warehouse_sales', 'choose_visible', 'Chọn doanh số hiển thị')}</span>
                    <i className={`fa fa-angle-${open ? 'up' : 'down'}`} aria-hidden="true" />
                </button>
                {open && (
                    <div className="ps-revenue-group-popover">
                        <div className="ps-revenue-group-actions">
                            <button type="button" onClick={setAll}>{reportText(t, 'warehouse_sales', 'select_all', 'Chọn tất cả')}</button>
                            <button type="button" onClick={reset}>{reportText(t, 'warehouse_sales', 'default_1_4', 'Mặc định 1–4')}</button>
                        </div>
                        <div className="ps-revenue-group-options">
                            {groups.map((group) => (
                                <label key={group.key} title={group.description}>
                                    <input
                                        type="checkbox"
                                        checked={selected.has(group.key)}
                                        onChange={() => toggle(group.key)}
                                    />
                                    <span>{group.number}. {group.label}</span>
                                </label>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}


function SystemBusinessReport({ rows, totals, filters, filterOptions, filterFields, routeUrl }) {
    const t = useT();
    const fields = [
        ['active_warehouses', 'number'],
        ['closed_qty', 'number'], ['revenue', 'currency'], ['avg', 'currency'],
        ['new_phone_count', 'number'], ['new_rev', 'currency'], ['new_avg', 'currency'], ['new_share', 'percent'],
        ['old_phone_count', 'number'], ['old_rev', 'currency'], ['old_avg', 'currency'], ['old_share', 'percent'],
        ['warehouse_revenue_avg', 'currency'], ['new_avg_per_phone', 'currency'],
        ['upsell_qty', 'number'], ['upsell_revenue', 'currency'], ['upsell_share', 'percent'],
    ];

    const renderCells = (record) => fields.map(([key, format]) => (
        <td key={key} className={key.includes('share') ? 'ps-report-percent-cell' : ''}>{formatCell(record?.[key], format)}</td>
    ));

    return (
        <section className="ps-report-page ps-system-business-report">
            <PushsaleReportToolbar
                title="Báo cáo kinh doanh hệ thống"
                routeUrl={routeUrl}
                filters={filters}
                filterOptions={filterOptions}
                filterFields={filterFields}
                className="ps-system-business-toolbar"
                primary={['date_from', 'date_to', 'discount_mode', 'delivery_status']}
                advanced={['parent_product_id', 'product_id', 'reconciliation_status', 'per_page', 'warehouse_id', 'team_leader_id', 'team_id', 'no_closing_date_limit']}
            />
            <div className="ps-report-table-shell ps-system-business-shell">
                <div className="ps-table-scroll ps-system-business-scroll">
                    <table className="table table-bordered table-multi-select ps-system-business-table" id="tableReport">
                        <thead>
                            <tr>
                                <th className="text-center" rowSpan={2}>STT</th>
                                <th className="text-center ps-system-name-col" rowSpan={2}>TÊN KHO</th>
                                <th className="text-center" rowSpan={2}>Số lượng<br />kho có<br />doanh số</th>
                                <th className="text-center" colSpan={3}>TỔNG</th>
                                <th className="text-center" colSpan={4}>KHÁCH HÀNG MỚI</th>
                                <th className="text-center" colSpan={4}>KHÁCH HÀNG CŨ</th>
                                <th className="text-center" rowSpan={2}>Doanh số TB/SL Kho</th>
                                <th className="text-center" rowSpan={2}>Doanh số mới TB/SĐT</th>
                                <th className="text-center ps-upsell-group" colSpan={3}>UPSALE</th>
                            </tr>
                            <tr className="drags-area">
                                <th className="text-center">Số đơn chốt</th>
                                <th className="text-center">Tổng doanh số</th>
                                <th className="text-center">Doanh số TB</th>
                                <th className="text-center">Số ĐT mới</th>
                                <th className="text-center">Doanh số</th>
                                <th className="text-center">Doanh số TB</th>
                                <th className="text-center">Tỷ lệ doanh số mới (%)</th>
                                <th className="text-center">Số ĐT cũ</th>
                                <th className="text-center">Doanh số</th>
                                <th className="text-center">Doanh số TB</th>
                                <th className="text-center">Tỷ lệ doanh số cũ (%)</th>
                                <th className="text-center">SL</th>
                                <th className="text-center">Doanh số</th>
                                <th className="text-center">Tỷ trọng</th>
                            </tr>
                            {totals ? (
                                <tr className="rowsum ps-total-row">
                                    <td colSpan={2} className="text-center font-weight-bold">Tổng:</td>
                                    {renderCells(totals)}
                                </tr>
                            ) : null}
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={row.warehouse_id ?? index}>
                                    <td className="text-center">{index + 1}</td>
                                    <td className="ps-text-left">{row.name}</td>
                                    {renderCells(row)}
                                </tr>
                            ))}
                            {rows.length === 0 && <tr><td colSpan={2 + fields.length} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    );
}


const REVENUE_DIMENSION_OPTIONS = [
    { value: 'warehouse', label: '1.Kho' },
    { value: 'products_per_order', label: '2.Số sản phẩm/đơn' },
    { value: 'product', label: '3.Sản phẩm' },
    { value: 'sale', label: '4.Sale' },
    { value: 'marketing', label: '5.Marketing' },
    { value: 'care', label: '6.Care đơn' },
    { value: 'sale_team', label: '7.Nhóm sale' },
    { value: 'marketing_team', label: '8.Nhóm marketing' },
    { value: 'province', label: '9.Tỉnh/Thành phố' },
    { value: 'channel', label: '10.Kênh quảng cáo' },
    { value: 'customer_type', label: '11.Khách cũ/mới' },
    { value: 'shipping_method', label: '12.Phương thức giao hàng' },
];

function RevenueDimensionField() {
    return (
        <select className="ps-control ps-revenue-view-select" value="warehouse" onChange={() => {}} title="Giai đoạn này đang chuẩn hóa theo mẫu 1.Kho của Pushsale">
            {REVENUE_DIMENSION_OPTIONS.map((option) => <option value={option.value} key={option.value}>{option.label}</option>)}
        </select>
    );
}

function RevenueGroupCompactPicker({ groups, selectedKeys, onChange }) {
    const selected = new Set(selectedKeys);
    const addGroup = (key) => {
        if (!key) return;
        const next = new Set(selectedKeys);
        next.add(key);
        onChange(groups.filter((group) => next.has(group.key)).map((group) => group.key));
    };
    const removeGroup = (key) => {
        if (selectedKeys.length <= 1) return;
        onChange(selectedKeys.filter((item) => item !== key));
    };

    return (
        <div className="ps-revenue-group-compact">
            <div className="ps-revenue-group-tags">
                {selectedKeys.slice(0, 2).map((key) => {
                    const group = groups.find((item) => item.key === key);
                    return group ? (
                        <button type="button" key={key} className="ps-revenue-group-tag" onClick={() => removeGroup(key)} title={group.description}>
                            <span>×</span> {group.number}.{group.label}
                        </button>
                    ) : null;
                })}
                {selectedKeys.length > 2 ? <span className="ps-revenue-group-more">+{selectedKeys.length - 2}</span> : null}
            </div>
            <select className="ps-control" value="" onChange={(event) => addGroup(event.target.value)}>
                <option value="">-- Chọn nhóm doanh số --</option>
                {groups.map((group) => <option key={group.key} value={group.key}>{group.number}. {group.label}</option>)}
            </select>
        </div>
    );
}

function RevenueOverviewToolbar({ title, routeUrl, filters, filterOptions, filterFields, groups = [], selectedKeys = [], onSelectedKeys, variant = 'summary' }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    const render = (field) => (fields.has(field) ? <ReportField field={field} draft={draft} set={set} filterOptions={filterOptions} t={t} /> : null);

    const primaryFilters = (
        <div className="ps-revenue-overview-primary ps-report-toolbar-controls">
            <RevenueDimensionField />
            {render('parent_product_id')}
            {render('product_id')}
            {render('date_type')}
            {(fields.has('date_from') || fields.has('date_to')) ? <RevenueDetailDateRange draft={draft} set={set} /> : null}
        </div>
    );

    const advancedFilters = (
        <div className="ps-revenue-overview-advanced ps-report-toolbar-controls">
            {render('discount_mode')}
            {render('delivery_status')}
            {render('reconciliation_status')}
            {render('warehouse_id')}
            {groups.length > 0 ? <RevenueGroupCompactPicker groups={groups} selectedKeys={selectedKeys} onChange={onSelectedKeys} /> : null}
            {render('marketing_team_leader_id') ?? render('team_leader_id')}
            {render('marketing_team_id') ?? render('team_id')}
            {render('per_page')}
            {render('no_closing_date_limit')}
        </div>
    );

    return (
        <PushsalePageShell
            title={title}
            className={`ps-report-toolbar-shell ps-extra-toolbar ps-revenue-overview-toolbar ps-revenue-overview-toolbar-${variant}`}
            headerClassName="ps-revenue-overview-header"
            bodyClassName="ps-revenue-overview-body"
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            actions={(
                <div className="ps-revenue-overview-actions ps-report-toolbar-actions">
                    <PushsaleSearchButton onClick={() => apply()} />
                    <button type="button" className="ps-report-gear" title="Cấu hình hiển thị">
                        <i className="fa fa-gear" aria-hidden="true" />
                    </button>
                </div>
            )}
        />
    );
}

function metricValue(record, key, format) {
    return formatCell(record?.[key], format);
}

function revenueShare(record, groupKey) {
    const total = Number(record?.total_revenue ?? 0);
    const value = Number(record?.[`${groupKey}_revenue`] ?? 0);
    return formatCell(total > 0 ? (value * 100) / total : 0, 'percent');
}

function WarehouseSalesSummaryReport({ title = 'Báo cáo doanh số theo kho', rows, totals, filters, filterOptions, filterFields, routeUrl, extra = {} }) {
    const t = useT();
    const groups = extra.revenueGroups ?? [];
    const defaultKeys = extra.defaultRevenueGroups ?? groups.slice(0, 4).map((group) => group.key);
    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);
    const selected = new Set(selectedKeys);
    const visibleGroups = groups.filter((group) => selected.has(group.key));
    const summaryColSpan = 2 + visibleGroups.length * 3 + 3;

    const renderGroupCells = (record, group, rowKind) => {
        const prefix = group.key;
        if (rowKind === 'main') {
            return [
                <td key={`${prefix}-revenue`} className="ps-rsum-revenue">{metricValue(record, `${prefix}_revenue`, 'currency')}</td>,
                <td key={`${prefix}-orders`} className="ps-rsum-orders">{metricValue(record, `${prefix}_orders`, 'number')}</td>,
                <td key={`${prefix}-avg`} className="ps-rsum-avg">{metricValue(record, `${prefix}_avg`, 'currency')}</td>,
            ];
        }

        return [
            <td key={`${prefix}-share`} className="ps-rsum-share">{revenueShare(record, prefix)}</td>,
            <td key={`${prefix}-products`} className="ps-rsum-products">{metricValue(record, `${prefix}_products`, 'number')}</td>,
            <td key={`${prefix}-ppo`} className="ps-rsum-products-per-order">{metricValue(record, `${prefix}_products_per_order`, 'number')}</td>,
        ];
    };

    const renderRecord = (record, index, isTotal = false) => {
        const rowNumber = isTotal ? '' : index + 1;
        const name = isTotal ? 'Tổng:' : record.name;
        return (
            <>
                <tr className={isTotal ? 'ps-total-row ps-revenue-summary-main-row' : 'ps-revenue-summary-main-row'}>
                    <td rowSpan={2} className="ps-rsum-stt">{rowNumber}</td>
                    <td rowSpan={2} className="ps-text-left ps-rsum-name">{name}</td>
                    {visibleGroups.flatMap((group) => renderGroupCells(record, group, 'main'))}
                    <td rowSpan={2} className="ps-upsell-qty">{metricValue(record, 'upsell_qty', 'number')}</td>
                    <td rowSpan={2} className="ps-upsell-revenue">{metricValue(record, 'upsell_revenue', 'currency')}</td>
                    <td rowSpan={2} className="ps-upsell-share">{metricValue(record, 'upsell_share', 'percent')}</td>
                </tr>
                <tr className={isTotal ? 'ps-total-row ps-revenue-summary-sub-row' : 'ps-revenue-summary-sub-row'}>
                    {visibleGroups.flatMap((group) => renderGroupCells(record, group, 'sub'))}
                </tr>
            </>
        );
    };

    return (
        <section className="ps-report-page ps-sales-revenue-summary ps-warehouse-sales-summary">
            <RevenueOverviewToolbar
                title={title}
                routeUrl={routeUrl}
                filters={filters}
                filterOptions={filterOptions}
                filterFields={filterFields}
                groups={groups}
                selectedKeys={selectedKeys}
                onSelectedKeys={setSelectedKeys}
                variant="summary"
            />
            <div className="ps-revenue-summary-legend">
                <span className="is-revenue">Doanh số<br />Doanh số/Tổng doanh số</span>
                <span className="is-orders">Số lượng đơn<br />Số sản phẩm</span>
                <span className="is-avg">Giá trị đơn hàng<br />Số sản phẩm/Đơn</span>
            </div>
            <div className="ps-table-scroll ps-sales-revenue-scroll">
                <table className="table table-bordered table-multi-select ps-grouped-report-table ps-sales-revenue-summary-table" id="tableReport">
                    <thead>
                        <tr>
                            <th rowSpan={2}>STT</th>
                            <th rowSpan={2}>TÊN KHO</th>
                            {visibleGroups.map((group) => <th key={group.key} colSpan={3} title={group.description}>{group.label.toUpperCase()} ({group.number})<br /><small>{group.description}</small></th>)}
                            <th colSpan={3} className="ps-upsell-group">UPSALE</th>
                        </tr>
                        <tr className="drags-area">
                            {visibleGroups.flatMap((group) => [
                                <th key={`${group.key}-ds`}>Doanh số</th>,
                                <th key={`${group.key}-sl`}>Số lượng đơn</th>,
                                <th key={`${group.key}-gt`}>Giá trị đơn hàng</th>,
                            ])}
                            <th>SL</th><th>Doanh số</th><th>Tỷ trọng</th>
                        </tr>
                    </thead>
                    <tbody>
                        {totals ? renderRecord(totals, 0, true) : null}
                        {rows.map((row, index) => <Fragment key={row.warehouse_id ?? index}>{renderRecord(row, index)}</Fragment>)}
                        {rows.length === 0 && <tr><td colSpan={summaryColSpan} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function WarehouseSalesV2Report({ title = 'Báo cáo doanh số V2', rows, totals, filters, filterOptions, filterFields, routeUrl, extra = {} }) {
    const t = useT();
    const groups = extra.revenueGroups ?? [];
    const defaultKeys = extra.defaultRevenueGroups ?? groups.slice(0, 4).map((group) => group.key);
    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);
    const selected = new Set(selectedKeys);
    const visibleGroups = groups.filter((group) => selected.has(group.key));
    const fields = [
        ['contacts', 'number', 'ps-rv2-contact'], ['closed_contacts', 'number', 'ps-rv2-closed'], ['close_rate', 'percent', 'ps-rv2-rate'],
        ...visibleGroups.flatMap((group) => [
            [`${group.key}_orders`, 'number', 'ps-rv2-orders'],
            [`${group.key}_products`, 'number', 'ps-rv2-products'],
            [`${group.key}_avg`, 'currency', 'ps-rv2-avg'],
            [`${group.key}_revenue`, 'currency', 'ps-rv2-revenue'],
        ]),
        ['upsell_qty', 'number', 'ps-rv2-upsell-qty'], ['upsell_revenue', 'currency', 'ps-rv2-upsell-revenue'], ['upsell_share', 'percent', 'ps-rv2-upsell-share'],
    ];
    const colSpan = 2 + fields.length;

    const renderRow = (record, index, isTotal = false) => (
        <tr className={isTotal ? 'ps-total-row' : ''} key={isTotal ? 'total' : record.warehouse_id ?? index}>
            <td>{isTotal ? '' : index + 1}</td>
            <td className="ps-text-left">{isTotal ? 'Tổng:' : record.name}</td>
            {fields.map(([key, format, className]) => <td key={key} className={className}>{formatCell(record?.[key], format)}</td>)}
        </tr>
    );

    return (
        <section className="ps-report-page ps-sales-revenue-v2 ps-warehouse-sales-v2">
            <RevenueOverviewToolbar
                title={title}
                routeUrl={routeUrl}
                filters={filters}
                filterOptions={filterOptions}
                filterFields={filterFields}
                groups={groups}
                selectedKeys={selectedKeys}
                onSelectedKeys={setSelectedKeys}
                variant="v2"
            />
            <div className="ps-table-scroll ps-sales-revenue-scroll ps-sales-revenue-v2-scroll">
                <table className="table table-bordered table-multi-select fixed-column-table fixed-2-row-top ps-grouped-report-table ps-sales-revenue-v2-table ps-v2-table" id="tableReport">
                    <thead>
                        <tr>
                            <th rowSpan={2}>STT</th>
                            <th rowSpan={2}>TÊN KHO</th>
                            <th rowSpan={2}>Số contact</th>
                            <th rowSpan={2}>Số đơn chốt</th>
                            <th rowSpan={2}>Tỷ lệ chốt (%)</th>
                            {visibleGroups.map((group) => <th key={group.key} colSpan={4} title={group.description}>{group.label.toUpperCase()} ({group.number})<br /><small>{group.description}</small></th>)}
                            <th colSpan={3} className="ps-upsell-group">UPSALE</th>
                        </tr>
                        <tr className="drags-area">
                            {visibleGroups.flatMap((group) => ['Số đơn', 'Số sp', 'Giá trị TB đơn', 'Doanh số'].map((label) => <th key={`${group.key}-${label}`}>{label}</th>))}
                            <th>SL</th><th>Doanh số</th><th>Tỷ trọng</th>
                        </tr>
                    </thead>
                    <tbody>
                        {totals ? renderRow(totals, 0, true) : null}
                        {rows.map((row, index) => renderRow(row, index))}
                        {rows.length === 0 && <tr><td colSpan={colSpan} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function AppointmentCardsReport({ rows, totals, filters, filterOptions, filterFields, routeUrl }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    return (
        <section className="ps-report-page ps-appointment-report">
            <div className="ps-report-topbar ps-extra-toolbar"><h1>Báo cáo lịch hẹn telesales</h1><div className="ps-extra-toolbar-controls">
                <PushsaleDateRange filters={draft} onChange={set} />
                {fields.has('operation_stage') && <PushsaleSelect placeholder="--Tác nghiệp--" value={draft.operation_stage ?? ''} options={filterOptions.operationStages ?? []} onChange={(value)=>set('operation_stage',value)} />}
                {fields.has('operation_result') && <PushsaleSelect placeholder="--Kết quả--" value={draft.operation_result ?? ''} options={filterOptions.operationResults ?? []} onChange={(value)=>set('operation_result',value)} />}
                {fields.has('team_id') && <PushsaleSelect placeholder={psText(t, 'choose_sales_team', '--Nhóm sale--')} value={draft.team_id ?? ''} options={filterOptions.salesTeams ?? filterOptions.teams ?? []} onChange={(value)=>set('team_id',value)} />}
                {fields.has('sale_id') && <PushsaleSelect placeholder={psText(t, 'choose_sale', '--Chọn sale--')} value={draft.sale_id ?? ''} options={filterOptions.salesUsers ?? []} onChange={(value)=>set('sale_id',value)} />}
                <PushsaleSearchButton onClick={()=>apply()} /><PushsaleExportButton routeUrl={routeUrl} filters={draft} />
            </div></div>
            <div className="ps-appointment-summary"><span>Tổng lịch hẹn</span><strong>{formatNumber(totals?.count ?? 0)}</strong><small>Không nhân đôi bởi packet upsale</small></div>
            <div className="ps-appointment-grid">{rows.map((row,index)=><article key={row.date_iso ?? index} className={`ps-appointment-card ${row.overdue ? 'is-overdue' : ''}`}><div className="ps-appointment-card-head"><span>{row.weekday}</span><strong>{row.date}</strong></div><div className="ps-appointment-count">{formatNumber(row.count)}</div><div className="ps-appointment-label">lịch hẹn gọi lại</div><div className="ps-appointment-sales" title={row.sales}>{row.sales}</div></article>)}</div>
        </section>
    );
}

function ProductConversionMatrixReport({ rows, totals, extra, filters, filterOptions, filterFields, routeUrl }) {
    const t = useT();
    const groups = extra?.groups ?? [];
    const core = [['contacts','number'],['closed','number'],['rate','percent'],['revenue','currency'],['avg','currency'],['upsell_qty','number'],['upsell_revenue','currency'],['upsell_share','percent']];
    const personFields = [['contacts','number'],['closed','number'],['rate','percent'],['revenue','currency'],['avg','currency']];
    const renderMetrics = (record, prefix='') => personFields.map(([key,format])=><td key={`${prefix}${key}`}>{formatCell(record?.[`${prefix}${key}`],format)}</td>);
    return (
        <section className="ps-report-page ps-product-conversion-report">
            <CommonToolbar title="Tỉ lệ chốt đơn sản phẩm" routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} filterFields={filterFields} />
            <div className="ps-matrix-legend"><span>Contact chỉ đếm lead gốc</span><span>Doanh số gồm sản phẩm gốc + upsale</span><span>Doanh số sản phẩm tính theo từng dòng hàng</span></div>
            <div className="ps-table-scroll"><table className="ps-table ps-product-matrix-table"><thead>
                <tr><th rowSpan={2}>{psText(t, 'stt', 'STT')}</th><th rowSpan={2}>ID</th><th rowSpan={2}>SẢN PHẨM</th><th colSpan={8}>TỔNG HỢP</th>{groups.map((group)=><th key={group.prefix} colSpan={5} className={group.role==='marketing'?'is-marketing':'is-sales'}>{group.label}</th>)}</tr>
                <tr>{['Contact','Chốt đơn','Tỷ lệ','Doanh số','AVG','Upsale SL','Upsale DS','% Upsale'].map((label)=><th key={label}>{label}</th>)}{groups.flatMap((group)=>['Contact','Chốt','Tỷ lệ','Doanh số','AVG'].map((label)=><th key={`${group.prefix}${label}`}>{label}</th>))}</tr>
            </thead><tbody>
                {totals && <tr className="ps-total-row"><td>1</td><td /><td className="ps-text-left">Tổng</td>{core.map(([key,format])=><td key={key}>{formatCell(totals[key],format)}</td>)}{groups.flatMap((group)=>renderMetrics(totals,group.prefix))}</tr>}
                {rows.map((row,index)=><tr key={row.product_key ?? index}><td>{index+2}</td><td>{row.product_id ?? '—'}</td><td className="ps-text-left ps-sticky-product">{row.name}</td>{core.map(([key,format])=><td key={key}>{formatCell(row[key],format)}</td>)}{groups.flatMap((group)=>renderMetrics(row,group.prefix))}</tr>)}
                {rows.length===0 && <tr><td colSpan={11+groups.length*5} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
            </tbody></table></div>
        </section>
    );
}



function MarketingUpsaleReport({ title, rows, totals, filters, filterOptions, filterFields, routeUrl, extra = {} }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const [showNote, setShowNote] = useState(false);
    const fields = new Set(filterFields);
    const perPage = Math.max(1, Number(draft?.per_page ?? 20) || 20);
    const [page, setPage] = useState(1);
    const totalPages = Math.max(1, Math.ceil((rows?.length ?? 0) / perPage));
    const safePage = Math.min(page, totalPages);
    const visibleRows = (rows ?? []).slice((safePage - 1) * perPage, safePage * perPage);
    const render = (field) => (fields.has(field) ? <ReportField key={field} field={field} draft={draft} set={set} filterOptions={filterOptions} t={t} /> : null);
    const currentPayload = cleanReportPayload(draft);
    const sourceScopeOptions = [
        { value: 'sale', label: 'Nhóm sale' },
        { value: 'marketing', label: 'Nhóm Marketing' },
    ];
    const [scopeType, setScopeType] = useState('sale');
    const scopeFields = scopeType === 'sale'
        ? ['team_leader_id', 'team_id', 'sale_id']
        : ['marketing_team_leader_id', 'marketing_team_id', 'marketer_id'];
    const visibleColSpan = 13;
    const renderNumber = (value, decimals = 0) => {
        if (value === null || value === undefined || value === '') return '';
        const number = Number(value);
        if (!Number.isFinite(number)) return String(value);
        if (decimals > 0) return number.toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
        return formatNumber(number);
    };
    const renderRow = (row, index) => (
        <tr key={row.source_id ?? `${row.name}-${index}`}>
            <td className="text-center">{(safePage - 1) * perPage + index + 1}</td>
            <td className="text-center ps-upsale-source-name">{row.name}</td>
            <td className="text-center">{row.channel}</td>
            <td className="text-center ps-upsale-products">{row.products}</td>
            <td className="text-center text-bold">{formatNumber(row.contacts)}</td>
            <td className="text-center text-bold">{formatNumber(row.closed)}</td>
            <td className="text-center">{renderNumber(row.rate_decimal, 2)}</td>
            <td className="text-center">{formatNumber(row.product_types)}</td>
            <td className="text-center">{formatNumber(row.qty_sold)}</td>
            <td className="text-center">{formatCurrency(row.revenue)}</td>
            <td className="text-center">{renderNumber(row.avg_order, 2)}</td>
            <td className="text-center">{renderNumber(row.items_per_order, 2)}</td>
            <td className="text-center ps-upsale-detail-cell">
                {row.detail_url ? (
                    <a href={row.detail_url} title="Hồ sơ khách hàng" className="ps-row-action-link">
                        <i className="fa fa-list fs15" aria-hidden="true" />
                    </a>
                ) : null}
            </td>
        </tr>
    );
    const renderTotalRow = (row) => (
        <tr className="rowsum ps-total-row">
            <td className="text-center font-weight-bold" colSpan={4}>Tổng:</td>
            <td className="text-center font-weight-bold">{formatNumber(row.contacts)}</td>
            <td className="text-center font-weight-bold">{formatNumber(row.closed)}</td>
            <td className="text-center font-weight-bold">{renderNumber(row.rate_decimal, 2)}</td>
            <td className="text-center font-weight-bold">{formatNumber(row.product_types)}</td>
            <td className="text-center font-weight-bold">{formatNumber(row.qty_sold)}</td>
            <td className="text-center font-weight-bold">{formatCurrency(row.revenue)}</td>
            <td className="text-center font-weight-bold">{renderNumber(row.avg_order, 2)}</td>
            <td className="text-center font-weight-bold">{renderNumber(row.items_per_order, 2)}</td>
            <td />
        </tr>
    );

    return (
        <section className="ps-report-page ps-marketing-upsale-report">
            <PushsalePageShell
                title={title}
                className="ps-report-toolbar-shell ps-extra-toolbar ps-marketing-upsale-toolbar"
                primaryFilters={(
                    <div className="ps-upsale-primary ps-report-toolbar-controls">
                        {fields.has('search') ? <input className="ps-control text-center" value={draft.search ?? ''} placeholder="Tên nguồn dữ liệu" onChange={(event) => set('search', event.target.value)} /> : null}
                        {render('date_type')}
                        {(fields.has('date_from') || fields.has('date_to')) ? <PushsaleDateRange filters={draft} onChange={set} /> : null}
                    </div>
                )}
                advancedFilters={(
                    <div className="ps-upsale-advanced-wrap">
                        <div className="ps-upsale-advanced ps-report-toolbar-controls">
                            {render('parent_product_id')}
                            {render('product_id')}
                            {render('customer_type')}
                            {render('no_closing_date_limit')}
                        </div>
                        <div className="ps-upsale-advanced ps-report-toolbar-controls">
                            <PushsaleSelect value={scopeType} options={sourceScopeOptions} placeholder="Nhóm sale" onChange={setScopeType} />
                            {scopeFields.map(render)}
                            {render('per_page')}
                        </div>
                    </div>
                )}
                actions={(
                    <div className="ps-report-toolbar-actions ps-upsale-actions">
                        <PushsaleSearchButton onClick={() => apply()} />
                        <PushsaleActionMenu routeUrl={routeUrl} filters={currentPayload} onNote={() => setShowNote(true)} />
                    </div>
                )}
            />

            <div className="ps-upsale-pager-top">
                <span />
                <div className="btn-group">
                    <button type="button" className="btn btn-default btn-sm">{rows.length > 0 ? `${(safePage - 1) * perPage + 1} - ${Math.min(safePage * perPage, rows.length)} / ${formatNumber(rows.length)}` : '0 / 0'}</button>
                    <button type="button" className="btn btn-default btn-sm" disabled={safePage <= 1} onClick={() => setPage(safePage - 1)}><i className="fa fa-caret-left" /></button>
                    <button type="button" className="btn btn-default btn-sm" disabled={safePage >= totalPages} onClick={() => setPage(safePage + 1)}><i className="fa fa-caret-right" /></button>
                </div>
            </div>

            <div className="ps-table-scroll ps-marketing-upsale-scroll dragscroll1 tableFixHead">
                <table className="table table-bordered table-multi-select ps-marketing-upsale-table" id="tableReport">
                    <thead>
                        <tr className="drags-area">
                            <th className="text-center">STT</th>
                            <th className="text-center">Nguồn dữ liệu (1)</th>
                            <th className="text-center">KÊNH (2)</th>
                            <th className="text-center">SẢN PHẨM ĐĂNG KÝ TRÊN NGUỒN DỮ LIỆU (3)</th>
                            <th className="text-center">CONTACT (4)</th>
                            <th className="text-center">ĐƠN CHỐT (5)</th>
                            <th className="text-center">TỈ LỆ CHỐT ĐƠN (6 = 5/4)</th>
                            <th className="text-center">SỐ LOẠI SP ĐƯỢC BÁN RA (7)</th>
                            <th className="text-center">SỐ LƯỢNG SP BÁN RA (8)</th>
                            <th className="text-center">DOANH SỐ (9)</th>
                            <th className="text-center">GIÁ TRỊ ĐƠN HÀNG TB (10 = 9/5)</th>
                            <th className="text-center">SỐ SẢN PHẨM TB/ĐƠN (11 = 8/5)</th>
                            <th className="text-center">CHI TIẾT ĐƠN HÀNG TỪ NGUỒN DỮ LIỆU (12)</th>
                        </tr>
                    </thead>
                    <tbody>
                        {totals ? renderTotalRow(totals) : null}
                        {visibleRows.map((row, index) => renderRow(row, index))}
                        {rows.length === 0 ? <tr><td colSpan={visibleColSpan} className="ps-empty">Không có dữ liệu.</td></tr> : null}
                    </tbody>
                </table>
            </div>
            {totalPages > 1 ? <PushsalePager current={safePage} totalPages={totalPages} onPage={setPage} /> : null}

            {showNote ? (
                <div className="ps-modal-backdrop ps-upsale-note-modal" role="dialog" aria-modal="true">
                    <div className="ps-modal-card modal-content">
                        <div className="modal-header">
                            <button type="button" className="close" aria-label="Close" onClick={() => setShowNote(false)}><span aria-hidden="true">×</span></button>
                            <h4 className="modal-title">GIẢI THÍCH</h4>
                        </div>
                        <div className="modal-body">
                            <table className="table table-bordered table-striped">
                                <thead><tr><th>Chỉ số</th><th>Ý nghĩa</th><th>Công thức</th></tr></thead>
                                <tbody>
                                    {(extra.notes ?? []).map((note, index) => (
                                        <tr key={index}>
                                            <td className="text-left text-bold">{note.metric}</td>
                                            <td className="text-left">{note.meaning}</td>
                                            <td className="text-left">{note.formula}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            ) : null}
        </section>
    );
}


function rateToneClass(value) {
    const rate = Number(value);
    if (!Number.isFinite(rate) || rate <= 0) return '';
    if (rate < 50) return 'color-alert';
    if (rate < 80) return 'color-warning';
    return 'color-success';
}

function MarketingWorkToolbar({ title, routeUrl, filters, filterOptions, filterFields = [] }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);
    const render = (field) => (fields.has(field) ? <ReportField key={field} field={field} draft={draft} set={set} filterOptions={filterOptions} t={t} /> : null);

    const primaryFilters = (
        <div className="ps-marketing-work-primary ps-report-toolbar-controls">
            {render('date_type')}
            {(fields.has('date_from') || fields.has('date_to')) ? <PushsaleDateRange filters={draft} onChange={set} /> : null}
            {render('customer_type')}
        </div>
    );

    const advancedFilters = (
        <div className="ps-marketing-work-advanced ps-report-toolbar-controls">
            <PushsaleSelect placeholder="Sale" value="sale" options={[{ value: 'sale', label: 'Sale' }]} onChange={() => {}} />
            {render('marketing_team_leader_id')}
            {render('marketing_team_id')}
            {render('team_id')}
            {render('no_closing_date_limit')}
            {render('marketer_id')}
            {render('sale_id')}
            {render('search')}
            {render('parent_product_id')}
            {render('product_id')}
            {render('per_page')}
        </div>
    );

    return (
        <PushsalePageShell
            title={title}
            className="ps-report-toolbar-shell ps-extra-toolbar ps-marketing-work-toolbar"
            primaryFilters={primaryFilters}
            advancedFilters={advancedFilters}
            actions={(
                <div className="ps-report-toolbar-actions">
                    <PushsaleSearchButton onClick={() => apply()} />
                    <PushsaleExportButton routeUrl={routeUrl} filters={cleanReportPayload(draft)} />
                </div>
            )}
        />
    );
}

function MarketingWorkMatrixReport({ title, rows, totals, filters, filterOptions, filterFields, routeUrl, extra = {} }) {
    const matrixRows = extra.matrixRows ?? rows ?? [];
    const salesColumns = extra.salesColumns ?? [];
    const totalColSpan = 5 + salesColumns.length * 2;
    const perPage = Math.max(1, Number(filters?.per_page ?? 20) || 20);
    const [page, setPage] = useState(1);
    const totalPages = Math.max(1, Math.ceil(matrixRows.length / perPage));
    const visibleMatrixRows = matrixRows.slice((page - 1) * perPage, page * perPage);
    const cellFor = (row, saleId) => (row?.sale_cells ?? []).find((cell) => Number(cell.sale_id) === Number(saleId)) ?? {};
    const renderRate = (value) => {
        if (value === null || value === undefined || value === '') return '%';
        return formatCell(value, 'percent');
    };
    const renderRow = (row, index, isTotal = false) => (
        <tr key={isTotal ? 'total' : row.marketer_id ?? index} className={isTotal ? 'ps-total-row' : ''}>
            <td className="text-center">{isTotal ? 1 : index + 2}</td>
            <td className="text-left withName">
                {isTotal ? 'Tổng:' : (
                    <>
                        {row.username ? `${row.username} ` : ''}{row.name ? `(${row.name})` : ''}
                    </>
                )}
            </td>
            <td className="text-center text-bold">{formatCell(row.contacts, 'number')}</td>
            <td className="text-center">{row.unallocated ? formatCell(row.unallocated, 'number') : ''}</td>
            <td className={`nowrap text-center ${rateToneClass(row.rate)}`}>{renderRate(row.rate)}</td>
            {salesColumns.flatMap((sale) => {
                const cell = cellFor(row, sale.id);
                return [
                    <td key={`${sale.id}-contacts`} className="nowrap text-center">{cell.contacts ? formatCell(cell.contacts, 'number') : ''}</td>,
                    <td key={`${sale.id}-rate`} className={`nowrap text-center ${rateToneClass(cell.rate)}`}>{renderRate(cell.rate)}</td>,
                ];
            })}
        </tr>
    );

    return (
        <section className="ps-report-page ps-marketing-work-report">
            <MarketingWorkToolbar title={title} routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} filterFields={filterFields} />
            <div className="ps-marketing-work-legend">
                <span><i className="color-swatch color-alert" />Dưới 50 %</span>
                <span><i className="color-swatch color-warning" />Từ 50 đến 80 %</span>
                <span><i className="color-swatch color-success" />Trên 80 %</span>
            </div>
            <div className="ps-table-scroll ps-marketing-work-scroll dragscroll1 tableFixHead">
                <table className="table table-bordered table-multi-select ps-marketing-work-table">
                    <thead>
                        <tr className="drags-area">
                            <th rowSpan={2} className="text-center">STT</th>
                            <th rowSpan={2} className="text-center">MARKETING</th>
                            <th rowSpan={2} className="text-center">Tổng contact</th>
                            <th rowSpan={2} className="text-center">Tổng contact<br />chưa phân bổ</th>
                            <th rowSpan={2} className="text-center">Tỷ lệ chốt<br />(%)</th>
                            {salesColumns.map((sale) => (
                                <th key={sale.id} colSpan={2} className="text-center">
                                    {sale.name}<br />
                                    <small>({sale.username})</small>
                                </th>
                            ))}
                        </tr>
                        <tr className="drags-area">
                            {salesColumns.flatMap((sale) => [
                                <th key={`${sale.id}-contacts`} className="text-center">Số contact</th>,
                                <th key={`${sale.id}-rate`} className="text-center">Tỷ lệ chốt<br />(%)</th>,
                            ])}
                        </tr>
                    </thead>
                    <tbody>
                        {totals ? renderRow(totals, 0, true) : null}
                        {visibleMatrixRows.map((row, index) => renderRow(row, (page - 1) * perPage + index))}
                        {matrixRows.length === 0 ? <tr><td colSpan={totalColSpan} className="ps-empty">Không có dữ liệu.</td></tr> : null}
                    </tbody>
                </table>
            </div>
            {totalPages > 1 ? <PushsalePager current={page} totalPages={totalPages} onPage={setPage} /> : null}
        </section>
    );
}

function GenericReport({ title, rows, totals, columns, filters, filterOptions, filterFields, routeUrl, t, labels }) {
    const perPage = 50;
    const [page, setPage] = useState(1);
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
    const visibleRows = rows.slice((page - 1) * perPage, page * perPage);

    return (
        <section className="ps-report-page ps-generic-report">
            <CommonToolbar title={title} routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} filterFields={filterFields} />
            <div className="ps-table-scroll">
                <table className="ps-table ps-generic-table">
                    <thead><tr><th>{psText(t, 'stt', 'STT')}</th>{columns.map((col) => <th key={col.key}>{resolveColumnLabel(col, t, labels)}</th>)}</tr></thead>
                    <tbody>
                        {totals && rows.length > 0 && (
                            <tr className="ps-total-row"><td>1</td>{columns.map((col, index) => <td key={col.key} className={col.format === 'text' ? 'ps-text-left' : ''}>{index === 0 ? psText(t, 'total', 'Tổng') : formatCell(totals[col.key], col.format)}</td>)}</tr>
                        )}
                        {visibleRows.length === 0 && <tr><td colSpan={columns.length + 1} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
                        {visibleRows.map((row, index) => (
                            <tr key={index}><td>{(page - 1) * perPage + index + (totals ? 2 : 1)}</td>{columns.map((col) => <td key={col.key} className={col.format === 'text' ? 'ps-text-left' : ''}>{formatCell(row[col.key], col.format)}</td>)}</tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {totalPages > 1 ? <PushsalePager current={page} totalPages={totalPages} onPage={setPage} /> : null}
        </section>
    );
}

export default function ExtraReport({
    meta,
    columns = [],
    rows = [],
    totals = null,
    filters = {},
    filterOptions = {},
    filterFields = [],
    routeUrl,
    extra = {},
    activeMenuCode = null,
}) {
    const t = useT();
    const labels = useLabels();
    const title = reportText(t, meta.key, 'title', meta.title);
    const isRevenueDetail = ['sale-revenue-detail', 'sale-3', 'marketing-1'].includes(meta.key);

    let content;
    if (['sale-work', 'sale-1'].includes(meta.key)) {
        content = <SaleWorkReport title={psText(t, 'sale_work_title', 'Báo cáo công việc sale')} rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (['sale-closing-summary', 'sale-2'].includes(meta.key)) {
        content = <SaleClosingSummaryReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (['sale-kpi', 'sale-4'].includes(meta.key)) {
        content = <SaleKpiReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (['sale-appointments', 'sale-5'].includes(meta.key)) {
        content = <AppointmentCardsReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (['system-business', 'kho-2'].includes(meta.key)) {
        content = <SystemBusinessReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (['sale-revenue', 'warehouse-sales-summary', 'marketing-sales-summary'].includes(meta.key)) {
        content = <WarehouseSalesSummaryReport title={title} rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} extra={extra} />;
    } else if (['sale-revenue-v2', 'warehouse-sales-v2', 'marketing-sales-v2'].includes(meta.key)) {
        content = <WarehouseSalesV2Report title={title} rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} extra={extra} />;
    } else if (meta.key === 'product-conversion') {
        content = <ProductConversionMatrixReport rows={rows} totals={totals} extra={extra} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'marketing-3') {
        content = <MarketingWorkMatrixReport title={title} rows={rows} totals={totals} extra={extra} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'marketing-4') {
        content = <MarketingUpsaleReport title={title} rows={rows} totals={totals} extra={extra} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'kho-1') {
        content = <WarehousePendingReport rows={rows} filters={filters} filterOptions={filterOptions} routeUrl={routeUrl} />;
    } else if (isRevenueDetail) {
        content = <RevenueDetailReport title={title} rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else {
        content = <GenericReport title={title} rows={rows} totals={totals} columns={columns} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} t={t} labels={labels} />;
    }

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={['sale-kpi', 'sale-4'].includes(meta.key) ? psText(t, 'sale_kpi_title', 'Sale KPI 2') : title} />
            {content}
        </AppLayout>
    );
}
