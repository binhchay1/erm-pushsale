import { Head, router } from '@inertiajs/react';
import { Fragment, useMemo, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';

const numberFormatter = new Intl.NumberFormat('vi-VN');
const currencyFormatter = new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
});

const DATE_TYPE_OPTIONS = [
    { id: 'next_operation_date', label: 'Ngày sale tác nghiệp tiếp' },
    { id: 'closing_date', label: 'Ngày sale chốt đơn' },
    { id: 'care_update', label: 'Ngày sale tác nghiệp' },
    { id: 'data_arrival', label: 'Ngày data về hệ thống' },
    { id: 'sale_received_data', label: 'Ngày sale nhận data' },
    { id: 'posting_date', label: 'Ngày đăng đơn' },
    { id: 'desired_delivery_date', label: 'Ngày nhận care đơn' },
    { id: 'delivery_update_date', label: 'Ngày cập nhật care đơn' },
];

const OPERATION_STAGES = [
    { key: 'call_1', label: 'Gọi lần 1' },
    { key: 'call_2', label: 'Gọi lần 2' },
    { key: 'call_3', label: 'Gọi lần 3' },
    { key: 'call_4', label: 'Gọi lần 4' },
    { key: 'call_5', label: 'Gọi lần 5' },
    { key: 'call_6', label: 'Gọi lần 6' },
    { key: 'care_1', label: 'Chăm sóc lần 1' },
    { key: 'care_2', label: 'Chăm sóc lần 2' },
    { key: 'care_3', label: 'Chăm sóc lần 3' },
    { key: 'skipped', label: 'Bỏ qua' },
];

const METRIC_OPTIONS = [
    { id: 'total_revenue', label: '1.Doanh số tổng' },
    { id: 'total_closed', label: '2.Số chốt đơn' },
    { id: 'total_contacts', label: '3.Số contact' },
    { id: 'total_rate', label: '4.Tỷ lệ chốt' },
];

const PER_PAGE_OPTIONS = ['20', '50', '100', '200', '500', '1000'];

function currentQuery() {
    if (typeof window === 'undefined') return new URLSearchParams();
    return new URLSearchParams(window.location.search);
}

function todayIso() {
    const date = new Date();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

function toDisplayDate(iso) {
    if (!iso) return '';
    const [year, month, day] = String(iso).slice(0, 10).split('-');
    if (!year || !month || !day) return iso;
    return `${day}/${month}/${year}`;
}

function toIsoDate(value) {
    const trimmed = String(value ?? '').trim();
    if (!trimmed) return '';
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) return trimmed;
    const match = trimmed.match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})/);
    if (!match) return '';
    const [, day, month, year] = match;
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
}

function rangeLabel(from, to) {
    const start = toDisplayDate(from || todayIso());
    const end = toDisplayDate(to || from || todayIso());
    return `${start} 00:00 - ${end} 23:59`;
}

function parseRange(value) {
    const parts = String(value ?? '').split('-').map((part) => part.trim()).filter(Boolean);
    if (parts.length >= 2) return { date_from: toIsoDate(parts[0]), date_to: toIsoDate(parts[1]) };
    const single = toIsoDate(value);
    return { date_from: single, date_to: single };
}

function number(value) {
    const numeric = Number(value) || 0;
    return numberFormatter.format(numeric);
}

function percent(value) {
    const numeric = Number(value) || 0;
    return `${Number.isInteger(numeric) ? numeric : numeric.toFixed(2)} %`;
}

function money(value) {
    return currencyFormatter.format(Number(value) || 0).replace(/\s?₫$/, '').trim();
}

function optionLabel(option) {
    return option.label ?? option.name ?? String(option.id ?? option.value ?? '');
}

function SelectFilter({ value, onChange, placeholder, options = [] }) {
    return (
        <select className="form-control ps-operation-conversion-control" value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.id ?? option.value} value={option.id ?? option.value}>
                    {optionLabel(option)}
                </option>
            ))}
        </select>
    );
}

function buildInitialFilters() {
    const params = currentQuery();
    const fallbackToday = todayIso();
    const dateFrom = params.get('date_from') || fallbackToday;
    const dateTo = params.get('date_to') || dateFrom;

    return {
        date_type: params.get('date_type') || 'next_operation_date',
        date_from: dateFrom,
        date_to: dateTo,
        no_closing_date_limit: params.get('no_closing_date_limit') === '1',
        sale_leader_id: params.get('sale_leader_id') || '',
        sale_team_id: params.get('sale_team_id') || '',
        operation_stage: params.get('operation_stage') || '',
        sort_metric: params.get('sort_metric') || 'total_revenue',
        per_page: params.get('per_page') || '20',
    };
}

