<?php

return [
    'all' => 'Tất cả',
    'customer_interactions' => [
        'phone_required' => 'Khách hàng chưa có số điện thoại hợp lệ.',
        'pancake_read_only' => 'Bạn chỉ có quyền xem. Chỉ Admin, Sales hoặc người được cấp quyền Chat khách hàng Pancake mức Toàn quyền mới được gửi tin cho khách.',
        'read_only' => 'Bạn chỉ có quyền xem. Cần quyền Khách hàng mức Toàn quyền để gửi tin nhắn.',
        'message_required' => 'Vui lòng nhập nội dung tin nhắn.',
        'same_phone_link' => 'Xem tin nhắn cùng số điện thoại',
        'pancake_missing_conversation' => 'Webhook Pancake không có conversation_id hợp lệ.',
        'system_actor' => 'Hệ thống',
        'history_before_tracking' => 'Dữ liệu hiện tại được tạo trước khi hệ thống bắt đầu lưu lịch sử chi tiết.',
        'history_actions' => [
            'landing_upsell_added' => 'Gộp upsell Landing',
            'landing_upsell_requires_review' => 'Upsell Landing cần kiểm tra',
            'landing_late_upsell_manually_merged' => 'Gộp thủ công upsell đến muộn',
            'landing_late_upsell_created_order' => 'Tạo đơn bổ sung từ upsell đến muộn',
            'landing_supplemental_order_created' => 'Đơn bổ sung từ Landing',
            'initial_snapshot' => 'Trạng thái hiện tại',
            'call' => 'Ghi nhận cuộc gọi',
            'status_updated' => 'Cập nhật tác nghiệp',
            'order_updated' => 'Cập nhật thông tin đơn',
            'order_closed' => 'Chốt đơn',
        ],
    ],
    'warehouse_tabs' => [
        'waiting' => 'Chờ vận đơn',
        'pickup' => 'Lấy hàng',
        'delivering' => 'Đang giao',
        'delivered' => 'Đã giao',
        'paid' => 'Đã thanh toán',
        'returns' => 'Đơn hoàn',
        'cancelled' => 'Đã hủy',
    ],
];
