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
import { useLabels } from '@/hooks/use-labels';
import AppLayout from '@/layouts/AppLayout';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

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
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const fields = new Set(filterFields);

    return (
        <div className={`ps-report-topbar ps-extra-toolbar ${compact ? 'is-compact' : ''}`}>
            <h1>{title}</h1>
            <div className="ps-extra-toolbar-controls">
                {fields.has('date_type') && (
                    <PushsaleSelect
                        placeholder="Ngày sale nhận data"
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
                        placeholder="--Chọn sale--"
                        value={draft.sale_id ?? ''}
                        options={filterOptions.salesUsers ?? []}
                        onChange={(value) => set('sale_id', value)}
                    />
                )}
                {fields.has('marketer_id') && (
                    <PushsaleSelect
                        placeholder="--Marketing--"
                        value={draft.marketer_id ?? ''}
                        options={filterOptions.marketingUsers ?? []}
                        onChange={(value) => set('marketer_id', value)}
                    />
                )}
                {fields.has('warehouse_id') && (
                    <PushsaleSelect
                        placeholder="--Chọn kho--"
                        value={draft.warehouse_id ?? ''}
                        options={filterOptions.warehouses ?? []}
                        onChange={(value) => set('warehouse_id', value)}
                    />
                )}
                {fields.has('product_id') && (
                    <PushsaleSelect
                        placeholder="--Chọn sản phẩm--"
                        value={draft.product_id ?? ''}
                        options={filterOptions.products ?? []}
                        onChange={(value) => set('product_id', value)}
                    />
                )}
                <PushsaleSearchButton onClick={() => apply()} />
                <PushsaleExportButton routeUrl={routeUrl} filters={draft} />
            </div>
        </div>
    );
}

const SALE_STAGES = [
    ['new_customer', 'Gọi lần 1'],
    ['call_2', 'Gọi lần 2'],
    ['call_3', 'Gọi lần 3'],
    ['call_4', 'Gọi lần 4'],
    ['call_5', 'Gọi lần 5'],
    ['call_6', 'Gọi lần 6'],
    ['care_1', 'Chăm sóc lần 1'],
    ['care_2', 'Chăm sóc lần 2'],
    ['care_3', 'Chăm sóc lần 3'],
    ['skipped', 'Bỏ qua'],
];

