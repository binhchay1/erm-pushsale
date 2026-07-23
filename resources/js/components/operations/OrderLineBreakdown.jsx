import { formatCurrency } from '@/lib/format';

function normalizedText(value) {
    return String(value ?? '').toLowerCase();
}

export function isUpsellOrderItem(item = {}) {
    const type = normalizedText(item.itemType ?? item.item_type ?? item.type);
    const origin = normalizedText(item.origin ?? item.source ?? item.packetType);

    return Boolean(item.isUpsell)
        || Boolean(item.is_upsell)
        || type === 'upsell'
        || type === 'upsale'
        || origin.includes('upsell')
        || origin.includes('upsale');
}

export function firstUpsellDivider(items = [], index = 0) {
    const current = items[index];
    if (!isUpsellOrderItem(current)) {
        return false;
    }

    return !items.slice(0, index).some((item) => isUpsellOrderItem(item));
}

export function OrderProductsBreakdown({ items = [], empty = '—' }) {
    if (!items.length) {
        return <span>{empty}</span>;
    }

    return (
        <div className="ps-order-products-breakdown" role="list" aria-label="Sản phẩm trong đơn">
            {items.map((item, index) => {
                const isUpsell = isUpsellOrderItem(item);
                const hasDivider = firstUpsellDivider(items, index);
                const name = item.productName ?? item.product_name ?? item.name ?? '—';
                const quantity = Number(item.quantity ?? item.qty ?? 0);
                const unitPrice = Number(item.unitPrice ?? item.unit_price ?? item.price ?? 0);

                return (
                    <div
                        key={item.itemId ?? item.id ?? `${name}-${index}`}
                        className={`ps-order-products-row ${isUpsell ? 'is-upsale-line' : 'is-main-line'} ${hasDivider ? 'has-upsale-divider' : ''}`.trim()}
                        role="listitem"
                    >
                        <span className="ps-order-product-name" title={name}>
                            {isUpsell ? (
                                <span className="ps-order-upsale-icon" title="Upsale" aria-label="Upsale">
                                    <i className="fa fa-level-up" aria-hidden="true" />
                                </span>
                            ) : null}
                            <span>{name}</span>
                        </span>
                        <span className="ps-order-product-qty">x{quantity}</span>
                        <span className="ps-order-product-price">{formatCurrency(unitPrice)}</span>
                    </div>
                );
            })}
        </div>
    );
}

export function OrderMoneyBreakdown({ row = {}, showZeroDiscount = true }) {
    const subtotal = Number(row.subtotal ?? row.sub_total ?? 0);
    const discount = Number(row.discount ?? row.discountAmount ?? 0);
    const vat = Number(row.vat ?? row.tax ?? 0);
    const shippingFee = Number(row.shippingFeeCollected ?? row.shipping_fee_collected ?? row.shippingFee ?? 0);
    const total = Number(row.total ?? 0);

    return (
        <div className="ps-order-money-breakdown" aria-label="Thành tiền đơn hàng">
            <div title="Thành tiền">{formatCurrency(subtotal)}</div>
            <div title="Chiết khấu">{discount > 0 || showZeroDiscount ? `-${formatCurrency(discount)}` : formatCurrency(0)}</div>
            <div title="VAT">{formatCurrency(vat)}</div>
            <div title="Phí vận chuyển">{formatCurrency(shippingFee)}</div>
            <strong title="Tổng tiền đơn hàng">{formatCurrency(total)}</strong>
        </div>
    );
}
