import { Head } from '@inertiajs/react';
import { useMemo } from 'react';

import { PushsalePageShell } from '@/components/layout/PushsalePageShell';
import { ReportExportControl } from '@/components/reports/ReportExportControl';
import { PushsaleDateRange, PushsaleSearchButton } from '@/components/reports/PushsaleReportChrome';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import { readQueryFilters, useInertiaFilters } from '@/hooks/useInertiaFilters';
import AppLayout from '@/layouts/AppLayout';
import { translateReportColumns, translateReportText } from '@/lib/reportI18n';
import { useT } from '@/providers/I18nProvider';

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

function format(t, value, fmt = 'text') {
    if (value === null || value === undefined || value === '') return '—';
    if (fmt === 'currency' || fmt === 'money') return currencyFormatter.format(Number(value) || 0);
    if (fmt === 'percent') return `${numberFormatter.format(Number(value) || 0)}%`;
    if (fmt === 'number' || fmt === 'decimal') return numberFormatter.format(Number(value) || 0);
    if (fmt === 'boolean') return value ? t('reports.pushsale.yes') : t('reports.pushsale.no');
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
    const t = useT();
    const resolvedTitle = translateReportText(t, title, title);
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
                placeholder={t('reports.pushsale.search_name_code')}
                value={draft.search}
                onChange={(event) => set('search', event.target.value)}
            />
        </div>
    );

    const actions = (
        <>
            <PushsaleSearchButton onClick={() => apply()} />
            {showExport ? (
                <ReportExportControl mode="visit" routeUrl={routeUrl} filters={draft} />
            ) : null}
        </>
    );

    return (
        <PushsalePageShell
            title={resolvedTitle}
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
    const t = useT();
    const entries = Object.entries(summary).filter(([, value]) => typeof value === 'number' || typeof value === 'string');
    if (!entries.length) return null;
    return (
        <div className="ps85-summary-cards">
            {entries.slice(0, 6).map(([key, value]) => (
                <div className="ps85-summary-card" key={key}>
                    <span>{translateReportText(t, key.replaceAll('_', ' '), key.replaceAll('_', ' '))}</span>
                    <b>{format(t, value, typeof value === 'number' ? 'number' : 'text')}</b>
                </div>
            ))}
        </div>
    );
}

export function DataTable({ columns = [], rows = [], compact = false }) {
    const t = useT();
    const safeColumns = columns.length ? columns : Object.keys(rows[0] ?? {}).map((key) => ({ key, label: key, format: 'text' }));
    return (
        <div className="ps85-table-wrap">
            <table className={`ps85-table ${compact ? 'is-compact' : ''}`}>
                <thead>
                    <tr>
                        {safeColumns.map((column) => <th key={column.key}>{translateReportText(t, column.label || column.key, column.label || column.key)}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {rows.length ? rows.map((row, index) => (
                        <tr key={row._record_id ?? index}>
                            {safeColumns.map((column) => <td key={column.key}>{format(t, row[column.key], column.format)}</td>)}
                        </tr>
                    )) : (
                        <TableEmptyRow colSpan={safeColumns.length || 1} message={t('reports.pushsale.no_data')} className="ps85-empty" />
                    )}
                </tbody>
            </table>
        </div>
    );
}

function SimpleReport({ schema = {}, rows = [], summary = {}, routeUrl, activeMenuCode, pageRuntimeError }) {
    const t = useT();
    const columns = translateReportColumns(t, schema.columns ?? []);
    const title = translateReportText(t, schema.title ?? t('reports.pushsale.report'), schema.title ?? t('reports.pushsale.report'));
    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={title} />
            <SystemReport85Shell
                title={title}
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
    const t = useT();
    const values = Array.from({ length: 7 }, (_, i) => Number(row[`day_${6 - i}_value`] ?? 0));
    const max = Math.max(1, ...values.map((value) => Math.abs(value)));
    const points = values.map((value, index) => {
        const x = 30 + index * 95;
        const y = 180 - (Math.abs(value) / max) * 130;
        return `${x},${y}`;
    }).join(' ');

    return (
        <div className="ps85-chart-card">
            <div className="ps85-chart-title">{t('reports.runtime.trend.value_title').replace('{period}', row.period)}</div>
            <svg viewBox="0 0 640 220" role="img" aria-label={t('reports.runtime.trend.chart_aria').replace('{period}', row.period)}>
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
    const t = useT();
    const title = translateReportText(t, schema.title ?? t('reports.runtime.trend.title'), schema.title ?? t('reports.runtime.trend.title'));
    const tableColumns = useMemo(() => [
        { key: 'period', label: t('reports.runtime.trend.period'), format: 'text' },
        ...Array.from({ length: 7 }, (_, i) => ({ key: `day_${6 - i}_value`, label: t('reports.runtime.trend.day_n').replace('{day}', 6 - i), format: 'number' })),
    ], [t]);

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={title} />
            <SystemReport85Shell
                title={title}
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
    const t = useT();
    const title = translateReportText(t, schema.title ?? t('reports.runtime.repurchase.title'), schema.title ?? t('reports.runtime.repurchase.title'));
    const columns = [
        { key: 'index', label: t('reports.pushsale.stt'), format: 'number' },
        { key: 'metric', label: t('reports.runtime.repurchase.metric'), format: 'text' },
        { key: 'purchase_1', label: t('reports.runtime.repurchase.purchase_1'), format: 'number' },
        { key: 'purchase_2', label: t('reports.runtime.repurchase.purchase_2'), format: 'number' },
        { key: 'purchase_3', label: t('reports.runtime.repurchase.purchase_3'), format: 'number' },
        { key: 'purchase_n', label: t('reports.runtime.repurchase.purchase_n'), format: 'number' },
    ];

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={title} />
            <SystemReport85Shell
                title={title}
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
                            <thead><tr><th>{t('reports.pushsale.stt')}</th><th>{t('reports.runtime.repurchase.purchase_times')}</th><th>{t('reports.runtime.repurchase.view_customers')}</th></tr></thead>
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
    const t = useT();
    const title = translateReportText(t, schema.title ?? t('reports.runtime.repurchase.products_title'), schema.title ?? t('reports.runtime.repurchase.products_title'));
    const columns = useMemo(() => [
        { key: 'purchase_no', label: t('reports.runtime.repurchase.purchase_no'), format: 'text' },
        ...Array.from({ length: 30 }, (_, i) => ({ key: `product_${i + 1}`, label: t('reports.runtime.repurchase.buy_n_products').replace('{count}', i + 1), format: 'number' })),
    ], [t]);

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title={title} />
            <SystemReport85Shell
                title={title}
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
