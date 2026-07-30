<?php

/**
 * FAB print profiles for warehouse/accounting operations.
 * UI + provider matching are data-driven — do not hardcode in React.
 */
return [
    'fab_order' => ['internal', 'shopee', 'tiktok', 'ghtk', 'jnt', 'spx'],

    'profiles' => [
        'internal' => [
            'key' => 'internal',
            'title' => 'In đơn',
            'tone' => 'success',
            'ui' => 'internal',
            'providers' => null,
            'match_tokens' => [],
            'max_quantity' => 2000,
            'templates' => [
                ['value' => 'mau_1_a4_a5', 'label' => 'Mẫu in 1 (A4, A5)'],
                ['value' => 'mau_2_a6', 'label' => 'Mẫu in 2 (A6)'],
                ['value' => 'mau_3_nhieu_don', 'label' => 'Mẫu in nhiều đơn / tờ'],
            ],
            'sort_options' => [
                ['value' => 'closed_at', 'label' => 'Ngày chốt'],
                ['value' => 'data_arrived_at', 'label' => 'Ngày data về'],
                ['value' => 'order_code', 'label' => 'Mã đơn'],
                ['value' => 'warehouse', 'label' => 'Kho'],
                ['value' => 'province', 'label' => 'Tỉnh/TP giao'],
            ],
            'tabs' => [
                ['value' => 'label', 'label' => 'Phiếu gửi hàng'],
                ['value' => 'invoice', 'label' => 'Hóa đơn'],
                ['value' => 'settings', 'label' => 'Thiết lập chung'],
            ],
            'toggles' => [
                ['key' => 'print_combo', 'label' => 'In combo'],
                ['key' => 'print_combo_detail', 'label' => 'In combo chi tiết'],
                ['key' => 'hide_product_name', 'label' => 'Ẩn Tên sản phẩm'],
                ['key' => 'show_sku', 'label' => 'Hiển thị mã SP'],
                ['key' => 'show_unit_price', 'label' => 'Hiển thị đơn giá', 'default' => true],
                ['key' => 'show_discount', 'label' => 'Hiển thị CK'],
                ['key' => 'hide_shipping_fee', 'label' => 'Ẩn phí VC'],
                ['key' => 'mask_customer_phone', 'label' => 'Che số ĐT của khách'],
                ['key' => 'show_sale_phone', 'label' => 'Hiển thị SĐT của sale'],
                ['key' => 'show_print_date', 'label' => 'Hiển thị Ngày in', 'default' => true],
                ['key' => 'sender_by_warehouse', 'label' => 'Người gửi theo kho', 'default' => true],
                ['key' => 'sender_is_ctv', 'label' => 'Người gửi là CTV'],
                ['key' => 'use_pushsale_as_tracking', 'label' => 'Dùng Pushsale làm mã vận đơn'],
                ['key' => 'hide_qr', 'label' => 'Ẩn QR code'],
                ['key' => 'print_invoice', 'label' => 'In hóa đơn'],
                ['key' => 'multi_per_page', 'label' => 'In nhiều đơn trên một tờ'],
            ],
        ],
        'shopee' => [
            'key' => 'shopee',
            'title' => 'In đơn mẫu Shopee',
            'tone' => 'warning',
            'ui' => 'platform',
            'providers' => ['shopee_logistics', 'shopee'],
            'match_tokens' => ['shopee'],
            'max_quantity' => 3000,
            'templates' => [
                ['value' => 'NORMAL_AIR_WAYBILL', 'label' => 'NORMAL AIR WAYBILL'],
                ['value' => 'THERMAL', 'label' => 'THERMAL'],
            ],
            'tabs' => [
                ['value' => 'print', 'label' => 'Đơn in'],
                ['value' => 'remaining', 'label' => 'Còn lại'],
            ],
            'supports_size' => false,
        ],
        'tiktok' => [
            'key' => 'tiktok',
            'title' => 'In đơn mẫu TikTok',
            'tone' => 'warning',
            'ui' => 'platform',
            'providers' => ['tiktok_logistics', 'tiktok'],
            'match_tokens' => ['tiktok'],
            'max_quantity' => 3000,
            'templates' => [
                ['value' => 'SH', 'label' => 'SH'],
                ['value' => 'STANDARD', 'label' => 'STANDARD'],
            ],
            'tabs' => [
                ['value' => 'print', 'label' => 'Đơn in'],
                ['value' => 'error', 'label' => 'Đơn lỗi'],
            ],
            'supports_size' => true,
            'sizes' => [
                ['value' => 'A6', 'label' => 'A6'],
                ['value' => 'A5', 'label' => 'A5'],
                ['value' => 'A4', 'label' => 'A4'],
            ],
        ],
        'ghtk' => [
            'key' => 'ghtk',
            'title' => 'In đơn mẫu GHTK',
            'tone' => 'success',
            'ui' => 'carrier',
            'providers' => ['ghtk'],
            'match_tokens' => ['ghtk', 'giao hàng tiết kiệm', 'giaohangtietkiem'],
            'max_quantity' => 3000,
            'templates' => [
                ['value' => 'A5', 'label' => 'Mẫu in A5'],
                ['value' => 'A6', 'label' => 'Mẫu in A6'],
            ],
            'orientations' => [
                ['value' => 'landscape', 'label' => 'In ngang'],
                ['value' => 'portrait', 'label' => 'In dọc'],
            ],
            'pretty_print' => true,
            'group_by_warehouse' => true,
        ],
        'jnt' => [
            'key' => 'jnt',
            'title' => 'In đơn mẫu J&T',
            'tone' => 'success',
            'ui' => 'merge',
            'providers' => ['jnt'],
            'match_tokens' => ['jnt', 'j&t'],
            'max_quantity' => 3000,
            'merge_all_label' => 'Gộp tất cả đơn',
        ],
        'spx' => [
            'key' => 'spx',
            'title' => 'In đơn mẫu SPX',
            'tone' => 'success',
            'ui' => 'merge',
            'providers' => ['spx'],
            'match_tokens' => ['spx', 'shopee xpress'],
            'max_quantity' => 3000,
            'merge_all_label' => 'Gộp tất cả đơn',
        ],
    ],
];
