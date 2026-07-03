import { Head, Link } from '@inertiajs/react';
import { BarChart3 } from 'lucide-react';

import { PageHeader } from '@/components/layout/PageHeader';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { ReportRefreshButton } from '@/components/reports/ReportRefreshButton';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useLabels } from '@/hooks/use-labels';
import { useTableSort } from '@/hooks/use-table-sort';
import AppLayout from '@/layouts/AppLayout';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useT } from '@/providers/I18nProvider';

function formatCell(value, format) {
    if (value === null || value === undefined || value === '') return '—';

    switch (format) {
        case 'currency':
            return formatCurrency(value);
        case 'number':
            return formatNumber(value);
        case 'percent':
            return formatPercent(value);
        default:
            return value;
    }
}

function percentToneClass(value, tone) {
    const v = Number(value);
    if (Number.isNaN(v)) return '';

    const good = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400';
    const warn = 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
    const bad = 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400';

    if (tone === 'negative') {
        if (v <= 5) return good;
        if (v <= 15) return warn;
        return bad;
    }

    if (v >= 50) return good;
    if (v >= 25) return warn;
    return bad;
}

function CellValue({ value, column }) {
    if (column.format === 'percent' && column.tone && value !== null && value !== undefined) {
        return (
            <span
                className={cn(
                    'inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold tabular-nums',
                    percentToneClass(value, column.tone),
                )}
            >
                {formatPercent(value)}
            </span>
        );
    }

    return formatCell(value, column.format);
}

function resolveColumnLabel(col, t, labels) {
    if (col.label_type === 'operation_stage' && col.label_key) {
        return labels.operation_stage?.[col.label_key] ?? col.label;
    }

    if (col.label_key) {
        const translated = t(`reports.columns.${col.label_key}`);
        if (translated !== `reports.columns.${col.label_key}`) {
            return translated;
        }
    }

    return col.label;
}

function reportText(t, key, field, fallback) {
    const path = `reports.extra.${key}.${field}`;
    const translated = t(path);

    return translated !== path ? translated : fallback;
}

export default function ExtraReport({
    meta,
    reportNav = [],
    columns = [],
    rows = [],
    totals = null,
    filters,
    filterOptions,
    filterFields = [],
    routeUrl,
    cachedAt,
}) {
    const t = useT();
    const labels = useLabels();
    const hasFilters = filterFields.length > 0;
    const useCache = ['sale-3', 'marketing-1', 'marketing-2', 'marketing-3', 'marketing-4'].includes(meta.key);
    const { sortedRows, sort, toggleSort } = useTableSort(rows);

    const title = reportText(t, meta.key, 'title', meta.title);
    const description = reportText(t, meta.key, 'description', meta.description);

    return (
        <AppLayout>
            <Head title={title} />

            <div className="space-y-6">
                <PageHeader
                    icon={BarChart3}
                    title={title}
                    description={description}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <ReportExportButton routeUrl={routeUrl} filters={filters} />
                            {useCache && (
                                <ReportRefreshButton routeUrl={routeUrl} filters={filters} cachedAt={cachedAt} />
                            )}
                        </div>
                    }
                />

                {reportNav.length > 1 && (
                    <div className="flex flex-wrap gap-2">
                        {reportNav.map((item) => (
                            <Link
                                key={item.key}
                                href={item.url}
                                className={cn(
                                    'rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors',
                                    item.key === meta.key
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-card text-muted-foreground hover:bg-muted',
                                )}
                            >
                                {reportText(t, item.key, 'title', item.title)}
                            </Link>
                        ))}
                    </div>
                )}

                {hasFilters && (
                    <ReportFilterBar
                        routeUrl={routeUrl}
                        filters={filters}
                        filterOptions={filterOptions}
                        filterFields={filterFields}
                    />
                )}

                <ScrollDataTable>
                    <table className="w-full min-w-max text-sm">
                        <thead>
                            <tr>
                                <Th className="w-10 text-center">{t('pages.stt')}</Th>
                                {columns.map((col) => (
                                    <Th
                                        key={col.key}
                                        sortable
                                        sortKey={col.key}
                                        sort={sort}
                                        onSort={toggleSort}
                                        className={col.format === 'text' ? '' : 'text-right'}
                                    >
                                        {resolveColumnLabel(col, t, labels)}
                                    </Th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <Td
                                        colSpan={columns.length + 1}
                                        className="py-10 text-center text-muted-foreground"
                                    >
                                        {t('reports.empty_period')}
                                    </Td>
                                </tr>
                            )}
                            {sortedRows.map((row, index) => (
                                <tr key={index}>
                                    <Td className="text-center text-muted-foreground">
                                        {index + 1}
                                    </Td>
                                    {columns.map((col) => (
                                        <Td
                                            key={col.key}
                                            className={cn(
                                                col.format === 'text'
                                                    ? 'font-medium'
                                                    : 'text-right tabular-nums',
                                                col.format === 'currency' && 'text-emerald-700 dark:text-emerald-400',
                                            )}
                                        >
                                            <CellValue value={row[col.key]} column={col} />
                                        </Td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                        {totals && rows.length > 0 && (
                            <tfoot>
                                <tr className="border-t-2 border-border bg-muted/60 font-semibold">
                                    <Td className="text-center">—</Td>
                                    {columns.map((col, index) => (
                                        <Td
                                            key={col.key}
                                            className={cn(
                                                'font-semibold',
                                                col.format !== 'text' && 'text-right tabular-nums',
                                            )}
                                        >
                                            {index === 0 && col.format === 'text'
                                                ? t('common.grand_total')
                                                : formatCell(totals[col.key], col.format)}
                                        </Td>
                                    ))}
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </ScrollDataTable>
            </div>
        </AppLayout>
    );
}