function totalsFor(rows) {
    const totals = rows.reduce((acc, row) => {
        acc.total_contacts += Number(row.total_contacts) || 0;
        acc.total_closed += Number(row.total_closed) || 0;
        acc.revenue += Number(row.revenue) || 0;
        OPERATION_STAGES.forEach(({ key }) => {
            acc[`${key}_contacts`] += Number(row[`${key}_contacts`]) || 0;
            acc[`${key}_closed`] += Number(row[`${key}_closed`]) || 0;
            acc[`${key}_revenue`] += Number(row[`${key}_revenue`]) || 0;
        });
        return acc;
    }, {
        index: 1,
        sale: '',
        total_contacts: 0,
        total_closed: 0,
        total_rate: 0,
        revenue: 0,
        ...Object.fromEntries(OPERATION_STAGES.flatMap(({ key }) => [
            [`${key}_contacts`, 0],
            [`${key}_closed`, 0],
            [`${key}_revenue`, 0],
            [`${key}_rate`, 0],
        ])),
    });

    totals.total_rate = totals.total_contacts ? (totals.total_closed / totals.total_contacts) * 100 : 0;
    OPERATION_STAGES.forEach(({ key }) => {
        totals[`${key}_rate`] = totals[`${key}_contacts`] ? (totals[`${key}_closed`] / totals[`${key}_contacts`]) * 100 : 0;
    });

    return totals;
}

function MetricCells({ row, stage }) {
    return (
        <>
            <td className="text-center nowrap">{number(row[`${stage}_contacts`])}</td>
            <td className="text-center nowrap">{number(row[`${stage}_closed`])}</td>
            <td className="text-center nowrap">{percent(row[`${stage}_rate`])}</td>
            <td className="text-center nowrap">{money(row[`${stage}_revenue`])}</td>
        </>
    );
}

function SaleCell({ row }) {
    const sale = String(row.sale ?? '').trim();
    const account = String(row.sale_account ?? '').trim();
    if (!sale && !account) return null;

    return (
        <span className="ps-operation-conversion-sale">
            <span>{sale || 'Chưa phân sale'}</span>
            {account && <small>({account})</small>}
        </span>
    );
}

function ReportRow({ row, className = '' }) {
    return (
        <tr className={className}>
            <td className="text-center">{row.index}</td>
            <td className="text-left"><SaleCell row={row} /></td>
            <td className="text-center nowrap">{number(row.total_contacts)}</td>
            <td className="text-center nowrap">{number(row.total_closed)}</td>
            <td className="text-center nowrap">{percent(row.total_rate)}</td>
            <td className="text-center nowrap">{money(row.revenue)}</td>
            {OPERATION_STAGES.map(({ key }) => <MetricCells key={key} row={row} stage={key} />)}
        </tr>
    );
}

function Pager({ meta = {}, routeUrl, filters }) {
    const current = Number(meta.current_page ?? 1);
    const last = Math.max(1, Number(meta.last_page ?? 1));
    const from = Number(meta.from ?? 0);
    const to = Number(meta.to ?? 0);
    const total = Number(meta.total ?? 0);

    const go = (page) => {
        const safePage = Math.min(Math.max(1, page), last);
        router.get(routeUrl, { ...filters, page: safePage }, { preserveScroll: false, preserveState: false, replace: true });
    };

    return (
        <div className="ps-operation-conversion-short-pager">
            <span>{from} - {to} / {total}</span>
            <button type="button" className="btn btn-default btn-sm" disabled={current <= 1} onClick={() => go(current - 1)} title="Trang trước">
                <i className="fa fa-caret-left" />
            </button>
            <button type="button" className="btn btn-default btn-sm" disabled={current >= last} onClick={() => go(current + 1)} title="Trang sau">
                <i className="fa fa-caret-right" />
            </button>
        </div>
    );
}

