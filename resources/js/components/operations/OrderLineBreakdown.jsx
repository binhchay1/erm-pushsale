import { formatNumber } from '@/lib/format';

function formatOpsMoney(value) {
    if (value == null || Number.isNaN(Number(value))) return '';
    return `${formatNumber(value)} đ`;
}

function normalizedText(value) {
    return String(value ?? '').toLowerCase();
}

function lineQuantity(item = {}) {
    const raw = Number(item.quantity ?? item.qty ?? 1);

    return Number.isFinite(raw) && raw > 0 ? raw : 1;
}

function parseMoneyToken(amount, unit) {
    const rawAmount = String(amount ?? '').replace(',', '.');
    const n = Number(rawAmount);
    if (!Number.isFinite(n) || n <= 0) return 0;
    const u = String(unit ?? '').toLowerCase();
    if (u === 'k' || u === 'nghìn' || u === 'nghin' || u === 'ngàn' || u === 'ngan') {
        return Math.round(n * 1000);
    }
    if (u === 'tr' || u === 'triệu' || u === 'trieu') {
        return Math.round(n * 1_000_000);
    }

    return Math.round(n);
}

/** Parse "649k" / "169.000đ" from landing labels; skip ship-fee tokens like "30k Phí Ship". */
function parseEmbeddedUnitPrice(label) {
    const text = String(label ?? '');
    if (!text) return 0;

    const re = /([0-9][0-9.,]*)\s*(k|nghìn|nghin|ngàn|ngan|đ|vnđ|vnd|tr|triệu|trieu)\b/giu;
    let match;
    while ((match = re.exec(text)) !== null) {
        const token = match[0];
        const idx = match.index;
        const before = text.slice(Math.max(0, idx - 16), idx);
        const after = text.slice(idx + token.length, idx + token.length + 16);
        const around = `${before}${after}`;
        const looksLikeShipFee = /(?:phí\s*)?(?:ship|vc|vận\s*chuyển|van\s*chuyen)/iu.test(after)
            || /(?:phí\s*)?(?:ship|vc|vận\s*chuyển|van\s*chuyen)\s*:?\s*$/iu.test(before);
        if (looksLikeShipFee && !/miễn\s*phí/iu.test(around)) {
            continue;
        }

        const parsed = parseMoneyToken(match[1], match[2]);
        if (parsed > 0) return parsed;
    }

    return 0;
}

function decodeProductName(raw) {
    const value = String(raw ?? '').trim();
    if (!value) return '—';
    try {
        const decoded = decodeURIComponent(value.replace(/\+/g, ' '));
        if (decoded && decoded !== value) return decoded.trim() || value;
    } catch {
        // keep raw
    }
    // Tracking / hash dumps are not product names
    if (/^[A-Za-z0-9+/=_-]{48,}$/.test(value) || /^[0-9a-f]{40,}$/i.test(value)) {
        return 'Sản phẩm (chưa map)';
    }

    return value;
}

function lineUnitPrice(item = {}, order = null) {
    const stored = Math.max(0, Number(item.unitPrice ?? item.unit_price ?? item.price ?? 0));
    if (stored > 0) return stored;

    const name = item.productName ?? item.product_name ?? item.name ?? '';
    const fromLabel = parseEmbeddedUnitPrice(name);
    if (fromLabel > 0) return fromLabel;

    const products = order?.products ?? order?.items ?? null;
    if (Array.isArray(products) && products.length === 1) {
        const orderTotal = Number(order?.total ?? order?.subtotal ?? 0);
        const qty = lineQuantity(item);
        if (orderTotal > 0 && qty > 0) {
            return Math.round(orderTotal / qty);
        }
    }

    return 0;
}

