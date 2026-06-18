<?php

return [
    'other' => 'Khác',
    'funnel' => [
        'lead' => 'Lead',
        'lead_ingest' => 'Lead ingest',
        'order' => 'Đơn',
        'closed' => 'Chốt',
        'delivered' => 'Giao',
        'paid' => 'Đã thanh toán',
        'allocated' => 'Đã chia',
        'contacted' => 'Đã liên hệ',
        'delivered_paid' => 'Giao/Đã TT',
        'in_progress' => 'Đang xử lý',
        'processed' => 'Đã xử lý',
        'failed_leads' => 'Lead lỗi',
    ],
    'delivery' => [
        'waiting_waybill' => 'Chờ vận đơn',
        'pending_pickup' => 'Chờ lấy hàng',
        'delivering' => 'Đang giao',
    ],
    'routing' => [
        'pending' => 'Chờ phân số',
        'failed' => 'Lỗi',
        'duplicate' => 'Trùng',
    ],
    'alerts' => [
        'failed_orders' => 'Đơn lỗi / hoàn hủy',
        'failed_orders_desc' => 'Cần rà soát trạng thái giao hàng.',
        'cod_mismatch' => 'Lệch COD',
        'cod_mismatch_desc' => 'Webhook vận chuyển có số tiền lệch.',
        'failed_leads' => 'Lead lỗi',
        'failed_leads_desc' => 'Lead ingest thất bại cần retry.',
        'waiting_waybill' => 'Chờ vận đơn',
        'waiting_waybill_desc' => 'Đơn đang chờ tạo/đẩy vận đơn.',
    ],
];
