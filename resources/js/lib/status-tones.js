/** Delivery status badge tones. */
export const DELIVERY_TONES = {
    waiting_waybill: 'warning',
    deliver_now: 'orange',
    posted: 'amber',
    picking_up: 'info',
    delivering: 'teal',
    redelivery: 'cyan',
    delivered: 'success',
    delivery_complete: 'emerald',
    paid: 'purple',
    returned: 'danger',
    returning: 'orange',
    cannot_deliver: 'rose',
    cannot_pickup: 'amber',
    cancel_waybill: 'muted',
    cancel_closing: 'muted',
    refund: 'rose',
};

/** Telesale closing status tones. */
export const CLOSING_TONES = {
    open: 'info',
    closed: 'emerald',
    cancelled: 'danger',
};

/** Shipment state tones. */
export const SHIPMENT_TONES = {
    pending: 'warning',
    submitted: 'teal',
    failed: 'danger',
    cancelled: 'muted',
};

/** Lead ingestion status tones. */
export const LEAD_TONES = {
    processed: 'success',
    pending: 'warning',
    duplicate: 'orange',
    failed: 'danger',
};

/** Inventory movement type tones. */
export const MOVEMENT_TONES = {
    intake: 'success',
    export: 'warning',
    deduction: 'info',
    return: 'purple',
};

/** Shipping reconciliation issue tones. */
export const RECONCILIATION_ISSUE_TONES = {
    cod_mismatch: 'danger',
    unmatched: 'warning',
    matched: 'success',
    info: 'info',
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

export function movementTone(value) {
    return MOVEMENT_TONES[value] ?? 'muted';
}

export function reconciliationIssueTone(type) {
    return RECONCILIATION_ISSUE_TONES[type] ?? 'info';
}

export function deliveryLabel(value, labels) {
    return labels?.delivery_status?.[value] ?? value;
}
