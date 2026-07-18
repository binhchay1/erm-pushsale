import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import {
    PushsaleDateRange,
    PushsaleExportButton,
    PushsalePager,
    PushsaleSearchButton,
    PushsaleSelect,
    usePushsaleFilters,
} from '@/components/reports/PushsaleReportChrome';
import { PushsalePageChrome } from '@/components/layout/PushsalePageChrome';
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

function CommonToolbar({ title, routeUrl, filters, filterOptions, filterFields = [], compact = false }) {
    const t = useT();
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);

    const controls = (
        <>
            {fields.has('date_type') && (
                <PushsaleSelect
                    placeholder={psText(t, 'date_sale_received', 'Ngày sale nhận data')}
                    value={draft.date_type ?? ''}
                    options={filterOptions.dateTypes ?? []}
                    onChange={(value) => set('date_type', value)}
                />
            )}
            {(fields.has('date_from') || fields.has('date_to')) && (
                <PushsaleDateRange filters={draft} onChange={set} />
            )}
            {fields.has('sale_id') && (
                <PushsaleSelect
                    placeholder={psText(t, 'choose_sale', '--Chọn sale--')}
                    value={draft.sale_id ?? ''}
                    options={filterOptions.salesUsers ?? []}
                    onChange={(value) => set('sale_id', value)}
                />
            )}
            {fields.has('marketer_id') && (
                <PushsaleSelect
                    placeholder={psText(t, 'choose_marketing', '--Marketing--')}
                    value={draft.marketer_id ?? ''}
                    options={filterOptions.marketingUsers ?? []}
                    onChange={(value) => set('marketer_id', value)}
                />
            )}
            {fields.has('team_id') && (
                <PushsaleSelect
                    placeholder={psText(t, 'choose_sales_team', '--Nhóm sale--')}
                    value={draft.team_id ?? ''}
                    options={filterOptions.salesTeams ?? filterOptions.teams ?? []}
                    onChange={(value) => set('team_id', value)}
                />
            )}
            {fields.has('marketing_team_id') && (
                <PushsaleSelect
                    placeholder={psText(t, 'choose_marketing_team', '--Nhóm marketing--')}
                    value={draft.marketing_team_id ?? ''}
                    options={filterOptions.marketingTeams ?? []}
                    onChange={(value) => set('marketing_team_id', value)}
                />
            )}
            {fields.has('warehouse_id') && (
                <PushsaleSelect
                    placeholder={psText(t, 'choose_warehouse', '--Chọn kho--')}
                    value={draft.warehouse_id ?? ''}
                    options={filterOptions.warehouses ?? []}
                    onChange={(value) => set('warehouse_id', value)}
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
            <PushsaleSearchButton onClick={() => apply()} />
            <PushsaleExportButton routeUrl={routeUrl} filters={draft} />
        </>
    );

    return (
        <PushsalePageChrome
            title={title}
            className={`ps-report-topbar ps-extra-toolbar ${compact ? 'is-compact' : ''}`}
            filters={controls}
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
            <div className="ps-grid-count">
                {fromRow} - {toRow} / {rows.length}
                <button type="button" disabled={safePage <= 1} onClick={() => setPage(Math.max(1, safePage - 1))}>‹</button>
                <button type="button" disabled={safePage >= totalPages} onClick={() => setPage(Math.min(totalPages, safePage + 1))}>›</button>
            </div>
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
            <PushsalePager current={safePage} totalPages={totalPages} onPage={setPage} />
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
                    <div className="ps-kpi-progress-bar"><span>{psText(t, 'sales_progress', 'Doanh số đạt:')}</span></div>
                    <div className="ps-kpi-notes">
                        <div>{psText(t, 'kpi_note_contacts', '* Số contact tính theo [ngày sale nhận data] nằm trong khoảng ngày đã chọn')}</div>
                        <div>{psText(t, 'kpi_note_closed', '* Số chốt đơn tính theo [ngày chốt đơn] nằm trong khoảng ngày đã chọn')}</div>
                        <div>{psText(t, 'kpi_note_upsale', '* Upsale cộng doanh thu và số lượng sản phẩm nhưng không tạo thêm contact/KPI lead.')}</div>
                    </div>
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
                    <div className="ps-kpi-notes">{psText(t, 'actual_revenue_formula', '* Doanh số thực = [Doanh số] - [Chiết khấu] - [Giá dịch vụ COD]')}</div>
                </div>
            </div>
        </section>
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

    return (
        <section className="ps-report-page ps-revenue-detail-report">
            <CommonToolbar title={title} routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} filterFields={filterFields} />
            <div className="ps-table-scroll">
                <table className="ps-table ps-revenue-table">
                    <thead>
                        <tr>
                            <th rowSpan={2}>{psText(t, 'stt', 'STT')}</th>
                            <th rowSpan={2}>{filterFields.includes('marketer_id') ? psText(t, 'marketing', 'MARKETING') : psText(t, 'sale', 'SALE')}</th>
                            {REVENUE_GROUPS.map(([label]) => <th key={label} colSpan={2}>{label}</th>)}
                            <th rowSpan={2}>{psText(t, 'return_rate', '% hoàn')}</th><th rowSpan={2}>{psText(t, 'cancel_rate', '% hủy')}</th><th rowSpan={2}>{psText(t, 'xngh_rate', '% XNGH')}</th>
                            <th rowSpan={2}>{psText(t, 'success_rate', '% giao TC')}</th><th rowSpan={2}>{psText(t, 'contact', 'Contact')}</th><th rowSpan={2}>{psText(t, 'close_rate', 'Tỷ lệ chốt')}</th>
                            <th rowSpan={2}>{psText(t, 'product_qty', 'Số SP')}</th><th rowSpan={2}>{psText(t, 'avg_order', 'Đơn TB')}</th><th rowSpan={2}>{psText(t, 'returned_revenue_rate', '% DS hoàn')}</th><th rowSpan={2}>{psText(t, 'cancelled_revenue_rate', '% DS hủy')}</th>
                            <th colSpan={6} className="ps-upsell-group">UPSALE</th>
                        </tr>
                        <tr>{REVENUE_GROUPS.flatMap(([label]) => [<th key={`${label}-q`}>{psText(t, 'quantity', 'SL')}</th>, <th key={`${label}-r`}>{psText(t, 'revenue', 'Doanh số')}</th>])}<th>{psText(t, 'original_product', 'SP gốc')}</th><th>{psText(t, 'original_revenue', 'DS gốc')}</th><th>{psText(t, 'upsale_qty', 'SL upsale')}</th><th>{psText(t, 'upsale_revenue', 'DS upsale')}</th><th>{psText(t, 'upsale_order_rate', '% đơn upsale')}</th><th>{psText(t, 'upsale_revenue_share', '% DS upsale')}</th></tr>
                    </thead>
                    <tbody>
                        {totals && (
                            <tr className="ps-total-row">
                                <td>1</td><td className="ps-text-left">Tổng</td>
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
                    </tbody>
                </table>
            </div>
            <PushsalePager current={1} totalPages={1} />
        </section>
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
            <PushsalePager current={page} totalPages={totalPages} onPage={setPage} />
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

function SaleClosingSummaryReport({ rows, totals, filters, filterOptions, filterFields, routeUrl }) {
    const t = useT();
    const fields = [
        ['new_contacts','number'],['new_closed','number'],['new_rate','percent'],['new_gross','currency'],['new_discount','currency'],['new_net','currency'],
        ['old_closed','number'],['old_gross','currency'],['old_discount','currency'],['old_net','currency'],
        ['total_closed','number'],['total_rate','percent'],['total_gross','currency'],['total_discount','currency'],['total_net','currency'],
        ['upsell_qty','number'],['upsell_revenue','currency'],
    ];
    return (
        <section className="ps-report-page ps-closing-summary-report">
            <CommonToolbar title="Bảng tổng hợp chốt đơn" routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} filterFields={filterFields} />
            <div className="ps-table-scroll">
                <table className="ps-table ps-grouped-report-table">
                    <thead>
                        <tr><th rowSpan={2}>{psText(t, 'stt', 'STT')}</th><th rowSpan={2}>TELESALE</th><th colSpan={6}>CONTACT MỚI</th><th colSpan={4}>KHÁCH HÀNG CŨ</th><th colSpan={5}>TỔNG</th><th colSpan={2} className="ps-upsell-group">UPSALE</th></tr>
                        <tr><th>Contact</th><th>Chốt đơn</th><th>Tỷ lệ</th><th>Doanh số</th><th>Chiết khấu</th><th>Sau CK</th><th>Chốt đơn</th><th>Doanh số</th><th>Chiết khấu</th><th>Sau CK</th><th>Chốt đơn</th><th>Tỷ lệ</th><th>Doanh số</th><th>Chiết khấu</th><th>Sau CK</th><th>SL</th><th>Doanh số</th></tr>
                    </thead>
                    <tbody>
                        <ReportTotalRow totals={totals} fields={fields} />
                        {rows.map((row, index) => <tr key={`${row.name}-${index}`}><td>{index + 2}</td><td className="ps-text-left">{row.name}</td>{fields.map(([key, format]) => <td key={key}>{formatCell(row[key], format)}</td>)}</tr>)}
                        {rows.length === 0 && <tr><td colSpan={19} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
                    </tbody>
                </table>
            </div>
            <PushsalePager current={1} totalPages={1} />
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

function WarehouseSalesSummaryReport({ rows, totals, filters, filterOptions, filterFields, routeUrl, extra = {} }) {
    const t = useT();
    const groups = extra.revenueGroups ?? [];
    const defaultKeys = extra.defaultRevenueGroups ?? groups.slice(0, 4).map((group) => group.key);
    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);
    const selected = new Set(selectedKeys);
    const visibleGroups = groups.filter((group) => selected.has(group.key));
    const fields = [
        ...visibleGroups.flatMap((group) => [
            [`${group.key}_revenue`, 'currency'],
            [`${group.key}_orders`, 'number'],
            [`${group.key}_avg`, 'currency'],
            [`${group.key}_products`, 'number'],
            [`${group.key}_products_per_order`, 'number'],
        ]),
        ['upsell_qty', 'number'], ['upsell_revenue', 'currency'], ['upsell_share', 'percent'],
    ];

    return (
        <section className="ps-report-page ps-warehouse-sales-summary">
            <CommonToolbar title="Báo cáo doanh số theo kho" routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} filterFields={filterFields} />
            <RevenueGroupSelector groups={groups} selectedKeys={selectedKeys} onChange={setSelectedKeys} defaultKeys={defaultKeys} />
            <div className="ps-table-scroll"><table className="ps-table ps-grouped-report-table"><thead>
                <tr>
                    <th rowSpan={2}>{psText(t, 'stt', 'STT')}</th><th rowSpan={2}>TÊN KHO</th>
                    {visibleGroups.map((group) => <th key={group.key} colSpan={5} title={group.description}>{group.label} ({group.number})</th>)}
                    <th colSpan={3} className="ps-upsell-group">UPSALE</th>
                </tr>
                <tr>
                    {visibleGroups.flatMap((group) => ['Doanh số', 'Số đơn', 'TB/đơn', 'Số SP', 'SP/đơn'].map((label) => <th key={`${group.key}-${label}`}>{label}</th>))}
                    <th>SL</th><th>Doanh số</th><th>Tỷ trọng</th>
                </tr>
            </thead><tbody>
                <ReportTotalRow totals={totals} fields={fields} />
                {rows.map((row, index) => <tr key={row.warehouse_id ?? index}><td>{index + 2}</td><td className="ps-text-left">{row.name}</td>{fields.map(([key, format]) => <td key={key}>{formatCell(row[key], format)}</td>)}</tr>)}
                {rows.length === 0 && <tr><td colSpan={2 + fields.length} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
            </tbody></table></div><PushsalePager current={1} totalPages={1} />
        </section>
    );
}

function WarehouseSalesV2Report({ rows, totals, filters, filterOptions, filterFields, routeUrl, extra = {} }) {
    const t = useT();
    const groups = extra.revenueGroups ?? [];
    const defaultKeys = extra.defaultRevenueGroups ?? groups.slice(0, 4).map((group) => group.key);
    const [selectedKeys, setSelectedKeys] = useState(defaultKeys);
    const selected = new Set(selectedKeys);
    const visibleGroups = groups.filter((group) => selected.has(group.key));
    const fields = [
        ['contacts', 'number'], ['closed_contacts', 'number'], ['close_rate', 'percent'],
        ...visibleGroups.flatMap((group) => [
            [`${group.key}_orders`, 'number'],
            [`${group.key}_products`, 'number'],
            [`${group.key}_avg`, 'currency'],
            [`${group.key}_revenue`, 'currency'],
        ]),
        ['upsell_qty', 'number'], ['upsell_revenue', 'currency'], ['upsell_share', 'percent'],
    ];

    return (
        <section className="ps-report-page ps-warehouse-sales-v2">
            <CommonToolbar title="Báo cáo doanh số V2" routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} filterFields={filterFields} />
            <RevenueGroupSelector groups={groups} selectedKeys={selectedKeys} onChange={setSelectedKeys} defaultKeys={defaultKeys} />
            <div className="ps-table-scroll"><table className="ps-table ps-grouped-report-table ps-v2-table"><thead>
                <tr>
                    <th rowSpan={2}>{psText(t, 'stt', 'STT')}</th><th rowSpan={2}>TÊN KHO</th><th colSpan={3}>PHỄU CONTACT</th>
                    {visibleGroups.map((group) => <th key={group.key} colSpan={4} title={group.description}>{group.label} ({group.number})</th>)}
                    <th colSpan={3} className="ps-upsell-group">UPSALE</th>
                </tr>
                <tr>
                    <th>Contact</th><th>Chốt</th><th>Tỷ lệ</th>
                    {visibleGroups.flatMap((group) => ['Số đơn', 'Số SP', 'TB/đơn', 'Doanh số'].map((label) => <th key={`${group.key}-${label}`}>{label}</th>))}
                    <th>SL</th><th>Doanh số</th><th>Tỷ trọng</th>
                </tr>
            </thead><tbody>
                <ReportTotalRow totals={totals} fields={fields} />
                {rows.map((row, index) => <tr key={row.warehouse_id ?? index}><td>{index + 2}</td><td className="ps-text-left">{row.name}</td>{fields.map(([key, format]) => <td key={key}>{formatCell(row[key], format)}</td>)}</tr>)}
                {rows.length === 0 && <tr><td colSpan={2 + fields.length} className="ps-empty">{psText(t, 'no_data', 'Không có dữ liệu.')}</td></tr>}
            </tbody></table></div><PushsalePager current={1} totalPages={1} />
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
            <PushsalePager current={page} totalPages={totalPages} onPage={setPage} />
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
    const isRevenueDetail = ['sale-3', 'marketing-1'].includes(meta.key);

    let content;
    if (meta.key === 'sale-1') {
        content = <SaleWorkReport title={psText(t, 'sale_work_title', 'Báo cáo công việc sale')} rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'sale-2') {
        content = <SaleClosingSummaryReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'sale-4') {
        content = <SaleKpiReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'sale-5') {
        content = <AppointmentCardsReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'warehouse-sales-summary') {
        content = <WarehouseSalesSummaryReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} extra={extra} />;
    } else if (meta.key === 'warehouse-sales-v2') {
        content = <WarehouseSalesV2Report rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} extra={extra} />;
    } else if (meta.key === 'product-conversion') {
        content = <ProductConversionMatrixReport rows={rows} totals={totals} extra={extra} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'kho-1') {
        content = <WarehousePendingReport rows={rows} filters={filters} filterOptions={filterOptions} routeUrl={routeUrl} />;
    } else if (isRevenueDetail) {
        content = <RevenueDetailReport title={title} rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else {
        content = <GenericReport title={title} rows={rows} totals={totals} columns={columns} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} t={t} labels={labels} />;
    }

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={meta.key === 'sale-4' ? psText(t, 'sale_kpi_title', 'Sale KPI 2') : title} />
            {content}
        </AppLayout>
    );
}
