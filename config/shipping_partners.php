<?php

$genericEndpointFields = [
    'base_url' => ['label' => 'API Base URL', 'secret' => false, 'required' => true],
    'api_token' => ['label' => 'API token', 'secret' => true, 'required' => true],
    'auth_header' => ['label' => 'Tên header xác thực', 'secret' => false, 'required' => false, 'default' => 'Authorization'],
    'auth_prefix' => ['label' => 'Tiền tố xác thực', 'secret' => false, 'required' => false, 'default' => 'Bearer'],
    'account_id' => ['label' => 'Mã tài khoản / shop', 'secret' => false, 'required' => false],
    'provider_code' => ['label' => 'Mã hãng tại hệ thống đối tác', 'secret' => false, 'required' => false],
    'create_path' => ['label' => 'Đường dẫn tạo vận đơn', 'secret' => false, 'required' => true, 'default' => '/shipments'],
    'status_path' => ['label' => 'Đường dẫn tra cứu ({tracking})', 'secret' => false, 'required' => true, 'default' => '/shipments/{tracking}'],
    'fee_path' => ['label' => 'Đường dẫn tính phí', 'secret' => false, 'required' => false, 'default' => '/rates'],
    'cancel_path' => ['label' => 'Đường dẫn hủy ({tracking})', 'secret' => false, 'required' => false, 'default' => '/shipments/{tracking}/cancel'],
    'label_path' => ['label' => 'Đường dẫn nhãn ({tracking})', 'secret' => false, 'required' => false, 'default' => '/shipments/{tracking}/label'],
];

$generic = static function (
    string $label,
    string $description,
    ?string $docsUrl = null,
    ?string $trackingUrl = null,
    array $fields = [],
    string $mode = 'direct_generic',
) use ($genericEndpointFields): array {
    return [
        'label' => $label,
        'description' => $description,
        'docs_url' => $docsUrl,
        'api_base_url' => null,
        'integration_mode' => $mode,
        'tracking_url' => $trackingUrl,
        'services' => [['code' => 'standard', 'label' => 'Tiêu chuẩn']],
        'fields' => array_merge($genericEndpointFields, $fields),
    ];
};

