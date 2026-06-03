<?php

use App\Integrations\Facebook\FacebookLeadDriver;
use App\Integrations\Generic\GenericWebhookDriver;
use App\Integrations\Landing\LandingFormDriver;

/**
 * Cấu hình tích hợp nền tảng — giá trị nhạy cảm đặt trong .env hoặc lưu DB (admin).
 * Chi tiết: docs/INTEGRATIONS.md
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
                'app_id' => ['label' => 'App ID', 'env' => 'FACEBOOK_APP_ID'],
                'app_secret' => ['label' => 'App Secret', 'env' => 'FACEBOOK_APP_SECRET', 'secret' => true],
                'page_access_token' => ['label' => 'Page Access Token', 'env' => 'FACEBOOK_PAGE_ACCESS_TOKEN', 'secret' => true],
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
                'app_id' => ['label' => 'App ID', 'env' => 'TIKTOK_APP_ID'],
                'app_secret' => ['label' => 'App Secret', 'env' => 'TIKTOK_APP_SECRET', 'secret' => true],
                'access_token' => ['label' => 'Access Token', 'env' => 'TIKTOK_ACCESS_TOKEN', 'secret' => true],
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
                'webhook_key' => ['label' => 'Webhook Key', 'env' => 'GOOGLE_LEADS_WEBHOOK_KEY', 'secret' => true],
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
                'oa_id' => ['label' => 'OA ID', 'env' => 'ZALO_OA_ID'],
                'app_id' => ['label' => 'App ID', 'env' => 'ZALO_APP_ID'],
                'secret_key' => ['label' => 'Secret Key', 'env' => 'ZALO_SECRET_KEY', 'secret' => true],
                'access_token' => ['label' => 'Access Token', 'env' => 'ZALO_ACCESS_TOKEN', 'secret' => true],
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
                'api_key' => ['label' => 'API Key / Webhook Secret', 'env' => 'LANDING_API_KEY', 'secret' => true],
            ],
            'webhook_path' => 'landing',
            'docs' => null,
        ],
        'shopee' => [
            'label' => 'Shopee',
            'category' => 'marketplace',
            'description' => 'Đơn / chat Shopee — đẩy qua webhook hoặc API đối tác.',
            'driver' => GenericWebhookDriver::class,
            'fields' => [
                'partner_id' => ['label' => 'Partner ID', 'env' => 'SHOPEE_PARTNER_ID'],
                'partner_key' => ['label' => 'Partner Key', 'env' => 'SHOPEE_PARTNER_KEY', 'secret' => true],
                'shop_id' => ['label' => 'Shop ID', 'env' => 'SHOPEE_SHOP_ID'],
                'access_token' => ['label' => 'Access Token', 'env' => 'SHOPEE_ACCESS_TOKEN', 'secret' => true],
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
                'app_key' => ['label' => 'App Key', 'env' => 'LAZADA_APP_KEY'],
                'app_secret' => ['label' => 'App Secret', 'env' => 'LAZADA_APP_SECRET', 'secret' => true],
                'access_token' => ['label' => 'Access Token', 'env' => 'LAZADA_ACCESS_TOKEN', 'secret' => true],
            ],
            'webhook_path' => 'lazada',
            'docs' => 'https://open.lazada.com/doc',
        ],
    ],

];
