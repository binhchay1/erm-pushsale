/** Màu badge trạng thái giao hàng — dùng chung toàn app. */
export const DELIVERY_TONES = {
    waiting_waybill: 'warning',
    deliver_now: 'warning',
    posted: 'warning',
    picking_up: 'info',
    delivering: 'info',
    redelivery: 'info',
    delivered: 'success',
    delivery_complete: 'success',
    paid: 'success',
    returned: 'danger',
    returning: 'danger',
    cannot_deliver: 'danger',
    cannot_pickup: 'danger',
    cancel_waybill: 'danger',
    cancel_closing: 'danger',
    refund: 'danger',
};

/** Trạng thái chốt đơn (telesale). */
export const CLOSING_TONES = {
    open: 'info',
    closed: 'success',
    cancelled: 'danger',
};

/** Trạng thái vận đơn trên hệ thống. */
export const SHIPMENT_TONES = {
    pending: 'warning',
    submitted: 'success',
    failed: 'danger',
    cancelled: 'muted',
};

/** Trạng thái lead đổ về. */
export const LEAD_TONES = {
    processed: 'success',
    pending: 'warning',
    duplicate: 'warning',
    failed: 'danger',
};

export function deliveryTone(value) {
    return DELIVERY_TONES[value] ?? 'muted';
}

export function closingTone(value) {
    return CLOSING_TONES[value] ?? 'muted';
}

export function shipmentTone(value) {
    return SHIPMENT_TONES[value] ?? 'muted';
}

export function leadTone(value) {
    return LEAD_TONES[value] ?? 'muted';
}