return [
    'verify_ssl' => filter_var(env('SHIPPING_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    'ca_bundle' => env('SHIPPING_CA_BUNDLE'),

    'default_geo' => [
        'province' => env('SHIPPING_DEFAULT_PROVINCE', 'Hà Nội'),
        'district' => env('SHIPPING_DEFAULT_DISTRICT', 'Quận Hoàn Kiếm'),
        'ward' => env('SHIPPING_DEFAULT_WARD', 'Phường Hàng Bài'),
    ],

    'pickup' => [
        'name' => env('SHIPPING_PICK_NAME', 'ERM Pushsale Kho'),
        'tel' => env('SHIPPING_PICK_TEL', '0900000000'),
        'address' => env('SHIPPING_PICK_ADDRESS', 'Kho ERM Pushsale'),
        'province' => env('SHIPPING_PICK_PROVINCE', 'Hà Nội'),
        'district' => env('SHIPPING_PICK_DISTRICT', 'Quận Cầu Giấy'),
        'ward' => env('SHIPPING_PICK_WARD'),
    ],

    'default_settings' => [
        'pickup_mode' => 'carrier_pickup',
        'inspection_mode' => 'view_only',
        'goods_type' => 'parcel',
        'insurance_enabled' => false,
        'allow_partial_delivery' => false,
        'auto_create_waybill' => false,
        'auto_restock_return' => true,
        'use_carrier_cod' => true,
        'fixed_receiver_phone' => null,
        'extra_services' => [],
        'sender_profile_id' => null,
    ],

    'providers' => [
        'manual' => [
            'label' => 'Thủ công',
            'description' => 'Tạo mã giao vận nội bộ, xuất kho và cập nhật trạng thái thủ công.',
            'integration_mode' => 'manual',
            'tracking_url' => null,
            'services' => [['code' => 'manual', 'label' => 'Giao hàng thủ công']],
            'fields' => [],
        ],
        'viettel_post' => [
            'label' => 'Viettel Post',
            'description' => 'Tạo vận đơn, tính phí, in nhãn, đồng bộ trạng thái và COD.',
            'docs_url' => 'https://partner.viettelpost.vn/v2/cms/Document',
            'api_base_url' => 'https://partner.viettelpost.vn/v2',
            'integration_mode' => 'direct',
            'tracking_url' => 'https://viettelpost.com.vn/tra-cuu-hanh-trinh-don?key={code}',
            'services' => [
                ['code' => 'VCN', 'label' => 'Chuyển phát nhanh'],
                ['code' => 'VTK', 'label' => 'Tiết kiệm'],
                ['code' => 'PHS', 'label' => 'Phát hỏa tốc'],
            ],
            'fields' => [
                'username' => ['label' => 'Tài khoản', 'secret' => false, 'required' => false, 'default' => env('VIETTEL_POST_USERNAME')],
                'password' => ['label' => 'Mật khẩu', 'secret' => true, 'required' => false, 'default' => env('VIETTEL_POST_PASSWORD')],
                'token' => ['label' => 'Token API', 'secret' => true, 'required' => true, 'default' => env('VIETTEL_POST_TOKEN')],
                'customer_code' => ['label' => 'Mã khách hàng / GROUPADDRESS', 'secret' => false, 'required' => true, 'default' => env('VIETTEL_POST_CUSTOMER_CODE')],
            ],
        ],
        'vnpost' => $generic(
            'VN Post',
            'Kết nối My Vietnam Post/VNPost theo tài khoản doanh nghiệp được cấp quyền API.',
            'https://my.vnpost.vn/',
            'https://ipostal.vnpost.vn/tra-cuu-buu-gui?code={code}',
            [
                'account' => ['label' => 'Tài khoản', 'secret' => false, 'required' => true],
                'customer_code' => ['label' => 'Mã khách hàng', 'secret' => false, 'required' => true],
                'contract_code' => ['label' => 'Mã hợp đồng', 'secret' => false, 'required' => false],
            ],
        ),
        'ghtk' => [
            'label' => 'Giao Hàng Tiết Kiệm',
            'description' => 'Đẩy đơn, nhận webhook, đồng bộ trạng thái giao hàng và đối soát COD.',
            'docs_url' => 'https://api.ghtk.vn/docs/submit-order/logistic-overview',
            'api_base_url' => 'https://services.giaohangtietkiem.vn',
            'api_staging_url' => 'https://services-staging.ghtklab.com',
            'use_sandbox' => filter_var(env('GHTK_USE_SANDBOX', false), FILTER_VALIDATE_BOOL),
            'integration_mode' => 'direct',
            'tracking_url' => 'https://i.ghtk.vn/{code}',
            'services' => [
                ['code' => 'road', 'label' => 'Đường bộ'],
                ['code' => 'fly', 'label' => 'Đường bay'],
            ],
            'fields' => [
                'token' => ['label' => 'Token API', 'secret' => true, 'required' => true, 'default' => env('GHTK_TOKEN')],
                'partner_code' => ['label' => 'Mã shop / X-Client-Source', 'secret' => false, 'required' => false, 'default' => env('GHTK_PARTNER_CODE', 'erm-pushsale')],
                'pick_address_id' => ['label' => 'ID kho lấy hàng', 'secret' => false, 'required' => false, 'default' => env('GHTK_PICK_ADDRESS_ID')],
            ],
        ],
        'ghn' => [
            'label' => 'Giao Hàng Nhanh',
            'description' => 'Tạo đơn, tính phí, in nhãn và tracking qua GHN Open API.',
            'docs_url' => 'https://api.ghn.vn/home/docs/detail',
            'api_base_url' => 'https://online-gateway.ghn.vn/shiip/public-api/v2',
            'integration_mode' => 'direct',
            'tracking_url' => 'https://donhang.ghn.vn/?order_code={code}',
            'services' => [
                ['code' => '2', 'label' => 'Hàng nhẹ'],
                ['code' => '5', 'label' => 'Hàng nặng'],
            ],
            'fields' => [
                'shop_id' => ['label' => 'Shop ID', 'secret' => false, 'required' => true, 'default' => env('GHN_SHOP_ID')],
                'token' => ['label' => 'Token API', 'secret' => true, 'required' => true, 'default' => env('GHN_TOKEN')],
                'client_id' => ['label' => 'Client ID', 'secret' => false, 'required' => false, 'default' => env('GHN_CLIENT_ID')],
            ],
        ],
        'jnt' => [
            'label' => 'J&T Express',
            'description' => 'Kết nối API merchant J&T để tạo đơn, tracking, hủy và đối soát.',
            'docs_url' => 'https://jtexpress.vn/',
            'api_base_url' => env('JNT_API_BASE_URL', 'https://openapi.jtexpress.vn'),
            'integration_mode' => 'direct',
            'tracking_url' => 'https://jtexpress.vn/vi/tracking?type=track&billcode={code}',
            'services' => [['code' => 'EZ', 'label' => 'Tiêu chuẩn']],
            'fields' => [
                'api_key' => ['label' => 'API key', 'secret' => true, 'required' => true, 'default' => env('JNT_API_KEY')],
                'api_secret' => ['label' => 'API secret', 'secret' => true, 'required' => true, 'default' => env('JNT_API_SECRET')],
                'client_code' => ['label' => 'Mã khách hàng', 'secret' => false, 'required' => true, 'default' => env('JNT_CLIENT_CODE')],
            ],
        ],
        'ems' => $generic('EMS', 'Kết nối tài khoản EMS doanh nghiệp và đồng bộ vận đơn/COD.', 'https://bill.ems.com.vn/', null, [
            'customer_code' => ['label' => 'Mã khách hàng EMS', 'secret' => false, 'required' => true],
        ]),
        'supership' => $generic('SuperShip', 'Kết nối SuperShip theo API/token được cấp cho shop.', 'https://supership.vn/', null),
        'best' => $generic('BEST Express', 'Kết nối BEST Express theo hợp đồng API doanh nghiệp.', 'https://best-inc.vn/', null, [
            'customer_code' => ['label' => 'Mã khách hàng', 'secret' => false, 'required' => true],
        ]),
        'heyu' => $generic('HeyU', 'Kết nối giao hàng nội thành HeyU theo tài khoản đối tác.', 'https://heyu.vn/', null),
        'boxme' => $generic('BoxMe', 'Dùng BoxMe như nền tảng fulfillment/multi-carrier.', 'https://boxme.asia/vi/wiki/', null, [
            'warehouse_id' => ['label' => 'Mã kho BoxMe', 'secret' => false, 'required' => false],
        ]),
        'chimcat' => $generic('Chim Cắt', 'Kết nối đối tác giao vận Chim Cắt theo endpoint được cấp.', null, null),
        'ship60' => $generic('Ship60', 'Kết nối Ship60 theo endpoint/token của tài khoản doanh nghiệp.', 'https://ship60.com/', null),
        'holaship' => $generic('HolaShip', 'Kết nối HolaShip, đồng bộ trạng thái, phí và COD.', 'https://holaship.vn/', null),
        'ahamove' => $generic('AhaMove', 'Giao hàng tức thời AhaMove; phù hợp đơn nội thành và webhook thời gian thực.', 'https://developers.ahamove.com/', null, [
            'user_mobile' => ['label' => 'Số điện thoại tài khoản', 'secret' => false, 'required' => true],
            'city_id' => ['label' => 'Mã thành phố', 'secret' => false, 'required' => false, 'default' => 'HAN'],
        ]),
        'ninjavan' => $generic('Ninja Van', 'Kết nối Ninja Van theo tài khoản shipper/enterprise được cấp.', 'https://www.ninjavan.co/en-vn', null, [
            'client_id' => ['label' => 'Client ID', 'secret' => false, 'required' => true],
            'client_secret' => ['label' => 'Client secret', 'secret' => true, 'required' => true],
        ]),
        'spx' => [
            'label' => 'SPX Express',
            'description' => 'Kết nối SPX theo API merchant được cấp quyền riêng.',
            'docs_url' => 'https://spx.vn/',
            'api_base_url' => env('SPX_API_BASE_URL', ''),
            'integration_mode' => 'direct',
            'tracking_url' => 'https://spx.vn/vi/?track={code}',
            'services' => [
                ['code' => 'standard', 'label' => 'Tiêu chuẩn'],
                ['code' => 'express', 'label' => 'Nhanh'],
            ],
            'fields' => [
                'user_id' => ['label' => 'User ID', 'secret' => false, 'required' => true, 'default' => env('SPX_USER_ID')],
                'secret_key' => ['label' => 'Secret key', 'secret' => true, 'required' => true, 'default' => env('SPX_SECRET_KEY')],
                'account_id' => ['label' => 'Account ID', 'secret' => false, 'required' => true, 'default' => env('SPX_ACCOUNT_ID')],
                'api_version' => ['label' => 'Phiên bản API', 'secret' => false, 'required' => false, 'default' => env('SPX_API_VERSION', 'v1')],
                'create_order_path' => ['label' => 'Đường dẫn tạo đơn', 'secret' => false, 'required' => true, 'default' => env('SPX_CREATE_ORDER_PATH')],
                'order_detail_path' => ['label' => 'Đường dẫn trạng thái', 'secret' => false, 'required' => true, 'default' => env('SPX_ORDER_DETAIL_PATH')],
                'fee_path' => ['label' => 'Đường dẫn tính phí', 'secret' => false, 'required' => false, 'default' => env('SPX_FEE_PATH')],
                'cancel_order_path' => ['label' => 'Đường dẫn hủy', 'secret' => false, 'required' => false, 'default' => env('SPX_CANCEL_ORDER_PATH')],
                'label_path' => ['label' => 'Đường dẫn nhãn', 'secret' => false, 'required' => false, 'default' => env('SPX_LABEL_PATH')],
                'test_connection_path' => ['label' => 'Đường dẫn test', 'secret' => false, 'required' => false, 'default' => env('SPX_TEST_CONNECTION_PATH')],
                'signature_header' => ['label' => 'Header chữ ký', 'secret' => false, 'required' => false, 'default' => env('SPX_SIGNATURE_HEADER', 'X-Signature')],
                'timestamp_header' => ['label' => 'Header thời gian', 'secret' => false, 'required' => false, 'default' => env('SPX_TIMESTAMP_HEADER', 'X-Timestamp')],
            ],
        ],
        'shippo' => $generic('Shippo', 'Kết nối Shippo hoặc cổng vận chuyển quốc tế theo API thống nhất.', 'https://docs.goshippo.com/', null, [], 'aggregator'),
        'aggregator' => $generic(
            'Đối tác trung gian / Multi-carrier API',
            'Kết nối một bên trung gian đã tích hợp nhiều hãng; ERM dùng payload chuẩn và nhận webhook chuẩn hóa.',
            null,
            null,
            [
                'webhook_signature_header' => ['label' => 'Header chữ ký webhook', 'secret' => false, 'required' => false, 'default' => 'X-Signature'],
                'webhook_signature_algorithm' => ['label' => 'Thuật toán chữ ký', 'secret' => false, 'required' => false, 'default' => 'sha256'],
            ],
            'aggregator',
        ),
        'tiktok_logistics' => $generic('TikTok Shop Logistics', 'Kết nối logistics TikTok Shop qua API seller/đối tác được cấp.', 'https://partner.tiktokshop.com/docv2', null),
        'shopee_logistics' => $generic('Shopee Logistics', 'Kết nối logistics Shopee qua Open Platform/đối tác được cấp.', 'https://open.shopee.com/', null),
    ],
];
