import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { useTableSort } from '@/hooks/use-table-sort';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export function SalesPerformanceTable({ rows = [] }) {
    const t = useT();
    const dataRows = rows.filter((r) => !r.isTotalRow);
    const totalRow = rows.find((r) => r.isTotalRow);
    const { sortedRows, sort, toggleSort } = useTableSort(dataRows, { defaultKey: 'saleName' });

    return (
        <ScrollDataTable>
            <table className="w-full min-w-[960px] border-collapse text-xs">
                <thead>
                    <tr>
                        <Th>{t('reports.sales_performance.stt')}</Th>
                        <Th sortable sortKey="saleName" sort={sort} onSort={toggleSort}>{t('reports.sales_performance.name')}</Th>
                        <Th sortable sortKey="totalLeads" sort={sort} onSort={toggleSort} className="text-right">{t('reports.sales_performance.total_leads')}</Th>
                        <Th sortable sortKey="actualCalls" sort={sort} onSort={toggleSort} className="text-right">{t('reports.sales_performance.calls')}</Th>
                        <Th sortable sortKey="answerRate" sort={sort} onSort={toggleSort} className="text-right">{t('reports.sales_performance.pickup_rate')}</Th>
                        <Th sortable sortKey="closedOrders" sort={sort} onSort={toggleSort} className="text-right">{t('reports.sales_performance.closed')}</Th>
                        <Th sortable sortKey="closeRate" sort={sort} onSort={toggleSort} className="text-right">{t('reports.sales_performance.closing_rate')}</Th>
                        <Th sortable sortKey="totalRevenue" sort={sort} onSort={toggleSort} className="text-right">{t('reports.sales_performance.revenue')}</Th>
                    </tr>
                </thead>
                <tbody>
                    {totalRow && (
                        <tr className="bg-muted/50 font-semibold">
                            <Td>—</Td>
                            <Td>{totalRow.saleName}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(totalRow.totalLeads)}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(totalRow.actualCalls)}</Td>
                            <Td className="text-right tabular-nums">{formatPercent(totalRow.answerRate)}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(totalRow.closedOrders)}</Td>
                            <Td className="text-right tabular-nums">{formatPercent(totalRow.closeRate)}</Td>
                            <Td className="text-right tabular-nums">{formatCurrency(totalRow.totalRevenue)}</Td>
                        </tr>
                    )}
                    {sortedRows.map((row, index) => (
                        <tr key={row.saleId} className="hover:bg-muted/30">
                            <Td>{index + 1}</Td>
                            <Td>{row.saleName}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(row.totalLeads)}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(row.actualCalls)}</Td>
                            <Td className="text-right tabular-nums">{formatPercent(row.answerRate)}</Td>
                            <Td className="text-right tabular-nums">{formatNumber(row.closedOrders)}</Td>
                            <Td className="text-right tabular-nums">{formatPercent(row.closeRate)}</Td>
                            <Td className="text-right tabular-nums">{formatCurrency(row.totalRevenue)}</Td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
