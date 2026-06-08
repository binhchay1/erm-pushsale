import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';

export function SalesPerformanceTable({ rows = [] }) {
    return (
        <ScrollDataTable>
            <table className="w-full min-w-[960px] border-collapse text-xs">
                <thead>
                    <tr>
                        <Th>STT</Th>
                        <Th>Tên Sales</Th>
                        <Th className="text-right">Tổng lead nhận</Th>
                        <Th className="text-right">Cuộc gọi thực tế</Th>
                        <Th className="text-right">Tỷ lệ bắt máy</Th>
                        <Th className="text-right">Đơn chốt</Th>
                        <Th className="text-right">Tỷ lệ chốt</Th>
                        <Th className="text-right">Doanh thu</Th>
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
