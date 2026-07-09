<?php

return [

    'brand' => [
        'name' => 'ERM SaleOps',
        'tagline' => 'Hệ thống điều hành bán hàng & vận hành',
        'short' => 'SaleOps',
    ],

    'themes' => [
        'brand' => [
            'label' => 'SaleOps Blue',
            'description' => 'Xanh chủ đạo — mặc định',
            'primary' => 'oklch(0.546 0.215 262.881)',
            'primary_foreground' => 'oklch(0.985 0 0)',
            'chart' => ['#3b82f6', '#60a5fa', '#93c5fd'],
        ],
        'ocean' => [
            'label' => 'Ocean Teal',
            'description' => 'Xanh ngọc hiện đại',
            'primary' => 'oklch(0.55 0.12 195)',
            'primary_foreground' => 'oklch(0.99 0 0)',
            'chart' => ['#0d9488', '#2dd4bf', '#5eead4'],
        ],
        'sunset' => [
            'label' => 'Sunset',
            'description' => 'Cam ấm — nhấn conversion',
            'primary' => 'oklch(0.62 0.19 45)',
            'primary_foreground' => 'oklch(0.99 0 0)',
            'chart' => ['#ea580c', '#fb923c', '#fdba74'],
        ],
        'violet' => [
            'label' => 'Violet Pro',
            'description' => 'Tím sang — báo cáo CEO',
            'primary' => 'oklch(0.52 0.22 295)',
            'primary_foreground' => 'oklch(0.99 0 0)',
            'chart' => ['#7c3aed', '#a78bfa', '#c4b5fd'],
        ],
    ],

    'lead_routing' => [
        'strategy' => env('LEAD_ROUTING_STRATEGY', 'round_robin'),
        'duplicate_window_days' => (int) env('LEAD_DUPLICATE_WINDOW_DAYS', 30),
    ],

    /*
    | Gộp đơn & giữ số cho luồng Landing (form đầu + upsale trang cảm ơn).
    | Mục tiêu: cùng 1 khách gửi nhiều gói tin → GỘP thành 1 đơn, chia 1 số.
    | - grouping_window_minutes: cửa sổ coi các gói tin cùng SĐT là cùng 1 đơn.
    | - hold_seconds: cửa sổ chờ upsale trên đơn (đã chia số; sale thấy badge "chờ upsale").
    | - max_hold_seconds: trần giữ tuyệt đối để lead không kẹt mãi.
    */
    'landing' => [
        'grouping_window_minutes' => (int) env('LEAD_GROUPING_WINDOW_MINUTES', 15),
        'hold_seconds' => (int) env('LEAD_HOLD_SECONDS', 5),
        'max_hold_seconds' => (int) env('LEAD_MAX_HOLD_SECONDS', 300),
    ],

    /*
    | Chống rác / spam khi nhận lead từ Landing & nền tảng ngoài.
    | Mục tiêu: dữ liệu vào hệ thống luôn sạch, đúng định dạng, không spam.
    */
    'lead_intake' => [
        // Trường bẫy bot (honeypot): nếu form gửi lên có 1 trong các tên này và CÓ giá trị → bỏ qua.
        // Marketing thêm 1 field ẩn tên "website" trên LadiPage là bật được chống bot.
        'honeypot_fields' => ['website', 'url', 'homepage', 'email_confirm', '_gotcha', 'hp'],
        'max_name_length' => 100,
        'max_message_length' => 1000,
        'max_product_length' => 255,
        'max_address_length' => 255,
        // Giới hạn kích thước payload thô (bytes) để tránh nhồi dữ liệu.
        'max_payload_bytes' => (int) env('LEAD_MAX_PAYLOAD_BYTES', 65536),
        // Rate limit cổng nhận lead.
        'rate_limit_per_minute' => (int) env('LEAD_RATE_LIMIT_PER_MINUTE', 60),
    ],


    /*
    | Tách queue theo luồng nghiệp vụ để webhook, tin nhắn, notification,
    | shipping và báo cáo không nghẽn chung một hàng đợi.
    */
    'queues' => [
        'webhooks' => env('QUEUE_WEBHOOKS', 'webhooks'),
        'pancake_orders' => env('QUEUE_PANCAKE_ORDERS', 'pancake-orders'),
        'shipping_webhooks' => env('QUEUE_SHIPPING_WEBHOOKS', 'shipping-webhooks'),
        'shipments' => env('QUEUE_SHIPMENTS', 'shipments'),
        'messages' => env('QUEUE_MESSAGES', 'messages'),
        'internal_chat_broadcasts' => env('QUEUE_INTERNAL_CHAT_BROADCASTS', 'broadcasts-internal-chat'),
        'dashboard_broadcasts' => env('QUEUE_DASHBOARD_BROADCASTS', 'broadcasts-dashboard'),
        'notification_broadcasts' => env('QUEUE_NOTIFICATION_BROADCASTS', 'broadcasts-notifications'),
        'pancake_chat_sync' => env('QUEUE_PANCAKE_CHAT_SYNC', 'pancake-chat'),
        'pancake_chat_broadcasts' => env('QUEUE_PANCAKE_CHAT_BROADCASTS', 'broadcasts-pancake-chat'),
        'notifications' => env('QUEUE_NOTIFICATIONS', 'notifications'),
        'translations' => env('QUEUE_TRANSLATIONS', 'translations'),
        'reports' => env('QUEUE_REPORTS', 'reports'),
        'exports' => env('QUEUE_EXPORTS', 'exports'),
        'default' => env('QUEUE_DEFAULT_NAMED', 'default'),
    ],

    'pancake_chat' => [
        'webhook_rate_limit_per_minute' => (int) env('PANCAKE_CHAT_WEBHOOK_RATE_LIMIT_PER_MINUTE', 120),
    ],

    'locales' => [
        'vi' => ['label' => 'Tiếng Việt', 'short' => 'VI'],
        'en' => ['label' => 'English', 'short' => 'EN'],
    ],

    'default_locale' => 'vi',

    /*
    | Đa doanh nghiệp — quy ước email (giống pushsale.vn):
    | - Nội bộ ERM: admin@saleops.local, sales@saleops.local …
    | - Khách hàng: admin@{slug}.saleops.local, sales@{slug}.saleops.local …
    | Doanh nghiệp mới chỉ do super admin tạo, không tự đăng ký.
    */
    'tenant' => [
        'internal_slug' => 'internal',
        'internal_name' => 'ERM SaleOps (Nội bộ)',
        'email_domain' => env('SALEOPS_EMAIL_DOMAIN', 'saleops.local'),
        'default_password' => env('SALEOPS_DEFAULT_PASSWORD', 'password'),
    ],

];
