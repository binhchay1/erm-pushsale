import { Head } from '@inertiajs/react';

import AppLayout from '@/layouts/AppLayout';
import { ReportExportButton } from '@/components/reports/ReportExportButton';
import { ReportFilterBar } from '@/components/reports/ReportFilterBar';
import { StatusSummaryBar } from '@/components/reports/StatusSummaryBar';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function SalePerformanceTable({ rows = [] }) {
    const t = useT();
    const { sortedRows, sort, toggleSort } = useTableSort(rows, { defaultKey: 'saleStaffName' });

    return (
        <ScrollDataTable>
            <table className="min-w-[1200px] w-full border-collapse">
                <thead>
                    <tr>
                        <Th>{t('reports.ceo_report.stt')}</Th>
                        <Th sortable sortKey="saleStaffName" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.sale')}</Th>
                        <Th colSpan={5}>{t('reports.ceo_report.new_customers')}</Th>
                        <Th colSpan={5}>{t('reports.ceo_report.old_customers')}</Th>
                        <Th sortable sortKey="totalEstRevenue" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.total_revenue')}</Th>
                        <Th sortable sortKey="salesKpi" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.kpi')}</Th>
                        <Th sortable sortKey="achievementRate" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.kpi_pct')}</Th>
                    </tr>
                    <tr className="bg-primary/90 text-primary-foreground text-xs">
                        <Th />
                        <Th />
                        <Th sortable sortKey="newContact" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.contact')}</Th>
                        <Th sortable sortKey="newClosed" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.closed')}</Th>
                        <Th sortable sortKey="newCloseRate" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.pct')}</Th>
                        <Th sortable sortKey="newProductQty" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.products')}</Th>
                        <Th sortable sortKey="newEstRevenue" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.revenue')}</Th>
                        <Th sortable sortKey="oldContact" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.contact')}</Th>
                        <Th sortable sortKey="oldClosed" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.closed')}</Th>
                        <Th sortable sortKey="oldCloseRate" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.pct')}</Th>
                        <Th sortable sortKey="oldProductQty" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.products')}</Th>
                        <Th sortable sortKey="oldEstRevenue" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.revenue')}</Th>
                        <Th />
                        <Th />
                        <Th />
                    </tr>
                </thead>
                <tbody>
                    {sortedRows.map((r, index) => (
                        <tr key={r.saleStaffId} className="hover:bg-muted/30">
                            <Td>{index + 1}</Td>
                            <Td>
                                {r.saleStaffName}
                                <span className="text-muted-foreground"> ({r.saleUsername})</span>
                            </Td>
                            <Td>{formatNumber(r.newContact)}</Td>
                            <Td>{formatNumber(r.newClosed)}</Td>
                            <Td>{formatPercent(r.newCloseRate)}</Td>
                            <Td>{formatNumber(r.newProductQty)}</Td>
                            <Td>{formatCurrency(r.newEstRevenue)}</Td>
                            <Td>{formatNumber(r.oldContact)}</Td>
                            <Td>{formatNumber(r.oldClosed)}</Td>
                            <Td>{formatPercent(r.oldCloseRate)}</Td>
                            <Td>{formatNumber(r.oldProductQty)}</Td>
                            <Td>{formatCurrency(r.oldEstRevenue)}</Td>
                            <Td className="font-semibold text-emerald-700 dark:text-emerald-400">{formatCurrency(r.totalEstRevenue)}</Td>
                            <Td>{formatCurrency(r.salesKpi)}</Td>
                            <Td>{formatPercent(r.achievementRate)}</Td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}

function MarketingTable({ rows = [] }) {
    const t = useT();
    const { sortedRows, sort, toggleSort } = useTableSort(rows, { defaultKey: 'marketerName' });

    return (
        <ScrollDataTable>
            <table className="w-full min-w-[640px] border-collapse">
                <thead>
                    <tr>
                        <Th>{t('reports.ceo_report.stt')}</Th>
                        <Th sortable sortKey="marketerName" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.marketing')}</Th>
                        <Th sortable sortKey="budget" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.budget')}</Th>
                        <Th sortable sortKey="contactPrice" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.contact_price')}</Th>
                        <Th sortable sortKey="budgetRevenueRatioNew" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.budget_new_pct')}</Th>
                        <Th sortable sortKey="budgetRevenueRatioTotal" sort={sort} onSort={toggleSort}>{t('reports.ceo_report.budget_total_pct')}</Th>
                    </tr>
                </thead>
                <tbody>
                    {sortedRows.map((r, index) => (
                        <tr key={r.marketerId} className="hover:bg-muted/30">
                            <Td>{index + 1}</Td>
                            <Td>{r.marketerName}</Td>
                            <Td>{formatCurrency(r.budget)}</Td>
                            <Td>{formatCurrency(r.contactPrice)}</Td>
                            <Td>{formatPercent(r.budgetRevenueRatioNew)}</Td>
                            <Td>{formatPercent(r.budgetRevenueRatioTotal)}</Td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}

export default function CeoReport({ filters, filterOptions, report, routeUrl = '/admin/reports/ceo' }) {
    const t = useT();

    return (
        <AppLayout>
            <Head title={t('reports.ceo_report.title')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">{t('reports.ceo_report.title')}</h1>
                    <p className="text-sm text-muted-foreground">{t('reports.ceo_report.desc')}</p>
                </div>

                <div className="flex flex-wrap items-end justify-between gap-3">
                    <ReportFilterBar
                        routeUrl={routeUrl}
                        filters={filters}
                        filterOptions={filterOptions}
                    />
                    <ReportExportButton routeUrl={routeUrl} filters={filters} />
                </div>

                <StatusSummaryBar summary={report.statusSummary} />

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t('reports.ceo_report.sale_section')}</h2>
                    <SalePerformanceTable rows={report.saleRows ?? []} />
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">{t('reports.ceo_report.marketing_section')}</h2>
                    <MarketingTable rows={report.marketingRows ?? []} />
                </section>
            </div>
        </AppLayout>
    );
}

