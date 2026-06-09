/** Màu badge trạng thái giao hàng — mỗi trạng thái một tone riêng khi có thể. */
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

/** Trạng thái chốt đơn (telesale). */
export const CLOSING_TONES = {
    open: 'info',
    closed: 'emerald',
    cancelled: 'danger',
};

/** Trạng thái vận đơn trên hệ thống. */
export const SHIPMENT_TONES = {
    pending: 'warning',
    submitted: 'teal',
    failed: 'danger',
    cancelled: 'muted',
};

/** Trạng thái lead đổ về. */
export const LEAD_TONES = {
    processed: 'success',
    pending: 'warning',
    duplicate: 'orange',
    failed: 'danger',
};

/** Loại phiếu nhập/xuất kho. */
export const MOVEMENT_TONES = {
    intake: 'success',
    export: 'warning',
    deduction: 'info',
    return: 'purple',
};

/** Loại vấn đề đối soát vận chuyển. */
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
    return RECONCILIATION_ISSUE_TONES[type] ?? 'muted';
}

/** Nhãn tiếng Việt cho mã trạng thái giao hàng (bảng đối soát). */
export const DELIVERY_LABELS = {
    waiting_waybill: 'Chờ vận đơn',
    deliver_now: 'Giao ngay',
    posted: 'Đã đăng',
    picking_up: 'Đang lấy hàng',
    delivering: 'Đang giao hàng',
    redelivery: 'Yêu cầu giao lại',
    delivered: 'Đã giao hàng',
    delivery_complete: 'Hoàn giao hàng',
    paid: 'Đã thanh toán',
    returned: 'Đã hoàn',
    returning: 'Đang hoàn',
    cannot_deliver: 'Không giao được',
    cannot_pickup: 'Không lấy được hàng',
    cancel_waybill: 'Hủy vận đơn',
    cancel_closing: 'Hủy đóng đơn',
    refund: 'Bồi hoàn',
};

export function deliveryLabel(value) {
    return DELIVERY_LABELS[value] ?? value;
}
