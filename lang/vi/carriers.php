<?php

return [
    'not_configured' => ':provider chưa bật hoặc thiếu thông tin cấu hình.',
    'create_rejected' => ':provider từ chối tạo đơn.',
    'status_failed' => 'Không lấy được trạng thái :provider.',
    'cancel_failed' => 'Không hủy được đơn :provider.',
    'no_waybill' => 'Chưa có mã vận đơn :provider.',
    'created_status' => 'Đã tạo trên :provider',
    'cancelled_status' => 'Đã hủy trên :provider',
    'action_unsupported' => 'Thao tác :provider [:action] không được hỗ trợ.',
    'print_token' => ':provider trả token in nhãn — mở link từ response.',
    'jnt' => [
        'sync_via_webhook' => 'J&T: đồng bộ trạng thái qua webhook hoặc API tracking — đang dùng webhook SaleOps.',
        'fee_via_portal' => 'J&T: tính phí qua portal hoặc bổ sung API fee sau.',
        'cancel_via_api' => 'J&T: hủy đơn qua API riêng — liên hệ vận hành để bật endpoint.',
        'label_via_portal' => 'J&T: in nhãn qua portal.',
    ],
];
