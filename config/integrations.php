<?php

use App\Integrations\Facebook\FacebookLeadDriver;
use App\Integrations\Generic\GenericWebhookDriver;
use App\Integrations\Landing\LandingFormDriver;
use App\Integrations\Pancake\PancakeLeadDriver;

/**
 * Cấu hình tích hợp nền tảng — giá trị nhạy cảm đặt trong .env hoặc lưu DB (admin).
 * Chi tiết: docs/API_AND_ROUTES.md § Tích hợp nền tảng
 */
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
        'pos' => 'POS / Bán hàng đa kênh',
    ],

    'webhook' => [
        'global_secret' => env('INTEGRATION_WEBHOOK_SECRET'),
        'tolerance_seconds' => (int) env('INTEGRATION_WEBHOOK_TOLERANCE', 300),
    ],

    'platforms' => [
        'facebook' => [
            'label' => 'Facebook Lead Ads',
            'category' => 'advertising',
            'description' => 'Lead từ form quảng cáo Facebook — webhook leadgen real-time.',
            'driver' => FacebookLeadDriver::class,
            'fields' => [
                'app_id' => ['label' => 'Mã ứng dụng (App ID)', 'env' => 'FACEBOOK_APP_ID', 'default' => env('FACEBOOK_APP_ID')],
                'app_secret' => ['label' => 'Khóa bí mật ứng dụng', 'env' => 'FACEBOOK_APP_SECRET', 'secret' => true, 'default' => env('FACEBOOK_APP_SECRET')],
                'page_access_token' => ['label' => 'Token trang Facebook', 'env' => 'FACEBOOK_PAGE_ACCESS_TOKEN', 'secret' => true, 'default' => env('FACEBOOK_PAGE_ACCESS_TOKEN')],
            ],
            'webhook_path' => 'facebook',
            'docs' => 'https://developers.facebook.com/docs/graph-api/webhooks',
        ],
        'tiktok' => [
            'label' => 'TikTok Lead Generation',
            'category' => 'advertising',
            'description' => 'Lead form TikTok Ads / TikTok Shop.',
            'driver' => GenericWebhookDriver::class,
            'fields' => [
                'app_id' => ['label' => 'Mã ứng dụng', 'env' => 'TIKTOK_APP_ID', 'default' => env('TIKTOK_APP_ID')],
                'app_secret' => ['label' => 'Khóa bí mật', 'env' => 'TIKTOK_APP_SECRET', 'secret' => true, 'default' => env('TIKTOK_APP_SECRET')],
                'access_token' => ['label' => 'Token truy cập', 'env' => 'TIKTOK_ACCESS_TOKEN', 'secret' => true, 'default' => env('TIKTOK_ACCESS_TOKEN')],
            ],
            'webhook_path' => 'tiktok',
            'docs' => 'https://business-api.tiktok.com/portal/docs',
        ],
        'google' => [
            'label' => 'Google Ads Lead Form',
            'category' => 'advertising',
            'description' => 'Lead form mở rộng Google Ads.',
            'driver' => GenericWebhookDriver::class,
            'fields' => [
                'webhook_key' => ['label' => 'Khóa webhook Google', 'env' => 'GOOGLE_LEADS_WEBHOOK_KEY', 'secret' => true, 'default' => env('GOOGLE_LEADS_WEBHOOK_KEY')],
            ],
            'webhook_path' => 'google',
            'docs' => 'https://developers.google.com/google-ads/api',
        ],
        'zalo' => [
            'label' => 'Zalo OA',
            'category' => 'social',
            'description' => 'Tin nhắn / form từ Zalo Official Account.',
            'driver' => GenericWebhookDriver::class,
            'fields' => [
                'oa_id' => ['label' => 'Mã OA Zalo', 'env' => 'ZALO_OA_ID', 'default' => env('ZALO_OA_ID')],
                'app_id' => ['label' => 'Mã ứng dụng', 'env' => 'ZALO_APP_ID', 'default' => env('ZALO_APP_ID')],
                'secret_key' => ['label' => 'Khóa bí mật', 'env' => 'ZALO_SECRET_KEY', 'secret' => true, 'default' => env('ZALO_SECRET_KEY')],
                'access_token' => ['label' => 'Token truy cập', 'env' => 'ZALO_ACCESS_TOKEN', 'secret' => true, 'default' => env('ZALO_ACCESS_TOKEN')],
            ],
            'webhook_path' => 'zalo',
            'docs' => 'https://developers.zalo.me/docs',
        ],
        'landing' => [
            'label' => 'Landing Page (Ladipage, Web)',
            'category' => 'landing',
            'description' => 'Mỗi chiến dịch có URL API riêng (Marketing → Kết nối Landing). Webhook chung bên dưới chỉ dùng dự phòng.',
            'driver' => LandingFormDriver::class,
            'fields' => [
                'api_key' => ['label' => 'Khóa API / Webhook', 'env' => 'LANDING_API_KEY', 'secret' => true, 'default' => env('LANDING_API_KEY')],
            ],
            'webhook_path' => 'landing',
            'docs' => null,
        ],

        'pancake' => [
            'label' => 'Pancake POS / Extension',
            'category' => 'pos',
            'description' => 'Nhận đơn/lead từ Pancake POS qua Webhook, Open API hoặc Chrome Extension giống luồng Pushsale.',
            'driver' => PancakeLeadDriver::class,
            'fields' => [
                'shop_id' => ['label' => 'Shop ID Pancake POS', 'env' => 'PANCAKE_SHOP_ID', 'default' => env('PANCAKE_SHOP_ID')],
                'allowed_shop_ids' => ['label' => 'Shop ID được phép (CSV)', 'env' => 'PANCAKE_ALLOWED_SHOP_IDS', 'default' => env('PANCAKE_ALLOWED_SHOP_IDS'), 'required' => false],
                'allowed_page_ids' => ['label' => 'Page ID được phép (CSV)', 'env' => 'PANCAKE_ALLOWED_PAGE_IDS', 'default' => env('PANCAKE_ALLOWED_PAGE_IDS'), 'required' => false],
                'base_url' => ['label' => 'Pancake POS API Base URL', 'env' => 'PANCAKE_API_BASE_URL', 'default' => env('PANCAKE_API_BASE_URL', 'https://pos.pages.fm/api/v1'), 'required' => false],
                'api_key' => ['label' => 'API Key Pancake POS', 'env' => 'PANCAKE_API_KEY', 'secret' => true, 'default' => env('PANCAKE_API_KEY')],
                'page_api_base_url' => ['label' => 'Pancake Page API Base URL', 'env' => 'PANCAKE_PAGE_API_BASE_URL', 'default' => env('PANCAKE_PAGE_API_BASE_URL', 'https://pages.fm/api/public_api/v1'), 'required' => false],
                'page_id' => ['label' => 'Page ID để chat', 'env' => 'PANCAKE_PAGE_ID', 'default' => env('PANCAKE_PAGE_ID'), 'required' => false],
                'page_access_token' => ['label' => 'Page Access Token để chat', 'env' => 'PANCAKE_PAGE_ACCESS_TOKEN', 'secret' => true, 'default' => env('PANCAKE_PAGE_ACCESS_TOKEN'), 'required' => false],
                'extension_token' => ['label' => 'Token riêng cho Extension', 'env' => 'PANCAKE_EXTENSION_TOKEN', 'secret' => true, 'default' => env('PANCAKE_EXTENSION_TOKEN'), 'required' => false],
                'default_marketing_source_id' => ['label' => 'ID nguồn mặc định', 'env' => 'PANCAKE_DEFAULT_SOURCE_ID', 'default' => env('PANCAKE_DEFAULT_SOURCE_ID'), 'required' => false],
                'default_warehouse_id' => ['label' => 'ID kho mặc định', 'env' => 'PANCAKE_DEFAULT_WAREHOUSE_ID', 'default' => env('PANCAKE_DEFAULT_WAREHOUSE_ID'), 'required' => false],
                'default_shipping_provider' => ['label' => 'Đơn vị giao mặc định', 'env' => 'PANCAKE_DEFAULT_SHIPPING_PROVIDER', 'default' => env('PANCAKE_DEFAULT_SHIPPING_PROVIDER', 'viettel_post'), 'required' => false],
            ],
            'webhook_path' => 'pancake',
            'docs' => 'https://api-docs.pancake.vn/',
        ],
        'shopee' => [
            'label' => 'Shopee',
            'category' => 'marketplace',
            'description' => 'Đơn / chat Shopee — đẩy qua webhook hoặc API đối tác.',
            'driver' => GenericWebhookDriver::class,
            'fields' => [
                'partner_id' => ['label' => 'Mã đối tác', 'env' => 'SHOPEE_PARTNER_ID', 'default' => env('SHOPEE_PARTNER_ID')],
                'partner_key' => ['label' => 'Khóa đối tác', 'env' => 'SHOPEE_PARTNER_KEY', 'secret' => true, 'default' => env('SHOPEE_PARTNER_KEY')],
                'shop_id' => ['label' => 'Mã cửa hàng', 'env' => 'SHOPEE_SHOP_ID', 'default' => env('SHOPEE_SHOP_ID')],
                'access_token' => ['label' => 'Token truy cập', 'env' => 'SHOPEE_ACCESS_TOKEN', 'secret' => true, 'default' => env('SHOPEE_ACCESS_TOKEN')],
            ],
            'webhook_path' => 'shopee',
            'docs' => 'https://open.shopee.com/documents',
        ],
        'lazada' => [
            'label' => 'Lazada',
            'category' => 'marketplace',
            'description' => 'Đơn Lazada — webhook từ Open Platform.',
            'driver' => GenericWebhookDriver::class,
            'fields' => [
                'app_key' => ['label' => 'Mã ứng dụng', 'env' => 'LAZADA_APP_KEY', 'default' => env('LAZADA_APP_KEY')],
                'app_secret' => ['label' => 'Khóa bí mật', 'env' => 'LAZADA_APP_SECRET', 'secret' => true, 'default' => env('LAZADA_APP_SECRET')],
                'access_token' => ['label' => 'Token truy cập', 'env' => 'LAZADA_ACCESS_TOKEN', 'secret' => true, 'default' => env('LAZADA_ACCESS_TOKEN')],
            ],
            'webhook_path' => 'lazada',
            'docs' => 'https://open.lazada.com/doc',
        ],
    ],

];