function SaleWorkReport({ title, rows, totals, filters, filterOptions, filterFields, routeUrl }) {
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const [operationFilter, setOperationFilter] = useState('');
    const [teamType, setTeamType] = useState('leader');
    const [teamId, setTeamId] = useState('');
    const [pageSize, setPageSize] = useState('50');
    const operationOptions = SALE_STAGES.map(([value, label]) => ({ value, label }));

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

    return (
        <section className="ps-report-page ps-sale-work-report">
            <div className="ps-report-topbar ps-extra-toolbar ps-sale-work-toolbar">
                <h1>{title}</h1>
                <div className="ps-extra-toolbar-controls">
                    <PushsaleSelect
                        placeholder="Ngày sale nhận data"
                        value={draft.date_type ?? ''}
                        options={filterOptions.dateTypes ?? []}
                        onChange={(value) => set('date_type', value)}
                    />
                    <PushsaleDateRange filters={draft} onChange={set} />
                    <PushsaleSelect
                        placeholder="Chọn tác nghiệp"
                        value={operationFilter}
                        options={operationOptions}
                        onChange={setOperationFilter}
                    />
                    <div className="ps-topbar-actions">
                        <button type="button" className="ps-collapse-filter" title="Thu gọn bộ lọc" aria-label="Thu gọn bộ lọc">
                            <i className="fa fa-angle-double-up" aria-hidden="true" />
                        </button>
                        <PushsaleSearchButton onClick={() => apply()} />
                        <PushsaleExportButton routeUrl={routeUrl} filters={draft} />
                    </div>
                </div>
            </div>
            <div className="ps-sale-work-secondary">
                <PushsaleSelect
                    placeholder="Trưởng nhóm"
                    value={teamType}
                    options={[{ value: 'leader', label: 'Trưởng nhóm' }, { value: 'staff', label: 'Nhân viên' }]}
                    onChange={setTeamType}
                />
                <PushsaleSelect
                    placeholder="--Chọn nhóm--"
                    value={teamId}
                    options={filterOptions.teams ?? []}
                    onChange={setTeamId}
                />
                <PushsaleSelect
                    placeholder="50"
                    value={pageSize}
                    options={[{ value: '50', label: '50' }, { value: '100', label: '100' }]}
                    onChange={setPageSize}
                />
            </div>
            <div className="ps-grid-count">1 - {Math.max(1, rows.length)} / {Math.max(1, rows.length)} <button disabled>‹</button><button disabled>›</button></div>
            <div className="ps-table-scroll">
                <table className="ps-table ps-sale-work-table">
                    <thead>
                        <tr>
                            <th colSpan={4} />
                            {SALE_STAGES.map(([key, label]) => <th key={key} colSpan={2}>{label}</th>)}
                        </tr>
                        <tr>
                            <th>STT</th>
                            <th>SALE</th>
                            <th>Tổng contact</th>
                            <th>Tổng contact<br />chưa tác<br />nghiệp</th>
                            {SALE_STAGES.flatMap(([key]) => [
                                <th key={`${key}-contact`}>Số<br />contact</th>,
                                <th key={`${key}-untouched`}>Chưa tác<br />nghiệp</th>,
                            ])}
                        </tr>
                    </thead>
                    <tbody>
                        <tr className="ps-total-row">
                            <td>1</td>
                            <td className="ps-text-left">Tổng: <span>()</span></td>
                            <td>{formatNumber(totalWithUntouched.contacts)}</td>
                            <td>{formatNumber(totalWithUntouched.untouched)}</td>
                            {SALE_STAGES.flatMap(([key]) => [
                                <td key={`${key}-total`}>{formatNumber(totalWithUntouched[`stage_${key}`])}</td>,
                                <td key={`${key}-untouched-total`}>{formatNumber(totalWithUntouched[`stage_${key}_untouched`])}</td>,
                            ])}
                        </tr>
                        {rows.map((row, index) => (
                            <tr key={`${row.name}-${index}`}>
                                <td>{index + 2}</td>
                                <td className="ps-text-left">{row.name}</td>
                                <td>{formatNumber(row.contacts)}</td>
                                <td>{formatNumber(row.untouched)}</td>
                                {SALE_STAGES.flatMap(([key]) => [
                                    <td key={`${key}-value`}>{formatNumber(row[`stage_${key}`])}</td>,
                                    <td key={`${key}-untouched-value`}>{formatNumber(row[`stage_${key}_untouched`])}</td>,
                                ])}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <PushsalePager current={1} totalPages={1} />
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

function SaleKpiReport({ rows, totals, filters, filterOptions, routeUrl }) {
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const achieved = rows[0] ?? totals ?? {};
    const time = dateProgress(draft);
    const cells = [
        ['new_contacts', 'number'], ['new_closed', 'number'], ['new_rate', 'percent'],
        ['old_contacts', 'number'], ['old_closed', 'number'], ['old_rate', 'percent'],
        ['total_closed', 'number'], ['expected_rev', 'currency'], ['base_salary', 'currency'],
        ['bonus', 'currency'], ['income', 'currency'], ['actual_rev', 'currency'],
    ];

    return (
        <section className="ps-report-page ps-sale-kpi-report">
            <div className="ps-report-topbar ps-extra-toolbar">
                <h1>Sale KPI 2</h1>
                <div className="ps-kpi-toolbar-controls">
                    <PushsaleSelect
                        placeholder="--Chọn sale--"
                        value={draft.sale_id ?? ''}
                        options={filterOptions.salesUsers ?? []}
                        onChange={(value) => set('sale_id', value)}
                    />
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
                                <th>Contact mới</th><th>Chốt đơn</th><th>Tỉ lệ</th>
                                <th>Contact cũ</th><th>Chốt đơn</th><th>Tỉ lệ</th>
                                <th>Tổng đơn chốt</th><th>Doanh số dự kiến</th><th>Lương cứng</th>
                                <th>Thưởng dự kiến</th><th>Tổng thu nhập</th><th>Doanh số thực</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><th>Target</th>{cells.map(([key]) => <td key={key}>{key.includes('rate') ? '%' : ''}</td>)}</tr>
                            <tr><th>Đã đạt</th>{cells.map(([key, format]) => <td key={key}>{formatCell(achieved[key], format) || (format === 'percent' ? '%' : '')}</td>)}</tr>
                            <tr><th>Tiến độ</th>{cells.map(([key]) => <td key={key} />)}</tr>
                            <tr><th>Tình trạng</th>{cells.map(([key]) => <td key={key} />)}</tr>
                        </tbody>
                    </table>
                    <div className="ps-kpi-progress-bar"><span>Doanh số đạt:</span></div>
                    <div className="ps-kpi-notes">
                        <div>* Số contact tính theo [ngày sale nhận data] nằm trong khoảng ngày đã chọn</div>
                        <div>* Số chốt đơn tính theo [ngày chốt đơn] nằm trong khoảng ngày đã chọn</div>
                    </div>
                </div>
                <div>
                    <table className="ps-time-table" data-table-theme="neutral">
                        <thead><tr><th colSpan={2}>TIẾN ĐỘ THỜI GIAN</th></tr></thead>
                        <tbody>
                            <tr><td>Tổng số ngày</td><td>{time.totalDays}</td></tr>
                            <tr><td>Số ngày làm việc</td><td>{time.worked}</td></tr>
                            <tr><td>Số ngày còn lại</td><td>{time.remaining}</td></tr>
                            <tr><td>Tiến độ thời gian</td><td>{time.progress}%</td></tr>
                        </tbody>
                    </table>
                    <div className="ps-kpi-notes">* Doanh số thực = [Doanh số] - [Chiết khấu] - [Giá dịch vụ COD]</div>
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
    return (
        <section className="ps-report-page ps-revenue-detail-report">
            <CommonToolbar title={title} routeUrl={routeUrl} filters={filters} filterOptions={filterOptions} filterFields={filterFields} />
            <div className="ps-table-scroll">
                <table className="ps-table ps-revenue-table">
                    <thead>
                        <tr>
                            <th rowSpan={2}>STT</th>
                            <th rowSpan={2}>{filterFields.includes('marketer_id') ? 'MARKETING' : 'SALE'}</th>
                            {REVENUE_GROUPS.map(([label]) => <th key={label} colSpan={2}>{label}</th>)}
                            <th rowSpan={2}>% hoàn</th><th rowSpan={2}>% hủy</th><th rowSpan={2}>% XNGH</th>
                            <th rowSpan={2}>% giao TC</th><th rowSpan={2}>Contact</th><th rowSpan={2}>Tỷ lệ chốt</th>
                            <th rowSpan={2}>Số SP</th><th rowSpan={2}>Đơn TB</th><th rowSpan={2}>% DS hoàn</th><th rowSpan={2}>% DS hủy</th>
                        </tr>
                        <tr>{REVENUE_GROUPS.flatMap(([label]) => [<th key={`${label}-q`}>SL</th>, <th key={`${label}-r`}>Doanh số</th>])}</tr>
                    </thead>
                    <tbody>
                        {totals && (
                            <tr className="ps-total-row">
                                <td>1</td><td className="ps-text-left">Tổng</td>
                                {REVENUE_GROUPS.flatMap(([, qty, rev]) => [<td key={qty}>{formatNumber(totals[qty])}</td>, <td key={rev}>{formatCurrency(totals[rev])}</td>])}
                                {['pct_returned','pct_cancel','pct_xngh','pct_success','contacts','close_rate','product_count','avg_order','pct_rev_returned','pct_rev_cancel'].map((key) => (
                                    <td key={key}>{formatCell(totals[key], key.includes('pct') || key === 'close_rate' ? 'percent' : key === 'avg_order' ? 'currency' : 'number')}</td>
                                ))}
                            </tr>
                        )}
                        {rows.map((row, index) => (
                            <tr key={`${row.name}-${index}`}>
                                <td>{index + 2}</td><td className="ps-text-left">{row.name}</td>
                                {REVENUE_GROUPS.flatMap(([, qty, rev]) => [<td key={qty}>{formatNumber(row[qty])}</td>, <td key={rev}>{formatCurrency(row[rev])}</td>])}
                                {['pct_returned','pct_cancel','pct_xngh','pct_success','contacts','close_rate','product_count','avg_order','pct_rev_returned','pct_rev_cancel'].map((key) => (
                                    <td key={key}>{formatCell(row[key], key.includes('pct') || key === 'close_rate' ? 'percent' : key === 'avg_order' ? 'currency' : 'number')}</td>
                                ))}
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
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const [movementOnly, setMovementOnly] = useState('changed');
    const [page, setPage] = useState(1);
    const perPage = 50;
    const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
    const visibleRows = rows.slice((page - 1) * perPage, page * perPage);

    return (
        <section className="ps-report-page ps-warehouse-pending-report">
            <div className="ps-report-topbar ps-warehouse-pending-title">
                <h1>Bảng tổng hợp chờ xuất theo ngày</h1>
            </div>
            <div className="ps-warehouse-pending-filters">
                <PushsaleDateRange filters={draft} onChange={set} />
                <PushsaleSelect
                    placeholder="--Chọn kho--"
                    value={draft.warehouse_id ?? ''}
                    options={filterOptions.warehouses ?? []}
                    onChange={(value) => set('warehouse_id', value)}
                />
                <PushsaleSelect
                    placeholder="--Chọn sản phẩm--"
                    value={draft.product_id ?? ''}
                    options={filterOptions.products ?? []}
                    onChange={(value) => set('product_id', value)}
                />
                <PushsaleSelect
                    placeholder="Có biến động"
                    value={movementOnly}
                    options={[
                        { value: 'changed', label: 'Có biến động' },
                        { value: 'all', label: 'Tất cả' },
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
                            <th>STT</th><th>Kho</th><th>Sản phẩm</th><th>Mã lô</th>
                            <th>Đầu kỳ</th><th>Chờ xuất</th><th>Xuất bán hàng</th><th>Cuối kỳ</th>
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRows.length === 0 && (
                            <tr><td colSpan={8} className="ps-empty">Không có dữ liệu.</td></tr>
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
                    <thead><tr><th>STT</th>{columns.map((col) => <th key={col.key}>{resolveColumnLabel(col, t, labels)}</th>)}</tr></thead>
                    <tbody>
                        {totals && rows.length > 0 && (
                            <tr className="ps-total-row"><td>1</td>{columns.map((col, index) => <td key={col.key} className={col.format === 'text' ? 'ps-text-left' : ''}>{index === 0 ? 'Tổng' : formatCell(totals[col.key], col.format)}</td>)}</tr>
                        )}
                        {visibleRows.length === 0 && <tr><td colSpan={columns.length + 1} className="ps-empty">Không có dữ liệu.</td></tr>}
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
}) {
    const t = useT();
    const labels = useLabels();
    const title = reportText(t, meta.key, 'title', meta.title);
    const isRevenueDetail = ['sale-3', 'marketing-1'].includes(meta.key);

    let content;
    if (meta.key === 'sale-1') {
        content = <SaleWorkReport title="Báo cáo công việc sale" rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else if (meta.key === 'sale-4') {
        content = <SaleKpiReport rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} routeUrl={routeUrl} />;
    } else if (meta.key === 'kho-1') {
        content = <WarehousePendingReport rows={rows} filters={filters} filterOptions={filterOptions} routeUrl={routeUrl} />;
    } else if (isRevenueDetail) {
        content = <RevenueDetailReport title={title} rows={rows} totals={totals} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} />;
    } else {
        content = <GenericReport title={title} rows={rows} totals={totals} columns={columns} filters={filters} filterOptions={filterOptions} filterFields={filterFields} routeUrl={routeUrl} t={t} labels={labels} />;
    }

    return (
        <AppLayout>
            <Head title={meta.key === 'sale-4' ? 'Sale KPI 2' : title} />
            {content}
        </AppLayout>
    );
}
