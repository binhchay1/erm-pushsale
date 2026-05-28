<?php

return [
    'providers' => [
        'viettel_post' => [
            'label' => 'Viettel Post',
            'description' => 'Tạo vận đơn, tính phí, đồng bộ trạng thái giao hàng.',
            'docs_url' => 'https://api.viettelpost.vn/',
            'fields' => [
                'username' => ['label' => 'Username', 'secret' => false],
                'password' => ['label' => 'Password', 'secret' => true],
                'token' => ['label' => 'API Token', 'secret' => true],
                'customer_code' => ['label' => 'Mã khách hàng', 'secret' => false],
            ],
        ],
        'ghn' => [
            'label' => 'GHN',
            'description' => 'Tích hợp GHN API cho đơn giao hàng và tracking.',
            'docs_url' => 'https://api.ghn.vn/home/docs',
            'fields' => [
                'shop_id' => ['label' => 'Shop ID', 'secret' => false],
                'token' => ['label' => 'Token', 'secret' => true],
                'client_id' => ['label' => 'Client ID', 'secret' => false],
            ],
        ],
        'ghtk' => [
            'label' => 'Giao Hang Tiet Kiem',
            'description' => 'Đẩy đơn sang GHTK và nhận trạng thái trả về.',
            'docs_url' => 'https://docs.giaohangtietkiem.vn/',
            'fields' => [
                'token' => ['label' => 'Token', 'secret' => true],
                'pick_address_id' => ['label' => 'ID kho lấy hàng', 'secret' => false],
            ],
        ],
        'jnt' => [
            'label' => 'J&T Express',
            'description' => 'Tích hợp API tạo đơn và đồng bộ giao vận J&T.',
            'docs_url' => null,
            'fields' => [
                'api_key' => ['label' => 'API Key', 'secret' => true],
                'api_secret' => ['label' => 'API Secret', 'secret' => true],
                'client_code' => ['label' => 'Client Code', 'secret' => false],
            ],
        ],
    ],
];
