import { Head } from '@inertiajs/react';
import { useMemo } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { ReportExportControl } from '@/components/reports/ReportExportControl';
import { PushsaleDateRange, PushsaleSearchButton } from '@/components/reports/PushsaleReportChrome';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import { readQueryFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout';

const numberFormatter = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
const currencyFormatter = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 });

function today() {
    return new Date().toISOString().slice(0, 10);
}

function toDate(value) {
    if (!value) return today();
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);
    return date.toISOString().slice(0, 10);
}

function buildInitialFilters(serverFilters = {}) {
    return readQueryFilters({
        date_from: toDate(serverFilters.date_from),
        date_to: toDate(serverFilters.date_to),
        search: '',
    });
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

function SystemReport85Shell({
    title,
    routeUrl,
    showExport = false,
    filters: serverFilters = {},
    pageRuntimeError,
    activeMenuCode,
    className = 'ps85-page',
    children,
}) {
    const { draft, set, apply } = useInertiaFilters(routeUrl, buildInitialFilters(serverFilters), {
        sync: false,
        preserveScroll: true,
        replace: false,
    });

    const primaryFilters = (
        <div className="ps85-filter-row">
            <PushsaleDateRange filters={draft} onChange={set} />
            <input
                className="form-control"
                placeholder="Tìm theo tên / mã"
                value={draft.search}
                onChange={(event) => set('search', event.target.value)}
            />
        </div>
    );

    const actions = (
        <>
            <PushsaleSearchButton onClick={() => apply()} label="Tìm kiếm" />
            {showExport ? (
                <ReportExportControl mode="visit" routeUrl={routeUrl} filters={draft} label="Xuất Excel" />
            ) : null}
        </>
    );

    return (
        <PushsalePageShell
            title={title}
            pageCode={activeMenuCode}
            className={className}
            primaryFilters={primaryFilters}
            actions={actions}
            notice={pageRuntimeError ? <ErrorBanner message={pageRuntimeError} /> : null}
        >
            {children}
        </PushsalePageShell>
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
            <SystemReport85Shell
                title={schema.title ?? 'Báo cáo'}
                routeUrl={routeUrl}
                filters={summary.filters ?? {}}
                pageRuntimeError={pageRuntimeError}
                activeMenuCode={activeMenuCode}
            >
                <SummaryCards summary={summary} />
                <DataTable columns={columns} rows={rows} />
            </SystemReport85Shell>
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
            <SystemReport85Shell
                title={schema.title ?? 'Biểu đồ xu hướng'}
                routeUrl={routeUrl}
                filters={summary.filters ?? {}}
                pageRuntimeError={pageRuntimeError}
                activeMenuCode={activeMenuCode}
                className="ps85-page ps85-trend-page"
            >
                <div className="ps85-chart-grid">
                    {rows.map((row) => <TrendChart key={row.period} row={row} />)}
                </div>
                <DataTable columns={tableColumns} rows={rows} compact />
            </SystemReport85Shell>
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
            <SystemReport85Shell
                title={schema.title ?? 'Thống kê mua lại'}
                routeUrl={routeUrl}
                filters={summary.filters ?? {}}
                showExport
                pageRuntimeError={pageRuntimeError}
                activeMenuCode={activeMenuCode}
                className="ps85-page ps85-repurchase-page"
            >
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
            </SystemReport85Shell>
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
            <SystemReport85Shell
                title={schema.title ?? 'Thống kê KH mua lại theo số sản phẩm'}
                routeUrl={routeUrl}
                filters={summary.filters ?? {}}
                showExport
                pageRuntimeError={pageRuntimeError}
                activeMenuCode={activeMenuCode}
                className="ps85-page ps85-repurchase-products-page"
            >
                <DataTable columns={columns} rows={rows} compact />
            </SystemReport85Shell>
        </AppLayout>
    );
}

export default SimpleReport;
