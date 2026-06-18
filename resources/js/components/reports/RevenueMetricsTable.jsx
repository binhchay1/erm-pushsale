import { MetricPairCell } from '@/components/reports/MetricPairCell';
import { ScrollDataTable, Td, Th } from '@/components/reports/ScrollDataTable';
import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

function useMetricColumns() {
    const t = useT();

    return [
        { key: 'closedOrders', label: t('reports.revenue_metrics_table.closed_orders'), pair: true },
        { key: 'confirmedDelivery', label: t('reports.revenue_metrics_table.confirmed_delivery'), pair: true },
        { key: 'canceledShipping', label: t('reports.revenue_metrics_table.canceled_shipping'), pair: true },
        { key: 'transferredToCarrier', label: t('reports.revenue_metrics_table.transferred_carrier'), pair: true },
        { key: 'returned', label: t('reports.revenue_metrics_table.returned'), pair: true },
        { key: 'returning', label: t('reports.revenue_metrics_table.returning'), pair: true },
        { key: 'delivered', label: t('reports.revenue_metrics_table.delivered'), pair: true },
        { key: 'paid', label: t('reports.revenue_metrics_table.paid'), pair: true },
        { key: 'successfulDelivery', label: t('reports.revenue_metrics_table.successful_delivery'), pair: true },
        { key: 'returnRate', label: t('reports.revenue_metrics_table.return_rate'), percent: true },
        { key: 'shippingCancelRate', label: t('reports.revenue_metrics_table.shipping_cancel_rate'), percent: true },
        { key: 'confirmRate', label: t('reports.revenue_metrics_table.confirm_rate'), percent: true },
        { key: 'successRate', label: t('reports.revenue_metrics_table.success_rate'), percent: true },
        { key: 'contacts', label: t('reports.revenue_metrics_table.contacts') },
        { key: 'closingRate', label: t('reports.revenue_metrics_table.closing_rate'), percent: true },
        { key: 'productCount', label: t('reports.revenue_metrics_table.product_count') },
        { key: 'averageOrderValue', label: t('reports.revenue_metrics_table.average_order_value'), currency: true },
        { key: 'revenueReturnRate', label: t('reports.revenue_metrics_table.revenue_return_rate'), percent: true },
        { key: 'revenueCancelRate', label: t('reports.revenue_metrics_table.revenue_cancel_rate'), percent: true },
    ];
}

function CellValue({ row, col }) {
    const v = row[col.key];
    if (col.pair) return <MetricPairCell pair={v} />;
    if (col.percent) return formatPercent(v);
    if (col.currency) return formatCurrency(v);
    return formatNumber(v);
}

export function RevenueMetricsTable({ rows, nameKey = 'saleName', nameLabel }) {
    const t = useT();
    const metricColumns = useMetricColumns();
    const resolvedNameLabel = nameLabel ?? t('reports.revenue_metrics.name');

    return (
        <ScrollDataTable>
            <table className="min-w-[2400px] w-full border-collapse">
                <thead>
                    <tr>
                        <Th>{t('reports.revenue_metrics_table.stt')}</Th>
                        <Th>{resolvedNameLabel}</Th>
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
