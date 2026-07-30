import { Head, usePage } from '@inertiajs/react';

import {
    PushsaleDateRange,
    PushsaleSearchButton,
    PushsaleSelect,
    usePushsaleFilters,
} from '@/components/reports/PushsaleReportChrome';
import AppLayout from '@/layouts/AppLayout';
import { formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

const HOURS = Array.from({ length: 24 }, (_, hour) => hour);

function psText(t, key, fallback) {
    const value = t(key);
    return value === key ? fallback : value;
}

function normalizeChartRows(rows = []) {
    const byHour = new Map(rows.map((row) => [Number(row.hour), row]));

    return HOURS.map((hour) => {
        const row = byHour.get(hour) ?? {};
        return {
            hour,
            label: row.label ?? `${hour}h`,
            contacts: Number(row.contacts ?? 0),
            closed: Number(row.closed ?? 0),
            revenue: Number(row.revenue ?? 0),
            rate: row.rate === null || row.rate === undefined ? 0 : Number(row.rate),
        };
    });
}

function colorFor(metric, value, maxValue) {
    const safeValue = Number(value) || 0;
    if (safeValue <= 0) {
        return '#fff';
    }

    const ratio = metric === 'rate'
        ? Math.min(1, Math.max(0, safeValue / 100))
        : Math.min(1, Math.max(0, safeValue / Math.max(1, maxValue)));

    if (metric === 'closed') {
        const lightness = Math.round(94 - ratio * 42);
        const saturation = Math.round(54 + ratio * 20);
        return `hsl(146, ${saturation}%, ${lightness}%)`;
    }

    if (metric === 'contacts') {
        const lightness = Math.round(95 - ratio * 38);
        const saturation = Math.round(54 + ratio * 18);
        return `hsl(215, ${saturation}%, ${lightness}%)`;
    }

    const lightness = Math.round(96 - ratio * 40);
    const saturation = Math.round(62 + ratio * 18);
    return `hsl(0, ${saturation}%, ${lightness}%)`;
}

function formatCell(metric, value) {
    if (metric === 'rate') {
        if (!Number.isFinite(Number(value))) return '0';
        return Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
    }

    return formatNumber(value ?? 0);
}

function HeatmapChart({ title, rows, metric, dayLabel, className = '' }) {
    const maxValue = Math.max(1, ...rows.map((row) => Number(row[metric]) || 0));
    const legendMax = metric === 'rate' ? 100 : maxValue;
    const legendSteps = metric === 'rate'
        ? [0, 20, 40, 60, 80, 100]
        : [0, Math.round(legendMax * 0.25), Math.round(legendMax * 0.5), Math.round(legendMax * 0.75), legendMax];

    return (
        <div className={`ps-hourly-chart ${className}`.trim()}>
            <button type="button" className="ps-hourly-chart-menu" aria-label="Biểu đồ">
                <i className="fa fa-bars" aria-hidden="true" />
            </button>
            <h2>{title}</h2>
            <div className="ps-hourly-chart-body">
                <div className="ps-hourly-day-label">{dayLabel}</div>
                <div className="ps-hourly-heatmap" role="table" aria-label={title}>
                    {rows.map((row) => {
                        const value = row[metric] ?? 0;
                        return (
                            <div
                                key={`${metric}-${row.hour}`}
                                className="ps-hourly-cell"
                                role="cell"
                                title={`${dayLabel} thời điểm ${row.hour}h: ${formatCell(metric, value)}`}
                                style={{ backgroundColor: colorFor(metric, value, maxValue) }}
                            >
                                {formatCell(metric, value)}
                            </div>
                        );
                    })}
                </div>
                <div className={`ps-hourly-legend ps-hourly-legend-${metric}`} aria-hidden="true">
                    <div className="ps-hourly-legend-bar" />
                    <div className="ps-hourly-legend-labels">
                        {legendSteps.map((step, index) => (
                            <span key={`${metric}-legend-${index}`}>{formatNumber(step)}</span>
                        ))}
                    </div>
                </div>
            </div>
            <div className="ps-hourly-xaxis">
                <span />
                {rows.map((row) => <span key={`${metric}-axis-${row.hour}`}>{row.hour}h</span>)}
                <span />
            </div>
        </div>
    );
}

function PersonFilter({ draft, set, filterFields, filterOptions }) {
    const fields = new Set(filterFields ?? []);

    if (fields.has('sale_id')) {
        return (
            <PushsaleSelect
                value={draft.sale_id ?? ''}
                placeholder="-- Chọn sale --"
                options={filterOptions.salesUsers ?? filterOptions.sales ?? []}
                onChange={(value) => set('sale_id', value)}
                className="ps-hourly-sale-select"
            />
        );
    }

    return (
        <PushsaleSelect
            value={draft.marketer_id ?? ''}
            placeholder="-- Chọn marketing --"
            options={filterOptions.marketingUsers ?? filterOptions.marketers ?? []}
            onChange={(value) => set('marketer_id', value)}
            className="ps-hourly-sale-select"
        />
    );
}

export default function HourlyStats({
    rows = [],
    totals = {},
    filters = {},
    filterOptions = {},
    filterFields = [],
    routeUrl,
    dayLabel = 'Tổng',
}) {
    const t = useT();
    const title = psText(t, 'reports.hourly.title', 'Biểu đồ thống kê theo khung giờ');
    const { draft, set, apply } = usePushsaleFilters(routeUrl, filters);
    const chartRows = normalizeChartRows(rows);

    return (
        <AppLayout>
            <Head title={title} />
            <section className="ps-hourly-report ps-report-page">
                <div className="ps-hourly-header-wrap">
                    <div className="ps-hourly-header">
                        <h1>{title}</h1>
                    </div>
                </div>

                <div className="ps-hourly-filter-box box">
                    <div className="box-body">
                        <div className="ps-hourly-filter-row">
                            <PersonFilter
                                draft={draft}
                                set={set}
                                filterFields={filterFields}
                                filterOptions={filterOptions}
                            />
                            <PushsaleDateRange filters={draft} onChange={set} className="ps-hourly-date-range" />
                            <div className="ps-hourly-action-wrap">
                                <PushsaleSearchButton onClick={() => apply()} />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="ps-hourly-chart-box box">
                    <div className="box-body">
                        <HeatmapChart
                            title="THỐNG KÊ TỶ LỆ CHỐT ĐƠN"
                            rows={chartRows}
                            metric="rate"
                            dayLabel={dayLabel}
                            className="ps-hourly-chart-main"
                        />

                        <div className="ps-hourly-chart-grid">
                            <HeatmapChart title="THỐNG KÊ SỐ CHỐT ĐƠN" rows={chartRows} metric="closed" dayLabel={dayLabel} />
                            <HeatmapChart title="THỐNG KÊ SỐ CONTACT" rows={chartRows} metric="contacts" dayLabel={dayLabel} />
                        </div>
                    </div>
                </div>

                <div className="ps-hourly-summary">
                    <span>Contact: <strong>{formatNumber(totals.contacts ?? 0)}</strong></span>
                    <span>Chốt đơn: <strong>{formatNumber(totals.closed ?? 0)}</strong></span>
                    <span>Doanh số: <strong>{formatNumber(totals.revenue ?? 0)} ₫</strong></span>
                </div>
            </section>
        </AppLayout>
    );
}
