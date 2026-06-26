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
        // Giới hạn kích thước payload thô (bytes) để tránh nhồi dữ liệu.
        'max_payload_bytes' => (int) env('LEAD_MAX_PAYLOAD_BYTES', 65536),
        // Rate limit cổng nhận lead.
        'rate_limit_per_minute' => (int) env('LEAD_RATE_LIMIT_PER_MINUTE', 60),
    ],

    'locales' => [
        'vi' => ['label' => 'Tiếng Việt', 'short' => 'VI'],
        'en' => ['label' => 'English', 'short' => 'EN'],
    ],

    'default_locale' => 'vi',

];