function productsSubtotal(items = [], order = null) {
    return items.reduce((sum, item) => sum + (lineQuantity(item) * lineUnitPrice(item, order)), 0);
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

export function OrderStatusFlags({ row = {}, order = null, onDuplicate = null, onReturning = null, className = '', showUpsell = true }) {
    const data = row ?? order ?? {};
    const hasDuplicate = Boolean(data.isDuplicatePhone);
    const hasReturning = Boolean(data.isReturningCustomer);
    const hasUpsell = showUpsell && orderHasUpsell(data);
    const isWaitingUpsell = Boolean(data.awaitingLandingUpsell) || Number(data.pendingSupplementCount ?? 0) > 0;

    if (!hasDuplicate && !hasReturning && !hasUpsell) {
        return null;
    }

    return (
        <span className={`ps-order-flags ${className}`.trim()} aria-label="Dấu hiệu khách hàng và đơn hàng">
            {hasReturning ? (
                onReturning ? (
                    <button type="button" className="ps-order-flag is-returning" title="Khách hàng cũ — xem đơn đã chốt" aria-label="Khách hàng cũ — xem đơn đã chốt" onClick={onReturning}>
                        <span className="ps-order-flag-inner"><i className="fa fa-heart" aria-hidden="true" /></span>
                    </button>
                ) : (
                    <span className="ps-order-flag is-returning" title="Khách hàng cũ" aria-label="Khách hàng cũ">
                        <span className="ps-order-flag-inner"><i className="fa fa-heart" aria-hidden="true" /></span>
                    </span>
                )
            ) : null}
            {hasDuplicate ? (
                onDuplicate ? (
                    <button type="button" className="ps-order-flag is-duplicate" title="Trùng số điện thoại" aria-label="Trùng số điện thoại" onClick={onDuplicate}>
                        <span className="ps-order-flag-inner"><i className="fa fa-clone" aria-hidden="true" /></span>
                    </button>
                ) : (
                    <span className="ps-order-flag is-duplicate" title="Trùng số điện thoại" aria-label="Trùng số điện thoại">
                        <span className="ps-order-flag-inner"><i className="fa fa-clone" aria-hidden="true" /></span>
                    </span>
                )
            ) : null}
            {hasUpsell ? (
                <span className={`ps-order-flag is-upsale ${isWaitingUpsell ? 'is-waiting-upsale' : ''}`.trim()} title={isWaitingUpsell ? 'Có upsale đang chờ xử lý' : 'Có đơn upsale'} aria-label={isWaitingUpsell ? 'Có upsale đang chờ xử lý' : 'Có đơn upsale'}>
                    <span className="ps-order-flag-inner"><i className="fa fa-level-up" aria-hidden="true" /></span>
                </span>
            ) : null}
        </span>
    );
}

function ProductLine({ item, index, order = null, forceUpsell = false, showUpsellDivider = false }) {
    const isUpsell = forceUpsell || isUpsellOrderItem(item);
    const rawName = item.productName ?? item.product_name ?? item.name ?? '—';
    const looksLikeUrl = /^https?:\/\//i.test(String(rawName)) || /^www\./i.test(String(rawName));
    const name = looksLikeUrl ? 'Sản phẩm (chưa map)' : decodeProductName(rawName);
    const quantity = lineQuantity(item);
    const unitPrice = lineUnitPrice(item, order);
    const textOnly = Boolean(item.meta?.text_only ?? item.textOnly)
        || (!item.productId && !item.product_id && unitPrice <= 0 && !parseEmbeddedUnitPrice(rawName));

    return (
        <div
            className={`row-sp ${isUpsell ? 'is-upsale-line' : 'is-main-line'} ${showUpsellDivider ? 'has-upsale-divider' : ''}`.trim()}
            role="listitem"
        >
            <span className="ten-sp ps-order-product-name" title={looksLikeUrl ? String(rawName) : name}>
                {showUpsellDivider ? (
                    <span className="ps-order-upsale-icon" title="Upsale" aria-label="Upsale">
                        <i className="fa fa-level-up" aria-hidden="true" />
                    </span>
                ) : null}
                <span>{name}</span>
            </span>
            <span className="ps-order-product-qty no-wrap">
                {textOnly && quantity <= 1 ? '' : `x${quantity}`}
            </span>
            <span className="ps-order-product-price no-wrap">
                {unitPrice > 0 ? formatOpsMoney(unitPrice) : ''}
            </span>
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
    const showDashBeforeUpsell = mainItems.length > 0 && upsellItems.length > 0;

    return (
        <div className="tb-in-sp ps-order-products-breakdown" aria-label="Sản phẩm trong đơn" role="list">
            {mainItems.map((item, index) => (
                <ProductLine key={item.itemId ?? item.id ?? `main-${index}`} item={item} index={index} order={order} />
            ))}
            {showDashBeforeUpsell ? (
                <div className="row-sp ps-order-products-upsell-rule-row" aria-hidden="true">
                    <span className="ps-order-products-upsell-rule">—</span>
                </div>
            ) : null}
            {upsellItems.map((item, index) => (
                <ProductLine
                    key={item.itemId ?? item.id ?? `upsell-${index}`}
                    item={item}
                    index={index}
                    order={order}
                    forceUpsell
                    showUpsellDivider={index === 0 && !showDashBeforeUpsell}
                />
            ))}
        </div>
    );
}

export function OrderMoneyBreakdown({ row = {}, items = null, showZeroDiscount = false }) {
    const products = items ?? row.products ?? row.items ?? [];
    const storedSubtotal = Number(row.subtotal ?? row.sub_total ?? 0);
    const computed = productsSubtotal(products, row);
    const subtotal = storedSubtotal > 0 ? storedSubtotal : computed;
    const discount = Number(row.discount ?? row.discountAmount ?? 0);
    const vat = Number(row.vat ?? row.tax ?? 0);
    const shippingFee = Number(row.shippingFeeCollected ?? row.shipping_fee_collected ?? row.shippingFee ?? 0);
    const storedTotal = Number(row.total ?? 0);
    const total = storedTotal > 0
        ? storedTotal
        : Math.max(0, subtotal - discount + shippingFee);

    const moneyOrBlank = (value, { always = false } = {}) => {
        if (!always && (!value || Number(value) === 0)) {
            return '';
        }

        return formatOpsMoney(value);
    };

    const lines = [];
    // Only show Thành tiền line when it differs from Tổng — identical values stacked
    // and looked like a ghosted "169.000" / "169.000 đ" overlap on WH/KT pages.
    if (subtotal > 0 && Math.abs(subtotal - total) > 0.5) {
        lines.push({ key: 'subtotal', title: 'Thành tiền', text: moneyOrBlank(subtotal) });
    }
    if (discount > 0 || showZeroDiscount) {
        lines.push({
            key: 'discount',
            title: 'Chiết khấu',
            text: discount > 0 ? `-${formatOpsMoney(discount)}` : formatOpsMoney(0),
        });
    }
    if (vat > 0) {
        lines.push({ key: 'vat', title: 'VAT', text: moneyOrBlank(vat) });
    }
    if (shippingFee > 0) {
        lines.push({ key: 'shipping', title: 'Phí vận chuyển', text: moneyOrBlank(shippingFee) });
    }
    lines.push({
        key: 'total',
        title: 'Tổng tiền đơn hàng',
        text: moneyOrBlank(total) || (subtotal > 0 ? formatOpsMoney(total) : ''),
        strong: true,
    });

    // Prefer plain "169.000 đ" (not Intl ₫) — avoids amount/symbol ghosting in ops tables.
    return (
        <div className="ps-order-money-breakdown" aria-label="Thành tiền đơn hàng">
            {lines.filter((line) => line.text).map((line) => (
                <div key={line.key} className={`ps-order-money-line${line.strong ? ' is-total' : ''}`} title={line.title}>
                    {line.text}
                </div>
            ))}
        </div>
    );
}

/** Shared Thành tiền <td> for Sale / Warehouse / Accounting / Customer tables. */
export function OrderMoneyCell({
    row = {},
    items = null,
    showZeroDiscount = false,
    className = '',
    ...tdProps
}) {
    return (
        <td className={`ps-ops-money-cell text-right ${className}`.trim()} {...tdProps}>
            <OrderMoneyBreakdown row={row} items={items} showZeroDiscount={showZeroDiscount} />
        </td>
    );
}
