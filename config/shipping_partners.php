<?php

return [
    'providers' => [
        'viettel_post' => [
            'label' => 'Viettel Post',
            'description' => 'Tạo vận đơn, tính phí, đồng bộ trạng thái giao hàng.',
            'docs_url' => 'https://partner.viettelpost.vn/v2/cms/Document',
            'api_base_url' => 'https://partner.viettelpost.vn/v2',
            'tracking_url' => 'https://viettelpost.com.vn/tra-cuu-hanh-trinh-don?key={code}',
            'services' => [
                ['code' => 'VCN', 'label' => 'Chuyển phát nhanh'],
                ['code' => 'VTK', 'label' => 'Tiết kiệm'],
                ['code' => 'PHS', 'label' => 'Phát hỏa tốc'],
            ],
            'fields' => [
                'username' => ['label' => 'Username', 'secret' => false],
                'password' => ['label' => 'Password', 'secret' => true],
                'token' => ['label' => 'API Token', 'secret' => true],
                'customer_code' => ['label' => 'Mã khách hàng (GROUPADDRESS)', 'secret' => false],
            ],
        ],
        'ghn' => [
            'label' => 'GHN',
            'description' => 'Tích hợp GHN API cho đơn giao hàng và tracking.',
            'docs_url' => 'https://api.ghn.vn/home/docs/detail',
            'api_base_url' => 'https://online-gateway.ghn.vn/shiip/public-api/v2',
            'tracking_url' => 'https://donhang.ghn.vn/?order_code={code}',
            'services' => [
                ['code' => '2', 'label' => 'Hàng nhẹ (chuẩn)'],
                ['code' => '5', 'label' => 'Hàng nặng'],
            ],
            'fields' => [
                'shop_id' => ['label' => 'Shop ID', 'secret' => false],
                'token' => ['label' => 'Token', 'secret' => true],
                'client_id' => ['label' => 'Client ID', 'secret' => false],
            ],
        ],
        'ghtk' => [
            'label' => 'Giao Hàng Tiết Kiệm',
            'description' => 'Đẩy đơn sang GHTK và nhận trạng thái trả về.',
            'docs_url' => 'https://docs.giaohangtietkiem.vn/',
            'api_base_url' => 'https://services.giaohangtietkiem.vn',
            'tracking_url' => 'https://i.ghtk.vn/{code}',
            'services' => [
                ['code' => 'road', 'label' => 'Đường bộ'],
                ['code' => 'fly', 'label' => 'Đường bay'],
            ],
            'fields' => [
                'token' => ['label' => 'Token API', 'secret' => true],
                'pick_address_id' => ['label' => 'ID kho lấy hàng', 'secret' => false],
            ],
        ],
        'jnt' => [
            'label' => 'J&T Express',
            'description' => 'Tích hợp API tạo đơn và đồng bộ giao vận J&T.',
            'docs_url' => 'https://www.jtexpress.vn/',
            'api_base_url' => 'https://openapi.jtexpress.vn',
            'tracking_url' => 'https://jtexpress.vn/vi/tracking?type=track&billcode={code}',
            'services' => [
                ['code' => 'EZ', 'label' => 'Tiêu chuẩn'],
            ],
            'fields' => [
                'api_key' => ['label' => 'API Account / Key', 'secret' => true],
                'api_secret' => ['label' => 'Private Key', 'secret' => true],
                'client_code' => ['label' => 'Customer Code', 'secret' => false],
            ],
        ],
    ],
];
