import { formatCurrency, formatNumber, formatPercent } from '@/lib/format';
import { useT } from '@/providers/I18nProvider';

const pairColumns = [
    ['closedOrders', 'ĐƠN CHỐT (1)'],
    ['confirmedDelivery', 'XÁC NHẬN GIAO HÀNG (2)'],
    ['canceledShipping', 'HỦY VẬN ĐƠN (3)'],
    ['transferredToCarrier', 'CHUYỂN ĐVGH (4)'],
    ['returned', 'ĐÃ HOÀN (5)'],
    ['returning', 'ĐANG HOÀN (6)'],
    ['delivered', 'ĐÃ GIAO HÀNG (7)'],
    ['paid', 'ĐÃ THANH TOÁN (8)'],
    ['successfulDelivery', 'GIAO THÀNH CÔNG (9)'],
];

const singleColumns = [
    ['returnRate', '% ĐÃ HOÀN (10)', 'percent'],
    ['shippingCancelRate', '% HỦY (11)', 'percent'],
    ['confirmRate', '% XNGH (12)', 'percent'],
    ['successRate', '% GH Thành công (13)', 'percent'],
    ['contacts', 'Contact (14)', 'number'],
    ['closingRate', 'Tỷ lệ chốt (%) (15)', 'percent'],
    ['productCount', 'Số sản phẩm (16)', 'number'],
    ['upsellQuantity', 'Upsale (SL)', 'number'],
    ['upsellRevenue', 'Upsale (DS)', 'currency'],
    ['upsellRevenueShare', '% DS upsale', 'percent'],
    ['averageOrderValue', 'Giá trị đơn (17)', 'currency'],
    ['revenueReturnRate', '% DS ĐÃ HOÀN (18)', 'percent'],
    ['revenueCancelRate', '% DS HỦY (19)', 'percent'],
];

function pairValue(row, key) {
    return row?.[key] ?? { qty: 0, revenue: 0 };
}

function Value({ row, keyName, type }) {
    const value = row?.[keyName] ?? 0;
    if (type === 'percent') return formatPercent(value);
    if (type === 'currency') return formatCurrency(value);
    return formatNumber(value);
}

function PairSubHeads() {
    return (
        <>
            <th className="text-center ps-sales-revenue-subhead">Số lượng</th>
            <th className="text-center ps-sales-revenue-subhead">Doanh số</th>
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
    const resolvedNameLabel = nameLabel ?? t('reports.revenue_metrics.name');

    return (
        <div className={`ps-sales-revenue-table-wrap ${className}`.trim()}>
            <table className="table table-bordered table-striped ps-sales-revenue-table" id="tableReport">
                <thead>
                    <tr>
                        <th className="text-center" rowSpan="2">STT</th>
                        <th className="text-center ps-sales-revenue-name-col" rowSpan="2">{resolvedNameLabel}</th>
                        {pairColumns.map(([key, label]) => (
                            <th className="text-center" key={key} colSpan="2">{label}</th>
                        ))}
                        {singleColumns.map(([key, label]) => (
                            <th className="text-center" key={key} rowSpan="2">{label}</th>
                        ))}
                    </tr>
                    <tr className="drags-area">
                        {pairColumns.map(([key]) => (
                            <PairSubHeads key={key} />
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.length === 0 ? (
                        <tr>
                            <td className="text-center ps-empty-row" colSpan={2 + pairColumns.length * 2 + singleColumns.length}>
                                Không có dữ liệu theo điều kiện lọc.
                            </td>
                        </tr>
                    ) : rows.map((row) => (
                        <tr key={`${row.stt}-${row[nameKey] ?? row.id ?? 'row'}`} className={row.isTotalRow ? 'ps-sales-revenue-total-row' : ''}>
                            <td className="text-center">{row.stt}</td>
                            <td className="ps-sales-revenue-name-cell">{row[nameKey]}</td>
                            {pairColumns.map(([key]) => (
                                <PairCells key={key} pair={pairValue(row, key)} />
                            ))}
                            {singleColumns.map(([key, , type]) => (
                                <td key={key} className="text-center">
                                    <Value row={row} keyName={key} type={type} />
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
