import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

import { PushsaleDateRange } from '@/components/reports/PushsaleReportChrome';
import AppLayout from '@/layouts/AppLayout';

const numberFormatter = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
const moneyFormatter = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 });

function currentQuery() {
    if (typeof window === 'undefined') return {};
    return Object.fromEntries(new URLSearchParams(window.location.search).entries());
}

function todayString() {
    return new Date().toISOString().slice(0, 10);
}

function formatDateInput(value) {
    if (!value) return todayString();
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);
    return date.toISOString().slice(0, 10);
}

function formatNumber(value, format = 'number') {
    const number = Number(value ?? 0);
    if (format === 'percent') return `${numberFormatter.format(number)}%`;
    if (format === 'money' || format === 'money_short') return moneyFormatter.format(number);
    if (format === 'money_decimal') return numberFormatter.format(number);
    if (format === 'decimal') return numberFormatter.format(number);
    return numberFormatter.format(number);
}

function formatDelta(value) {
    const number = Number(value ?? 0);
    return `${number > 0 ? '' : ''}${numberFormatter.format(number)}%`;
}

function deltaClass(value) {
    const number = Number(value ?? 0);
    if (number > 0) return 'is-up';
    if (number < 0) return 'is-down';
    return 'is-flat';
}

function SummaryCard({ card }) {
    const delta = Number(card.delta ?? 0);
    const tone = card.tone === 'up' ? 'is-up' : card.tone === 'down' ? 'is-down' : 'is-primary';
    return (
        <div className={`ps-power-card ${tone}`}>
            <div className="ps-power-card-content">
                <div className="ps-power-card-title">{card.title}</div>
                <div className="ps-power-card-value">{formatNumber(card.value, card.format)}</div>
            </div>
            {card.delta !== null && card.delta !== undefined ? (
                <div className="ps-power-card-badge">
                    <i className={`fa ${delta >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'}`} />
                    <span>{formatDelta(delta)}</span>
                </div>
            ) : null}
        </div>
    );
}

const PANEL_COLUMNS = {
    marketing: [
        ['contacts', 'Số contact', 'number'],
        ['cost_per_contact', 'Giá contact', 'money_decimal'],
        ['budget_ratio', 'Ngân sách/DS', 'percent'],
        ['revenue', 'Doanh số', 'money'],
    ],
    telesale: [
        ['contacts', 'Số contact', 'number'],
        ['closed', 'Số đơn chốt', 'number'],
        ['close_rate', 'Tỉ lệ chốt', 'percent'],
        ['products_per_order', 'Số sp/đơn', 'decimal'],
        ['revenue', 'Doanh số', 'money'],
    ],
    shipping: [
        ['success_rate', 'Tỉ lệ TC', 'percent'],
        ['revenue', 'Doanh số', 'money'],
    ],
    care: [
        ['close_rate', 'Tỉ lệ chốt', 'percent'],
        ['closed', 'Số đơn', 'number'],
        ['products_per_order', 'Số sp/đơn', 'decimal'],
        ['revenue', 'Doanh số', 'money'],
    ],
};

const PANEL_TITLES = {
    marketing: 'MARKETING',
    telesale: 'TELESALE',
    shipping: 'GIAO HÀNG',
    care: 'CHĂM SÓC KHÁCH HÀNG',
};

function MetricCell({ row, column }) {
    const [key, , format] = column;
    return (
        <td className="ps-power-panel-metric">
            <span className="ps-power-panel-value">{formatNumber(row[key], format)}</span>
            <span className={`ps-power-panel-delta ${deltaClass(row[`${key}_delta`])}`}>{formatDelta(row[`${key}_delta`])}</span>
        </td>
    );
}

