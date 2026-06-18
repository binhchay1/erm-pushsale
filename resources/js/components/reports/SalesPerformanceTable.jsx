import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

export function SalesPerformanceTable({ rows = [] }) {
    const t = useT();

    return (
        <ScrollDataTable>
            <table className="w-full min-w-[960px] border-collapse text-xs">
                <thead>
                    <tr>
                        <Th>{t('reports.sales_performance.stt')}</Th>
                        <Th>{t('reports.sales_performance.name')}</Th>
                        <Th className="text-right">{t('reports.sales_performance.total_leads')}</Th>
                        <Th className="text-right">{t('reports.sales_performance.calls')}</Th>
                        <Th className="text-right">{t('reports.sales_performance.pickup_rate')}</Th>
                        <Th className="text-right">{t('reports.sales_performance.closed')}</Th>
                        <Th className="text-right">{t('reports.sales_performance.closing_rate')}</Th>
                        <Th className="text-right">{t('reports.sales_performance.revenue')}</Th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr
                            key={row.saleId}
                            className={row.isTotalRow ? 'bg-muted/50 font-semibold' : 'hover:bg-muted/30'}
                        >
                            <Td>{row.isTotalRow ? '—' : row.stt}</Td>
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
