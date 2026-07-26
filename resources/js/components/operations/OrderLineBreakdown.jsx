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

export function orderHasUpsell(order = {}, items = null) {
    const productItems = items ?? order.products ?? order.items ?? [];

    return Boolean(order.isSupplementalOrder)
        || Boolean(order.awaitingLandingUpsell)
        || Number(order.pendingSupplementCount ?? 0) > 0
        || productItems.some((item) => isUpsellOrderItem(item));
}

export function firstUpsellDivider(items = [], index = 0) {
    const current = items[index];
    if (!isUpsellOrderItem(current)) {
        return false;
    }

    return !items.slice(0, index).some((item) => isUpsellOrderItem(item));
}

export function OrderStatusFlags({ row = {}, order = null, onDuplicate = null, className = '', showUpsell = true }) {
    const data = row ?? order ?? {};
    const hasDuplicate = Boolean(data.isDuplicatePhone);
    const hasReturning = Boolean(data.isReturningCustomer);
    const hasUpsell = showUpsell && orderHasUpsell(data);
    const isWaitingUpsell = Boolean(data.awaitingLandingUpsell) || Number(data.pendingSupplementCount ?? 0) > 0;

    if (!hasDuplicate && !hasReturning && !hasUpsell) {
        return null;
    }

    const duplicateIcon = (
        <span className="ps-order-flag-inner">
            <i className="fa fa-clone" aria-hidden="true" />
        </span>
    );

    return (
        <span className={`ps-order-flags ${className}`.trim()} aria-label="Dấu hiệu khách hàng và đơn hàng">
            {hasReturning ? (
                <span className="ps-order-flag is-returning" title="Khách hàng cũ" aria-label="Khách hàng cũ">
                    <i className="fa fa-heart" aria-hidden="true" />
                </span>
            ) : null}
            {hasDuplicate ? (
                onDuplicate ? (
                    <button type="button" className="ps-order-flag is-duplicate" title="Trùng số điện thoại" aria-label="Trùng số điện thoại" onClick={onDuplicate}>
                        {duplicateIcon}
                    </button>
                ) : (
                    <span className="ps-order-flag is-duplicate" title="Trùng số điện thoại" aria-label="Trùng số điện thoại">
                        {duplicateIcon}
                    </span>
                )
            ) : null}
            {hasUpsell ? (
                <span className={`ps-order-flag is-upsale ${isWaitingUpsell ? 'is-waiting-upsale' : ''}`.trim()} title={isWaitingUpsell ? 'Có upsale đang chờ xử lý' : 'Có đơn upsale'} aria-label={isWaitingUpsell ? 'Có upsale đang chờ xử lý' : 'Có đơn upsale'}>
                    <i className="fa fa-level-up" aria-hidden="true" />
                </span>
            ) : null}
        </span>
    );
}

function ProductLine({ item, index, forceUpsell = false }) {
    const isUpsell = forceUpsell || isUpsellOrderItem(item);
    const name = item.productName ?? item.product_name ?? item.name ?? '—';
    const quantity = Number(item.quantity ?? item.qty ?? 0);
    const unitPrice = Number(item.unitPrice ?? item.unit_price ?? item.price ?? 0);

    return (
        <div
            key={item.itemId ?? item.id ?? `${name}-${index}`}
            className={`ps-order-products-row ${isUpsell ? 'is-upsale-line' : 'is-main-line'}`.trim()}
            role="listitem"
        >
            <span className="ps-order-product-name" title={name}>
                <span>{name}</span>
            </span>
            <span className="ps-order-product-qty">x{quantity}</span>
            <span className="ps-order-product-price">{formatCurrency(unitPrice)}</span>
        </div>
    );
}

function ProductSection({ label, type = 'main', items = [], forceUpsell = false }) {
    if (!items.length) {
        return null;
    }

    return (
        <div className={`ps-order-products-section is-${type}-section`.trim()}>
            {label ? <div className="ps-order-products-section-label">{label}</div> : null}
            {items.map((item, index) => <ProductLine key={item.itemId ?? item.id ?? `${type}-${index}`} item={item} index={index} forceUpsell={forceUpsell} />)}
        </div>
    );
}

export function OrderProductsBreakdown({ items = [], order = null, empty = '—' }) {
    if (!items.length) {
        return <span>{empty}</span>;
    }

    const hasExplicitUpsellLine = items.some((item) => isUpsellOrderItem(item));
    const forceWholeOrderUpsell = Boolean(order?.isSupplementalOrder) && !hasExplicitUpsellLine;
    const mainItems = forceWholeOrderUpsell ? [] : items.filter((item) => !isUpsellOrderItem(item));
    const upsellItems = forceWholeOrderUpsell ? items : items.filter((item) => isUpsellOrderItem(item));
    const hasBothSections = mainItems.length > 0 && upsellItems.length > 0;

    return (
        <div className="ps-order-products-breakdown" role="list" aria-label="Sản phẩm trong đơn">
            <ProductSection label={hasBothSections ? 'Đơn chính' : ''} type="main" items={mainItems} />
            <ProductSection label={hasBothSections || forceWholeOrderUpsell ? 'Upsale' : ''} type="upsale" items={upsellItems} forceUpsell={forceWholeOrderUpsell} />
            {order?.awaitingLandingUpsell ? <div className="ps-order-products-pending-upsale">Đang chờ upsale</div> : null}
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