function PowerPanel({ type, rows = [] }) {
    const columns = PANEL_COLUMNS[type] ?? [];
    return (
        <div className={`ps-power-panel ps-power-panel-${type}`}>
            <div className="ps-power-panel-heading">{PANEL_TITLES[type]}</div>
            <div className="ps-power-panel-body">
                <table className="ps-power-panel-table">
                    <thead>
                        <tr>
                            <th />
                            {columns.map(([key, label]) => <th key={key}>{label}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length ? rows.map((row, index) => (
                            <tr key={`${type}-${row.label}-${index}`} className={index < 2 ? 'is-summary' : ''}>
                                <td className="ps-power-panel-name">{row.label}</td>
                                {columns.map((column) => <MetricCell key={column[0]} row={row} column={column} />)}
                            </tr>
                        )) : (
                            <tr><td colSpan={columns.length + 1} className="ps-power-empty">Không có dữ liệu.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function MatrixCell({ day, format }) {
    return (
        <>
            <td className="ps-power-matrix-value">{formatNumber(day.value, format)}</td>
            <td className={`ps-power-matrix-delta ${deltaClass(day.previous_delta)}`}>{formatDelta(day.previous_delta)}</td>
            <td className={`ps-power-matrix-delta ${deltaClass(day.average_delta)}`}>{formatDelta(day.average_delta)}</td>
        </>
    );
}

function MatrixTable({ days = [], rows = [] }) {
    const sectionRowSpan = useMemo(() => rows.reduce((acc, row) => {
        acc[row.section] = (acc[row.section] ?? 0) + 1;
        return acc;
    }, {}), [rows]);
    const seen = {};

    return (
        <div className="ps-power-matrix-wrap">
            <table className="ps-power-matrix-table">
                <thead>
                    <tr>
                        <th className="ps-power-section-col" />
                        <th className="ps-power-metric-col" />
                        <th>Tổng</th>
                        <th>Trung bình</th>
                        {days.map((day) => <th key={day.key} colSpan="3">{day.label}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => {
                        const first = !seen[row.section];
                        seen[row.section] = true;
                        return (
                            <tr key={`${row.section}-${row.metric}-${index}`}>
                                {first ? (
                                    <td rowSpan={sectionRowSpan[row.section]} className={`ps-power-section-label section-${row.section.toLowerCase().replace(/\s+/g, '-')}`}>
                                        <span>{row.section}</span>
                                    </td>
                                ) : null}
                                <td className="ps-power-metric-name">{row.metric}</td>
                                <td className="ps-power-matrix-total">{formatNumber(row.total, row.format)}</td>
                                <td>{formatNumber(row.average, row.format)}</td>
                                {(row.days ?? []).map((day) => <MatrixCell key={day.key} day={day} format={row.format} />)}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

export default function PowerDashboardPage({ summary = {}, rows = [], routeUrl = '/admin/reports/power-dashboard', activeMenuCode = '8.5.9', pageRuntimeError = null }) {
    const query = currentQuery();
    const filters = summary.filters ?? {};
    const [draft, setDraft] = useState({
        mode: query.mode || filters.mode || 'day',
        date_from: formatDateInput(query.date_from || filters.date_from),
        date_to: formatDateInput(query.date_to || filters.date_to),
    });

    const updateDraft = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
    const runSearch = () => router.get(routeUrl, draft, { preserveScroll: true });

    const topCards = summary.top_cards ?? [];
    const panels = summary.panels ?? {};
    const matrixDays = summary.days ?? [];
    const matrixRows = summary.matrix_rows ?? rows;

    return (
        <AppLayout activeMenuCode={activeMenuCode}>
            <Head title="Power dashboard" />
            <div className="ps-power-dashboard-page">
                <div className="ps-power-filter-bar">
                    <select className="form-control" value={draft.mode} onChange={(event) => updateDraft('mode', event.target.value)}>
                        <option value="day">Ngày</option>
                        <option value="week">Tuần</option>
                        <option value="month">Tháng</option>
                    </select>
                    <PushsaleDateRange
                        filters={draft}
                        onChange={(key, value) => updateDraft(key, value)}
                    />
                    <div className="ps-power-cards">
                        {topCards.map((card) => <SummaryCard key={card.title} card={card} />)}
                    </div>
                    <button type="button" className="btn btn-sm btn-primary ps-power-search" onClick={runSearch}>
                        <i className="fa fa-search" /> Tìm kiếm
                    </button>
                </div>

                {pageRuntimeError ? (
                    <div className="ps-power-runtime-error"><i className="fa fa-exclamation-triangle" /> {pageRuntimeError}</div>
                ) : null}

                <div className="ps-power-panels-grid">
                    <PowerPanel type="marketing" rows={panels.marketing ?? []} />
                    <PowerPanel type="telesale" rows={panels.telesale ?? []} />
                    <PowerPanel type="shipping" rows={panels.shipping ?? []} />
                    <PowerPanel type="care" rows={panels.care ?? []} />
                </div>

                <MatrixTable days={matrixDays} rows={matrixRows} />
            </div>
        </AppLayout>
    );
}
