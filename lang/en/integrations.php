<?php

return [
    'hub' => [
        'title' => 'Sales data funnel — Data Hub',
        'summary' => 'Collect leads from landing pages, ads, and marketplaces in one system: route to telesales, dedupe, measure ROI, and push shipments.',
        'problems' => [
            'Lost orders when sales forget to call back',
            'Inventory shrinkage / stock mismatch',
            'Unknown which campaigns are profitable',
            'Manual waybill creation wastes time',
        ],
        'solutions' => [
            'marketing' => 'Measure ROI from Facebook, TikTok, Google → per successful order',
            'telesales' => 'Auto routing (round-robin / load-based), phone deduplication',
            'fulfillment' => 'Connect GHTK, GHN, Viettel Post (status webhooks)',
            'finance' => 'COD reports, receivables, real-time inventory',
        ],
        'workflow' => [
            'Ingest data via Webhook / API',
            'Dedupe phone numbers & route to sales',
            'Telesales close orders in workspace',
            'Warehouse ships & pushes to carriers',
            'Carriers update status via webhook',
            'Accounting reconciles COD & follow-up',
        ],
    ],
    'categories' => [
        'advertising' => 'Advertising & Lead Forms',
        'social' => 'Social & Chat',
        'landing' => 'Landing Page / Website',
        'marketplace' => 'E-commerce marketplaces',
    ],
    'fields' => [
        'app_id' => 'App ID',
        'app_secret' => 'App secret',
        'page_access_token' => 'Facebook page token',
        'access_token' => 'Access token',
        'webhook_key' => 'Google webhook key',
        'oa_id' => 'Zalo OA ID',
        'secret_key' => 'Secret key',
        'api_key' => 'API / Webhook key',
        'partner_id' => 'Partner ID',
        'partner_key' => 'Partner key',
        'shop_id' => 'Shop ID',
        'app_key' => 'App key',
    ],
    'platforms' => [
        'facebook' => [
            'label' => 'Facebook Lead Ads',
            'description' => 'Leads from Facebook ad forms — real-time leadgen webhook.',
        ],
        'tiktok' => [
            'label' => 'TikTok Lead Generation',
            'description' => 'TikTok Ads / TikTok Shop lead forms.',
        ],
        'google' => [
            'label' => 'Google Ads Lead Form',
            'description' => 'Google Ads extended lead forms.',
        ],
        'zalo' => [
            'label' => 'Zalo OA',
            'description' => 'Messages / forms from Zalo Official Account.',
        ],
        'landing' => [
            'label' => 'Landing Page (Ladipage, Web)',
            'description' => 'Each campaign has its own API URL (Marketing → Landing). Shared webhook below is fallback only.',
        ],
        'shopee' => [
            'label' => 'Shopee',
            'description' => 'Shopee orders / chat — via webhook or partner API.',
        ],
        'lazada' => [
            'label' => 'Lazada',
            'description' => 'Lazada orders — webhook from Open Platform.',
        ],
    ],
    'test' => [
        'unsupported' => 'Platform is not supported.',
        'success' => 'Test webhook sent successfully.',
        'sample_recorded' => 'Sample lead was recorded by the system.',
        'failed' => 'Test webhook failed: :error',
        'payload_failed' => 'Could not process test payload. Check configuration and enable the platform.',
        'sample_name' => 'Webhook test customer',
        'sample_product' => 'Demo product',
        'platform' => 'Platform',
        'lead_id' => 'Lead record ID',
        'status' => 'Processing status',
        'sample_phone' => 'Sample phone number',
    ],
];