export default function Page({ schema, rows = [], pagination = {}, filterOptions = {}, routeUrl = '/admin/sales/reports/operation-conversion', pageRuntimeError = null }) {
    const [filters, setFilters] = useState(buildInitialFilters);
    const [dateRange, setDateRange] = useState(() => rangeLabel(filters.date_from, filters.date_to));
    const totals = useMemo(() => totalsFor(rows), [rows]);
    const cleanFilters = useMemo(() => Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== false && value !== null && value !== undefined)), [filters]);

    const set = (key, value) => setFilters((current) => ({ ...current, [key]: value }));

    const search = () => {
        const parsed = parseRange(dateRange);
        const next = {
            ...filters,
            date_from: parsed.date_from || filters.date_from || todayIso(),
            date_to: parsed.date_to || parsed.date_from || filters.date_to || todayIso(),
            no_closing_date_limit: filters.no_closing_date_limit ? '1' : '',
            page: 1,
        };
        router.get(routeUrl, Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== false && value !== null && value !== undefined)), {
            preserveScroll: false,
            preserveState: false,
            replace: true,
        });
    };

    const exportExcel = () => {
        const params = new URLSearchParams(cleanFilters);
        params.set('export', '1');
        window.location.assign(`${routeUrl}?${params.toString()}`);
    };

    return (
        <AppLayout>
            <Head title={schema?.title ?? 'Báo cáo tỉ lệ chốt đơn theo tác nghiệp'} />
            <div className="pushsale-page ps-operation-conversion-report" data-page-code="4.6.1">
                {pageRuntimeError && <div className="pushsale-error-banner"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div>}

                <div className="ps-operation-conversion-header">
                    <div className="ps-operation-conversion-title">{schema?.title ?? 'Báo cáo tỉ lệ chốt đơn theo tác nghiệp'}</div>
                    <SelectFilter value={filters.date_type} onChange={(value) => set('date_type', value)} placeholder="Ngày sale tác nghiệp tiếp" options={DATE_TYPE_OPTIONS} />
                    <input className="form-control ps-operation-conversion-date" value={dateRange} onChange={(event) => setDateRange(event.target.value)} />
                    <label className="ps-operation-conversion-check">
                        <input type="checkbox" checked={Boolean(filters.no_closing_date_limit)} onChange={(event) => set('no_closing_date_limit', event.target.checked)} />
                        <span>Không giới hạn ngày chốt</span>
                    </label>
                    <div className="ps-operation-conversion-actions">
                        <button type="button" className="btn-icon" title="Ẩn/hiện bộ lọc"><i className="fa fa-angle-double-up" /></button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={search}><i className="fa fa-search" /> Tìm kiếm</button>
                        <button type="button" className="btn btn-sm btn-primary" onClick={exportExcel}><i className="fa fa-file-excel-o" /> Xuất Excel</button>
                    </div>
                </div>

                <div className="ps-operation-conversion-filter-row">
                    <SelectFilter value={filters.sale_leader_id} onChange={(value) => set('sale_leader_id', value)} placeholder="--Trưởng nhóm--" options={filterOptions.saleLeaders ?? []} />
                    <SelectFilter value={filters.sale_team_id} onChange={(value) => set('sale_team_id', value)} placeholder="--Chọn nhóm--" options={filterOptions.saleTeams ?? filterOptions.teams ?? []} />
                    <SelectFilter value={filters.operation_stage} onChange={(value) => set('operation_stage', value)} placeholder="--Tác nghiệp--" options={OPERATION_STAGES.map(({ key, label }) => ({ id: key, label }))} />
                    <SelectFilter value={filters.sort_metric} onChange={(value) => set('sort_metric', value)} placeholder="1.Doanh số tổng" options={METRIC_OPTIONS} />
                    <SelectFilter value={filters.per_page} onChange={(value) => set('per_page', value)} placeholder="20" options={PER_PAGE_OPTIONS.map((value) => ({ id: value, label: value }))} />
                </div>

                <div className="ps-operation-conversion-pager-row">
                    <Pager meta={pagination} routeUrl={routeUrl} filters={cleanFilters} />
                </div>

                <div className="dragscroll1 tableFixHead ps-operation-conversion-table-wrap">
                    <table className="table table-bordered table-striped" id="tblData">
                        <thead>
                            <tr className="drags-area">
                                <th className="text-center" rowSpan="2">STT</th>
                                <th className="text-center" rowSpan="2">SALE</th>
                                <th className="text-center" rowSpan="2">Tổng<br />contact</th>
                                <th className="text-center" rowSpan="2">Tổng<br />chốt đơn</th>
                                <th className="text-center" rowSpan="2">Tổng<br />tỷ lệ</th>
                                <th className="text-center" rowSpan="2">Tổng doanh<br />số</th>
                                {OPERATION_STAGES.map(({ key, label }) => (
                                    <th className="text-center" key={key} colSpan="4">{label}</th>
                                ))}
                            </tr>
                            <tr className="drags-area">
                                {OPERATION_STAGES.map(({ key }) => (
                                    <Fragment key={key}>
                                        <th className="text-center">Số contact</th>
                                        <th className="text-center">Chốt đơn</th>
                                        <th className="text-center">Tỷ lệ chốt</th>
                                        <th className="text-center">Doanh số</th>
                                    </Fragment>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length > 0 && <ReportRow row={totals} className="rowTong" />}
                            {rows.map((row, index) => <ReportRow key={`${row.sale}-${row.sale_account ?? ''}-${index}`} row={{ ...row, index: (pagination.from || 1) + index }} />)}
                            {!rows.length && (
                                <tr>
                                    <td colSpan={46} className="text-center ps-operation-conversion-empty">Chưa có dữ liệu phù hợp với bộ lọc.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="ps-operation-conversion-bottom-pager">
                    <button type="button" className="btn btn-default btn-sm" disabled>«</button>
                    <button type="button" className="btn btn-primary btn-sm">{pagination.current_page ?? 1}</button>
                    <button type="button" className="btn btn-default btn-sm" disabled={Number(pagination.current_page ?? 1) >= Number(pagination.last_page ?? 1)}>»</button>
                </div>
            </div>
        </AppLayout>
    );
}
