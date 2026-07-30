import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { ReportExportControl } from '@/components/reports/ReportExportControl';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import AppLayout from '@/layouts/AppLayout';

const numberFormatter = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
const currencyFormatter = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 });

function q() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function toDate(value) {
    if (!value) return today();
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);
    return date.toISOString().slice(0, 10);
}

function format(value, fmt = 'text') {
    if (value === null || value === undefined || value === '') return '—';
    if (fmt === 'currency' || fmt === 'money') return currencyFormatter.format(Number(value) || 0);
    if (fmt === 'percent') return `${numberFormatter.format(Number(value) || 0)}%`;
    if (fmt === 'number' || fmt === 'decimal') return numberFormatter.format(Number(value) || 0);
    if (fmt === 'boolean') return value ? 'Có' : 'Không';
    return String(value);
}

function ErrorBanner({ message }) {
    if (!message) return null;
    return <div className="ps85-error"><i className="fa fa-exclamation-triangle" /> {message}</div>;
}

function SearchHeader({ title, routeUrl, showExport = false, filters = {}, pageRuntimeError }) {
    const query = q();
    const [draft, setDraft] = useState({
        date_from: toDate(query.date_from || filters.date_from),
        date_to: toDate(query.date_to || filters.date_to),
        search: query.search || '',
    });
    const update = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const submit = () => router.get(routeUrl, draft, { preserveScroll: true });

    return (
        <div className="ps85-header-block">
            <div className="ps85-title">{title}</div>
            <div className="ps85-filter-row">
                <input className="form-control" type="date" value={draft.date_from} onChange={(event) => update('date_from', event.target.value)} />
                <input className="form-control" type="date" value={draft.date_to} onChange={(event) => update('date_to', event.target.value)} />
                <input className="form-control" placeholder="Tìm theo tên / mã" value={draft.search} onChange={(event) => update('search', event.target.value)} />
                <button type="button" className="btn btn-primary btn-sm" onClick={submit}><i className="fa fa-search" /> Tìm kiếm</button>
                {showExport ? (
                    <ReportExportControl mode="visit" routeUrl={routeUrl} filters={draft} label="Xuất Excel" />
                ) : null}
            </div>
            <ErrorBanner message={pageRuntimeError} />
        </div>
    );
}

function SummaryCards({ summary = {} }) {
    const entries = Object.entries(summary).filter(([, value]) => typeof value === 'number' || typeof value === 'string');
    if (!entries.length) return null;
    return (
        <div className="ps85-summary-cards">
            {entries.slice(0, 6).map(([key, value]) => (
                <div className="ps85-summary-card" key={key}>
                    <span>{key.replaceAll('_', ' ')}</span>
                    <b>{format(value, typeof value === 'number' ? 'number' : 'text')}</b>
                </div>
            ))}
        </div>
    );
}

