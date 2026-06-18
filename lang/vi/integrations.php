<?php

return [
    'hub' => [
        'title' => 'Phễu dữ liệu bán hàng — Data Hub',
        'summary' => 'Gom lead từ Landing, quảng cáo, sàn TMĐT về một hệ thống: chia số telesale, chống trùng, đo ROI và đẩy đơn vận chuyển.',
        'problems' => [
            'Rớt đơn do sale quên gọi',
            'Thất thoát hàng / không khớp tồn kho',
            'Không biết chiến dịch nào sinh lời',
            'Tạo vận đơn thủ công tốn thời gian',
        ],
        'solutions' => [
            'marketing' => 'Đo ROI từ Facebook, TikTok, Google → từng đơn thành công',
            'telesales' => 'Chia số tự động (round-robin / theo tải), chống trùng SĐT',
            'fulfillment' => 'Kết nối GHTK, GHN, Viettel Post (webhook trạng thái)',
            'finance' => 'Báo cáo COD, công nợ, tồn kho real-time',
        ],
        'workflow' => [
            'Hứng data qua Webhook / API',
            'Kiểm tra trùng SĐT & phân số sale',
            'Telesale chốt đơn trên workspace',
            'Kho xuất & đẩy sang hãng vận chuyển',
            'Hãng VC cập nhật trạng thái ngược qua webhook',
            'Kế toán đối soát COD & chăm sóc lại',
        ],
    ],
    'categories' => [
        'advertising' => 'Quảng cáo & Lead Form',
        'social' => 'Mạng xã hội & Chat',
        'landing' => 'Landing Page / Website',
        'marketplace' => 'Sàn thương mại điện tử',
    ],
    'fields' => [
        'app_id' => 'Mã ứng dụng (App ID)',
        'app_secret' => 'Khóa bí mật ứng dụng',
        'page_access_token' => 'Token trang Facebook',
        'access_token' => 'Token truy cập',
        'webhook_key' => 'Khóa webhook Google',
        'oa_id' => 'Mã OA Zalo',
        'secret_key' => 'Khóa bí mật',
        'api_key' => 'Khóa API / Webhook',
        'partner_id' => 'Mã đối tác',
        'partner_key' => 'Khóa đối tác',
        'shop_id' => 'Mã cửa hàng',
        'app_key' => 'Mã ứng dụng',
    ],
    'platforms' => [
        'facebook' => [
            'label' => 'Facebook Lead Ads',
            'description' => 'Lead từ form quảng cáo Facebook — webhook leadgen real-time.',
        ],
        'tiktok' => [
            'label' => 'TikTok Lead Generation',
            'description' => 'Lead form TikTok Ads / TikTok Shop.',
        ],
        'google' => [
            'label' => 'Google Ads Lead Form',
            'description' => 'Lead form mở rộng Google Ads.',
        ],
        'zalo' => [
            'label' => 'Zalo OA',
            'description' => 'Tin nhắn / form từ Zalo Official Account.',
        ],
        'landing' => [
            'label' => 'Landing Page (Ladipage, Web)',
            'description' => 'Mỗi chiến dịch có URL API riêng (Marketing → Kết nối Landing). Webhook chung bên dưới chỉ dùng dự phòng.',
        ],
        'shopee' => [
            'label' => 'Shopee',
            'description' => 'Đơn / chat Shopee — đẩy qua webhook hoặc API đối tác.',
        ],
        'lazada' => [
            'label' => 'Lazada',
            'description' => 'Đơn Lazada — webhook từ Open Platform.',
        ],
    ],
    'test' => [
        'unsupported' => 'Nền tảng không được hỗ trợ.',
        'success' => 'Đã gửi thử webhook thành công.',
        'sample_recorded' => 'Lead mẫu đã được hệ thống ghi nhận.',
        'failed' => 'Gửi thử webhook thất bại: :error',
        'payload_failed' => 'Không xử lý được payload thử nghiệm. Kiểm tra cấu hình và bật nền tảng.',
        'sample_name' => 'Khách test webhook',
        'sample_product' => 'Sản phẩm demo',
        'platform' => 'Nền tảng',
        'lead_id' => 'Mã bản ghi lead',
        'status' => 'Trạng thái xử lý',
        'sample_phone' => 'Số điện thoại mẫu',
    ],
];
