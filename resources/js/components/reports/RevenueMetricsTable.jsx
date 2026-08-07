import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { TableEmptyRow } from '@/components/reports/TableEmpty';
import { useT } from '@/providers/I18nProvider';
import { translateReportText } from '@/lib/reportI18n';

const pairColumns = [
    ['closedOrders', 'reports.revenue_metrics_table.closed_orders'],
    ['confirmedDelivery', 'reports.revenue_metrics_table.confirmed_delivery'],
    ['canceledShipping', 'reports.revenue_metrics_table.canceled_shipping'],
    ['transferredToCarrier', 'reports.revenue_metrics_table.transferred_carrier'],
    ['returned', 'reports.revenue_metrics_table.returned'],
    ['returning', 'reports.revenue_metrics_table.returning'],
    ['delivered', 'reports.revenue_metrics_table.delivered'],
    ['paid', 'reports.revenue_metrics_table.paid'],
    ['successfulDelivery', 'reports.revenue_metrics_table.successful_delivery'],
];

const singleColumns = [
    ['returnRate', 'reports.revenue_metrics_table.return_rate', 'percent'],
    ['shippingCancelRate', 'reports.revenue_metrics_table.shipping_cancel_rate', 'percent'],
    ['confirmRate', 'reports.revenue_metrics_table.confirm_rate', 'percent'],
    ['successRate', 'reports.revenue_metrics_table.success_rate', 'percent'],
    ['contacts', 'reports.revenue_metrics_table.contacts', 'number'],
    ['closingRate', 'reports.revenue_metrics_table.closing_rate', 'percent'],
    ['productCount', 'reports.revenue_metrics_table.product_count', 'number'],
    ['upsellQuantity', 'reports.revenue_metrics_table.upsale_quantity', 'number'],
    ['upsellRevenue', 'reports.revenue_metrics_table.upsale_revenue', 'currency'],
    ['upsellRevenueShare', 'reports.revenue_metrics_table.upsale_revenue_share', 'percent'],
    ['averageOrderValue', 'reports.revenue_metrics_table.average_order_value', 'currency'],
    ['revenueReturnRate', 'reports.revenue_metrics_table.revenue_return_rate', 'percent'],
    ['revenueCancelRate', 'reports.revenue_metrics_table.revenue_cancel_rate', 'percent'],
];

function pairValue(row, key) {
    return row?.[key] ?? { qty: 0, revenue: 0 };
}

function Value({ row, keyName, type, t }) {
    const value = row?.[keyName] ?? 0;
    if (keyName === 'contacts' && row?.primaryPackets !== undefined) {
        return (
            <span className="ps-contact-breakdown-inline">
                <b>{formatNumber(value)}</b>
                <small>{t('reports.columns.primary_packets')} {formatNumber(row.primaryPackets ?? 0)} · {t('reports.columns.upsale_packets')} {formatNumber(row.upsalePackets ?? 0)}</small>
            </span>
        );
    }
    if (type === 'percent') return formatPercent(value);
    if (type === 'currency') return formatCurrency(value);
    return formatNumber(value);
}

function PairSubHeads({ t }) {
    return (
        <>
            <th className="text-center ps-sales-revenue-subhead">{t('reports.pushsale.quantity')}</th>
            <th className="text-center ps-sales-revenue-subhead">{t('reports.pushsale.revenue')}</th>
        </>
    );
}

function PairCells({ pair }) {
    return (
        <>
            <td className="text-center">{formatNumber(pair?.qty ?? 0)}</td>
            <td className="text-center">{formatCurrency(pair?.revenue ?? 0)}</td>
        </>
    );
}

export function RevenueMetricsTable({ rows = [], nameKey = 'saleName', nameLabel, className = '' }) {
    const t = useT();
    const resolvedNameLabel = translateReportText(t, nameLabel, nameLabel) ?? t('reports.revenue_metrics.name');

    return (
        <div className={`ps-sales-revenue-table-wrap ${className}`.trim()}>
            <table className="table table-bordered table-striped ps-sales-revenue-table" id="tableReport">
                <thead>
                    <tr>
                        <th className="text-center" rowSpan="2">{t('reports.pushsale.stt')}</th>
                        <th className="text-center ps-sales-revenue-name-col" rowSpan="2">{resolvedNameLabel}</th>
                        {pairColumns.map(([key, labelKey]) => (
                            <th className="text-center" key={key} colSpan="2">{t(labelKey)}</th>
                        ))}
                        {singleColumns.map(([key, labelKey]) => (
                            <th className="text-center" key={key} rowSpan="2">{t(labelKey)}</th>
                        ))}
                    </tr>
                    <tr className="drags-area">
                        {pairColumns.map(([key]) => (
                            <PairSubHeads key={key} t={t} />
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <TableEmptyRow
                            colSpan={2 + pairColumns.length * 2 + singleColumns.length}
                            message={t('reports.pushsale.no_matching_filter')}
                            className="text-center ps-empty-row"
                        />
                    ) : rows.map((row) => (
                        <tr key={`${row.stt}-${row[nameKey] ?? row.id ?? 'row'}`} className={row.isTotalRow ? 'ps-sales-revenue-total-row' : ''}>
                            <td className="text-center">{row.stt}</td>
                            <td className="ps-sales-revenue-name-cell">{row[nameKey]}</td>
                            {pairColumns.map(([key]) => (
                                <PairCells key={key} pair={pairValue(row, key)} />
                            ))}
                            {singleColumns.map(([key, , type]) => (
                                <td key={key} className="text-center">
                                    <Value row={row} keyName={key} type={type} t={t} />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
