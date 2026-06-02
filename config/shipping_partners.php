<?php

return [
    // Verify SSL khi gọi API hãng vận chuyển. Đặt false chỉ khi debug tạm (không dùng production).
    'verify_ssl' => filter_var(env('SHIPPING_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    // Đường dẫn CA bundle; mặc định dùng certs/cacert.pem trong project nếu file tồn tại.
    'ca_bundle' => env('SHIPPING_CA_BUNDLE'),

    // Địa chỉ giao mặc định khi đơn chưa có shipping_geo.
    'default_geo' => [
        'province' => env('SHIPPING_DEFAULT_PROVINCE', 'Hà Nội'),
        'district' => env('SHIPPING_DEFAULT_DISTRICT', 'Quận Hoàn Kiếm'),
        'ward' => env('SHIPPING_DEFAULT_WARD', 'Phường Hàng Bài'),
    ],

    // Kho lấy hàng mặc định khi đơn/kho chưa cấu hình.
    'pickup' => [
        'name' => env('SHIPPING_PICK_NAME', 'ERM SaleOps Kho'),
        'tel' => env('SHIPPING_PICK_TEL', '0900000000'),
        'address' => env('SHIPPING_PICK_ADDRESS', 'Kho demo SaleOps'),
        'province' => env('SHIPPING_PICK_PROVINCE', 'Hà Nội'),
        'district' => env('SHIPPING_PICK_DISTRICT', 'Quận Cầu Giấy'),
        'ward' => env('SHIPPING_PICK_WARD'),
    ],

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
                'username' => ['label' => 'Username', 'secret' => false, 'default' => env('VIETTEL_POST_USERNAME')],
                'password' => ['label' => 'Password', 'secret' => true, 'default' => env('VIETTEL_POST_PASSWORD')],
                'token' => ['label' => 'API Token', 'secret' => true, 'default' => env('VIETTEL_POST_TOKEN')],
                'customer_code' => ['label' => 'Mã khách hàng (GROUPADDRESS)', 'secret' => false, 'default' => env('VIETTEL_POST_CUSTOMER_CODE')],
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
                'shop_id' => ['label' => 'Shop ID', 'secret' => false, 'default' => env('GHN_SHOP_ID')],
                'token' => ['label' => 'Token', 'secret' => true, 'default' => env('GHN_TOKEN')],
                'client_id' => ['label' => 'Client ID', 'secret' => false, 'default' => env('GHN_CLIENT_ID')],
            ],
        ],
        'ghtk' => [
            'label' => 'Giao Hàng Tiết Kiệm',
            'description' => 'Đẩy đơn sang GHTK và nhận trạng thái trả về.',
            'docs_url' => 'https://api.ghtk.vn/docs/submit-order/logistic-overview',
            'api_base_url' => 'https://services.giaohangtietkiem.vn',
            'api_staging_url' => 'https://services-staging.ghtklab.com',
            'use_sandbox' => filter_var(env('GHTK_USE_SANDBOX', false), FILTER_VALIDATE_BOOL),
            'tracking_url' => 'https://i.ghtk.vn/{code}',
            'services' => [
                ['code' => 'road', 'label' => 'Đường bộ'],
                ['code' => 'fly', 'label' => 'Đường bay'],
            ],
            'fields' => [
                'token' => ['label' => 'Token API', 'secret' => true, 'default' => env('GHTK_TOKEN')],
                'partner_code' => ['label' => 'Mã shop / X-Client-Source', 'secret' => false, 'default' => env('GHTK_PARTNER_CODE', 'saleops')],
                'pick_address_id' => ['label' => 'ID kho lấy hàng GHTK', 'secret' => false, 'default' => env('GHTK_PICK_ADDRESS_ID')],
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
                'api_key' => ['label' => 'API Account / Key', 'secret' => true, 'default' => env('JNT_API_KEY')],
                'api_secret' => ['label' => 'Private Key', 'secret' => true, 'default' => env('JNT_API_SECRET')],
                'client_code' => ['label' => 'Customer Code', 'secret' => false, 'default' => env('JNT_CLIENT_CODE')],
            ],
        ],
    ],
];
