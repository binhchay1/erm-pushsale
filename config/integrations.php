<?php

/**
 * Cấu hình tích hợp nền tảng — giá trị nhạy cảm đặt trong .env.
 * Chi tiết đăng ký & lấy key: docs/INTEGRATIONS.md
 */
return [

    'webhook' => [
        'global_secret' => env('INTEGRATION_WEBHOOK_SECRET'),
        'tolerance_seconds' => (int) env('INTEGRATION_WEBHOOK_TOLERANCE', 300),
    ],

    'platforms' => [
        'facebook' => [
            'label' => 'Facebook Lead Ads',
            'driver' => \App\Integrations\Facebook\FacebookLeadDriver::class,
            'env' => [
                'app_id' => 'FACEBOOK_APP_ID',
                'app_secret' => 'FACEBOOK_APP_SECRET',
                'page_access_token' => 'FACEBOOK_PAGE_ACCESS_TOKEN',
                'verify_token' => 'FACEBOOK_VERIFY_TOKEN',
            ],
            'webhook_path' => 'facebook',
            'docs' => 'https://developers.facebook.com/docs/graph-api/webhooks',
        ],
        'tiktok' => [
            'label' => 'TikTok Lead Generation',
            'driver' => \App\Integrations\Generic\GenericWebhookDriver::class,
            'env' => [
                'app_id' => 'TIKTOK_APP_ID',
                'app_secret' => 'TIKTOK_APP_SECRET',
                'access_token' => 'TIKTOK_ACCESS_TOKEN',
            ],
            'webhook_path' => 'tiktok',
            'docs' => 'https://business-api.tiktok.com/portal/docs',
        ],
        'zalo' => [
            'label' => 'Zalo OA / ZNS',
            'driver' => \App\Integrations\Generic\GenericWebhookDriver::class,
            'env' => [
                'oa_id' => 'ZALO_OA_ID',
                'app_id' => 'ZALO_APP_ID',
                'secret_key' => 'ZALO_SECRET_KEY',
                'access_token' => 'ZALO_ACCESS_TOKEN',
            ],
            'webhook_path' => 'zalo',
            'docs' => 'https://developers.zalo.me/docs',
        ],
        'landing' => [
            'label' => 'Landing Page / Form riêng',
            'driver' => \App\Integrations\Landing\LandingFormDriver::class,
            'env' => [
                'api_key' => 'LANDING_API_KEY',
            ],
            'webhook_path' => 'landing',
            'docs' => null,
        ],
        'google' => [
            'label' => 'Google Ads Lead Form',
            'driver' => \App\Integrations\Generic\GenericWebhookDriver::class,
            'env' => [
                'webhook_key' => 'GOOGLE_LEADS_WEBHOOK_KEY',
            ],
            'webhook_path' => 'google',
            'docs' => 'https://developers.google.com/google-ads/api',
        ],
    ],

];
