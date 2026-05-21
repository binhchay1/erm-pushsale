import { MetricPairCell } from '@/components/reports/MetricPairCell';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';

const metricColumns = [
    { key: 'closedOrders', label: 'Đơn chốt (1)', pair: true },
    { key: 'confirmedDelivery', label: 'XNGH (2)', pair: true },
    { key: 'canceledShipping', label: 'Hủy VĐ (3)', pair: true },
    { key: 'transferredToCarrier', label: 'Chuyển ĐVGH (4)', pair: true },
    { key: 'returned', label: 'Đã hoàn (5)', pair: true },
    { key: 'returning', label: 'Đang hoàn (6)', pair: true },
    { key: 'delivered', label: 'Đã giao (7)', pair: true },
    { key: 'paid', label: 'Đã TT (8)', pair: true },
    { key: 'successfulDelivery', label: 'GH thành công (9)', pair: true },
    { key: 'returnRate', label: '% Hoàn (10)', percent: true },
    { key: 'shippingCancelRate', label: '% Hủy VĐ (11)', percent: true },
    { key: 'confirmRate', label: '% XNGH (12)', percent: true },
    { key: 'successRate', label: '% GH TC (13)', percent: true },
    { key: 'contacts', label: 'Contact (14)' },
    { key: 'closingRate', label: 'Tỷ lệ chốt (15)', percent: true },
    { key: 'productCount', label: 'Số SP (16)' },
    { key: 'averageOrderValue', label: 'Giá trị đơn (17)', currency: true },
    { key: 'revenueReturnRate', label: '% DS hoàn (18)', percent: true },
    { key: 'revenueCancelRate', label: '% DS hủy (19)', percent: true },
];

function CellValue({ row, col }) {
    const v = row[col.key];
    if (col.pair) return <MetricPairCell pair={v} />;
    if (col.percent) return formatPercent(v);
    if (col.currency) return formatCurrency(v);
    return formatNumber(v);
}

export function RevenueMetricsTable({ rows, nameKey = 'saleName', nameLabel = 'Tên' }) {
    return (
        <ScrollDataTable>
            <table className="min-w-[2400px] w-full border-collapse">
                <thead>
                    <tr>
                        <Th>STT</Th>
                        <Th>{nameLabel}</Th>
                        {metricColumns.map((c) => (
                            <Th key={c.key}>{c.label}</Th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows?.map((row) => (
                        <tr
                            key={row.stt + row[nameKey]}
                            className={row.isTotalRow ? 'bg-muted/50 font-semibold' : 'hover:bg-muted/30'}
                        >
                            <Td>{row.stt}</Td>
                            <Td className="font-medium">{row[nameKey]}</Td>
                            {metricColumns.map((c) => (
                                <Td key={c.key}>
                                    <CellValue row={row} col={c} />
                                </Td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </ScrollDataTable>
    );
}