export function DataTable({ columns = [], rows = [], compact = false }) {
    const safeColumns = columns.length ? columns : Object.keys(rows[0] ?? {}).map((key) => ({ key, label: key, format: 'text' }));
    return (
        <div className="ps85-table-wrap">
            <table className={`ps85-table ${compact ? 'is-compact' : ''}`}>
                <thead>
                    <tr>
                        {safeColumns.map((column) => <th key={column.key}>{column.label || column.key}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {rows.length ? rows.map((row, index) => (
                        <tr key={row._record_id ?? index}>
                            {safeColumns.map((column) => <td key={column.key}>{format(row[column.key], column.format)}</td>)}
                        </tr>
                    )) : (
                        <TableEmptyRow colSpan={safeColumns.length || 1} message="Không có dữ liệu." className="ps85-empty" />
                    )}
                </tbody>
            </table>
        </div>
    );
}

function SimpleReport({ schema = {}, rows = [], summary = {}, routeUrl, activeMenuCode, pageRuntimeError }) {
    const columns = schema.columns ?? [];
    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={schema.title ?? 'Báo cáo'} />
            <div className="ps85-page">
                <SearchHeader title={schema.title ?? 'Báo cáo'} routeUrl={routeUrl} filters={summary.filters ?? {}} pageRuntimeError={pageRuntimeError} />
                <SummaryCards summary={summary} />
                <DataTable columns={columns} rows={rows} />
            </div>
        </AppLayout>
    );
}

function TrendChart({ row }) {
    const values = Array.from({ length: 7 }, (_, i) => Number(row[`day_${6 - i}_value`] ?? 0));
    const max = Math.max(1, ...values.map((value) => Math.abs(value)));
    const points = values.map((value, index) => {
        const x = 30 + index * 95;
        const y = 180 - (Math.abs(value) / max) * 130;
        return `${x},${y}`;
    }).join(' ');

    return (
        <div className="ps85-chart-card">
            <div className="ps85-chart-title">Giá trị {row.period}</div>
            <svg viewBox="0 0 640 220" role="img" aria-label={`Biểu đồ ${row.period}`}>
                <line x1="30" y1="180" x2="610" y2="180" className="axis" />
                <polyline points={points} className="line" />
                {points.split(' ').map((point, index) => {
                    const [x, y] = point.split(',');
                    return <circle key={index} cx={x} cy={y} r="4" className="point" />;
                })}
            </svg>
        </div>
    );
}

export function TrendReport({ schema = {}, rows = [], summary = {}, routeUrl, activeMenuCode, pageRuntimeError }) {
    const tableColumns = useMemo(() => [
        { key: 'period', label: 'Thời gian', format: 'text' },
        ...Array.from({ length: 7 }, (_, i) => ({ key: `day_${6 - i}_value`, label: `Ngày n-${6 - i}`, format: 'number' })),
    ], []);

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={schema.title ?? 'Biểu đồ xu hướng'} />
            <div className="ps85-page ps85-trend-page">
                <SearchHeader title={schema.title ?? 'Biểu đồ xu hướng'} routeUrl={routeUrl} filters={summary.filters ?? {}} pageRuntimeError={pageRuntimeError} />
                <div className="ps85-chart-grid">
                    {rows.map((row) => <TrendChart key={row.period} row={row} />)}
                </div>
                <DataTable columns={tableColumns} rows={rows} compact />
            </div>
        </AppLayout>
    );
}

export function RepurchaseReport({ schema = {}, rows = [], summary = {}, routeUrl, activeMenuCode, pageRuntimeError }) {
    const columns = [
        { key: 'index', label: 'STT', format: 'number' },
        { key: 'metric', label: 'Chỉ số', format: 'text' },
        { key: 'purchase_1', label: 'Mua 1 Lần', format: 'number' },
        { key: 'purchase_2', label: 'Mua 2 Lần', format: 'number' },
        { key: 'purchase_3', label: 'Mua 3 Lần', format: 'number' },
        { key: 'purchase_n', label: 'Mua >= 4 Lần', format: 'number' },
    ];

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={schema.title ?? 'Thống kê mua lại'} />
            <div className="ps85-page ps85-repurchase-page">
                <SearchHeader title={schema.title ?? 'Thống kê mua lại'} routeUrl={routeUrl} filters={summary.filters ?? {}} showExport pageRuntimeError={pageRuntimeError} />
                <div className="ps85-repurchase-layout">
                    <DataTable columns={columns} rows={rows} />
                    <div className="ps85-side-card">
                        <table className="ps85-table is-compact">
                            <thead><tr><th>STT</th><th>Số lần mua</th><th>Xem danh sách khách</th></tr></thead>
                            <tbody>
                                {[1, 2, 3, 4].map((times) => <tr key={times}><td>{times}</td><td>{times === 4 ? '>= 4' : times}</td><td><i className="fa fa-search" /></td></tr>)}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

export function RepurchaseProductsReport({ schema = {}, rows = [], summary = {}, routeUrl, activeMenuCode, pageRuntimeError }) {
    const columns = useMemo(() => [
        { key: 'purchase_no', label: 'Lần mua', format: 'text' },
        ...Array.from({ length: 30 }, (_, i) => ({ key: `product_${i + 1}`, label: `Mua ${i + 1} SP`, format: 'number' })),
    ], []);

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={schema.title ?? 'Thống kê KH mua lại theo số sản phẩm'} />
            <div className="ps85-page ps85-repurchase-products-page">
                <SearchHeader title={schema.title ?? 'Thống kê KH mua lại theo số sản phẩm'} routeUrl={routeUrl} filters={summary.filters ?? {}} showExport pageRuntimeError={pageRuntimeError} />
                <DataTable columns={columns} rows={rows} compact />
            </div>
        </AppLayout>
    );
}

export default SimpleReport;
