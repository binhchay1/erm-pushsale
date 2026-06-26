<?php

return [
    'test_actions' => [
        'shop' => 'Shop information',
        'fee' => 'Sample fee quote',
        'authenticate' => 'Verify token',
        'pick-addresses' => 'Pickup warehouses',
        'products' => 'Product list',
        'solutions' => 'Gam solutions',
        'login' => 'Login / verify token',
        'connection' => 'Test connection',
    ],
    'providers' => [
        'viettel_post' => [
            'label' => 'Viettel Post',
            'description' => 'Create shipments, calculate fees, sync delivery status.',
            'services' => [
                'VCN' => 'Express',
                'VTK' => 'Economy',
                'PHS' => 'Express fire delivery',
            ],
            'fields' => [
                'username' => 'Username',
                'password' => 'Password',
                'customer_code' => 'Customer code (GROUPADDRESS)',
            ],
        ],
        'ghn' => [
            'label' => 'Giao Hang Nhanh (GHN)',
            'description' => 'GHN API integration for shipments and tracking.',
            'services' => [
                '2' => 'Light goods (standard)',
                '5' => 'Heavy goods',
            ],
            'fields' => [
                'shop_id' => 'Shop ID',
                'client_id' => 'API client ID',
            ],
        ],
        'ghtk' => [
            'label' => 'Giao Hang Tiet Kiem (GHTK)',
            'description' => 'Push orders to GHTK and receive status updates.',
            'services' => [
                'road' => 'Road',
                'fly' => 'Air',
            ],
            'fields' => [
                'token' => 'API token',
                'partner_code' => 'Shop code / X-Client-Source',
                'pick_address_id' => 'GHTK pickup warehouse ID',
            ],
        ],
        'jnt' => [
            'label' => 'J&T Express',
            'description' => 'J&T API integration for shipments and tracking.',
            'services' => [
                'EZ' => 'Standard',
            ],
            'fields' => [
                'api_key' => 'API key',
                'api_secret' => 'API secret',
                'client_code' => 'Client code',
            ],
        ],
        'spx' => [
            'label' => 'SPX Express',
            'description' => 'SPX (Shopee Xpress) merchant API integration.',
            'services' => [
                'standard' => 'Standard',
            ],
            'fields' => [
                'user_id' => 'User ID',
                'secret_key' => 'Secret key',
                'account_id' => 'Account ID',
            ],
        ],
    ],
];
