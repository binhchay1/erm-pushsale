import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { PageHeader } from '@/components/layout/PageHeader';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { StatusBadge } from '@/components/ui/status-badge';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency, formatNumber } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function rateTone(rate) {
    if (rate >= 80) return 'success';
    if (rate >= 50) return 'warning';
    return 'danger';
}

function AllocationTable({ data }) {
    const t = useT();
    const rows = data?.rows ?? [];
    const totals = data?.totals;
    const { sortedRows, sort, toggleSort } = useTableSort(rows, { defaultKey: 'date', defaultDir: 'desc' });

    return (
        <ScrollDataTable>
            <table className="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <Th sortable sortKey="date" sort={sort} onSort={toggleSort}>{t('pages.allocator_reports.col_date')}</Th>
                        <Th sortable sortKey="total" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_total')}</Th>
                        <Th sortable sortKey="assigned" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_assigned')}</Th>
                        <Th sortable sortKey="pending" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_pending')}</Th>
                        <Th sortable sortKey="duplicate" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_duplicate')}</Th>
                        <Th sortable sortKey="failed" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_failed')}</Th>
                        <Th sortable sortKey="allocation_rate" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_rate')}</Th>
                    </tr>
                </thead>
                <tbody>
                    {totals && (
                        <tr className="bg-muted/50 font-semibold">
                            <Td>{t('pages.allocator_reports.total')}</Td>
                            <Td className="text-right">{formatNumber(totals.total)}</Td>
                            <Td className="text-right">{formatNumber(totals.assigned)}</Td>
                            <Td className="text-right">{formatNumber(totals.pending)}</Td>
                            <Td className="text-right">{formatNumber(totals.duplicate)}</Td>
                            <Td className="text-right">{formatNumber(totals.failed)}</Td>
                            <Td className="text-right">{totals.allocation_rate}%</Td>
                        </tr>
                    )}
                    {sortedRows.length ? (
                        sortedRows.map((row) => (
                            <tr key={row.date} className="hover:bg-muted/30">
                                <Td>{row.date}</Td>
                                <Td className="text-right font-medium">{formatNumber(row.total)}</Td>
                                <Td className="text-right text-emerald-600 dark:text-emerald-400">{formatNumber(row.assigned)}</Td>
                                <Td className="text-right text-amber-600 dark:text-amber-400">{formatNumber(row.pending)}</Td>
                                <Td className="text-right text-muted-foreground">{formatNumber(row.duplicate)}</Td>
                                <Td className="text-right text-red-600 dark:text-red-400">{formatNumber(row.failed)}</Td>
                                <Td className="text-right">
                                    <StatusBadge tone={rateTone(row.allocation_rate)}>{row.allocation_rate}%</StatusBadge>
                                </Td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <Td colSpan={7} className="py-8 text-center text-muted-foreground">
                                {t('pages.allocator_reports.empty')}
                            </Td>
                        </tr>
                    )}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}

function LoadTable({ data }) {
    const t = useT();
    const rows = data?.rows ?? [];
    const totals = data?.totals;
    const { sortedRows, sort, toggleSort } = useTableSort(rows, { defaultKey: 'sale_name' });

    return (
        <ScrollDataTable>
            <table className="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <Th sortable sortKey="sale_name" sort={sort} onSort={toggleSort}>{t('pages.allocator_reports.col_sale')}</Th>
                        <Th sortable sortKey="received" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_received')}</Th>
                        <Th sortable sortKey="closed" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_closed')}</Th>
                        <Th sortable sortKey="conversion" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_conversion')}</Th>
                        <Th sortable sortKey="revenue" sort={sort} onSort={toggleSort} className="text-right">{t('pages.allocator_reports.col_revenue')}</Th>
                    </tr>
                </thead>
                <tbody>
                    {totals && (
                        <tr className="bg-muted/50 font-semibold">
                            <Td>{t('pages.allocator_reports.total')}</Td>
                            <Td className="text-right">{formatNumber(totals.received)}</Td>
                            <Td className="text-right">{formatNumber(totals.closed)}</Td>
                            <Td className="text-right">{totals.conversion}%</Td>
                            <Td className="text-right">{formatCurrency(totals.revenue)}</Td>
                        </tr>
                    )}
                    {sortedRows.length ? (
                        sortedRows.map((row) => (
                            <tr key={row.sale_id} className="hover:bg-muted/30">
                                <Td className="font-medium">{row.sale_name}</Td>
                                <Td className="text-right">{formatNumber(row.received)}</Td>
                                <Td className="text-right text-emerald-600 dark:text-emerald-400">{formatNumber(row.closed)}</Td>
                                <Td className="text-right">
                                    <StatusBadge tone={rateTone(row.conversion)}>{row.conversion}%</StatusBadge>
                                </Td>
                                <Td className="text-right">{formatCurrency(row.revenue)}</Td>
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <Td colSpan={5} className="py-8 text-center text-muted-foreground">
                                {t('pages.allocator_reports.empty')}
                            </Td>
                        </tr>
                    )}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}

export default function AllocatorReports({ report, data, filters, filterFields, filterOptions, routeUrl }) {
    const t = useT();
    const isLoad = report === 'load';
    const exportUrl = routeUrl ?? `/allocator/reports/${report}`;

    return (
        <AppLayout>
            <Head title={t(isLoad ? 'pages.allocator_reports.load_title' : 'pages.allocator_reports.allocation_title')} />

            <div className="space-y-6">
                <PageHeader
                    title={t(isLoad ? 'pages.allocator_reports.load_title' : 'pages.allocator_reports.allocation_title')}
                    description={t(isLoad ? 'pages.allocator_reports.load_desc' : 'pages.allocator_reports.allocation_desc')}
                />

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <ReportFilterBar
                        routeUrl={exportUrl}
                        filters={filters}
                        filterOptions={filterOptions}
                        filterFields={filterFields}
                    />
                    <ReportExportButton routeUrl={exportUrl} filters={filters} />
                </div>

                {isLoad ? <LoadTable data={data} /> : <AllocationTable data={data} />}
            </div>
        </AppLayout>
    );
}
