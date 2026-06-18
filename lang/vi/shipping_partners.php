<?php

return [
    'test_actions' => [
        'shop' => 'Thông tin cửa hàng',
        'fee' => 'Tính phí mẫu',
        'authenticate' => 'Kiểm tra Token',
        'pick-addresses' => 'Danh sách kho lấy hàng',
        'products' => 'Danh sách sản phẩm',
        'solutions' => 'Giải pháp Gam',
        'login' => 'Đăng nhập / kiểm tra token',
    ],
    'providers' => [
        'viettel_post' => [
            'label' => 'Viettel Post',
            'description' => 'Tạo vận đơn, tính phí, đồng bộ trạng thái giao hàng.',
            'services' => [
                'VCN' => 'Chuyển phát nhanh',
                'VTK' => 'Tiết kiệm',
                'PHS' => 'Phát hỏa tốc',
            ],
            'fields' => [
                'username' => 'Tên đăng nhập',
                'password' => 'Mật khẩu',
                'customer_code' => 'Mã khách hàng (GROUPADDRESS)',
            ],
        ],
        'ghn' => [
            'label' => 'Giao Hàng Nhanh (GHN)',
            'description' => 'Tích hợp GHN API cho đơn giao hàng và tracking.',
            'services' => [
                '2' => 'Hàng nhẹ (chuẩn)',
                '5' => 'Hàng nặng',
            ],
            'fields' => [
                'shop_id' => 'Mã cửa hàng (Shop ID)',
                'client_id' => 'Mã khách hàng API',
            ],
        ],
        'ghtk' => [
            'label' => 'Giao Hàng Tiết Kiệm',
            'description' => 'Đẩy đơn sang GHTK và nhận trạng thái trả về.',
            'services' => [
                'road' => 'Đường bộ',
                'fly' => 'Đường bay',
            ],
            'fields' => [
                'token' => 'Token API',
                'partner_code' => 'Mã shop / X-Client-Source',
                'pick_address_id' => 'ID kho lấy hàng GHTK',
            ],
        ],
        'jnt' => [
            'label' => 'J&T Express',
            'description' => 'Tích hợp API tạo đơn và đồng bộ giao vận J&T.',
            'services' => [
                'EZ' => 'Tiêu chuẩn',
            ],
            'fields' => [
                'api_key' => 'Khóa API',
                'api_secret' => 'Khóa bí mật',
                'client_code' => 'Mã khách hàng',
            ],
        ],
        'spx' => [
            'label' => 'SPX Express',
            'description' => 'Tích hợp SPX (Shopee Xpress) theo API merchant được cấp quyền.',
            'services' => [
                'standard' => 'Tiêu chuẩn',
            ],
            'fields' => [
                'user_id' => 'Mã người dùng',
                'secret_key' => 'Khóa bí mật',
                'account_id' => 'Mã tài khoản',
            ],
        ],
    ],
];
