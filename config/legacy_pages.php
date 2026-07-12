<?php

return [
    '1.1.2' => [
        'title' => 'Lịch sử đăng ký gói dịch vụ',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'unit_payment',
                'label' => 'Đơn vị / Mã thanh toán',
                'format' => 'text',
            ],
            [
                'key' => 'contract_type',
                'label' => 'Loại hợp đồng',
                'format' => 'text',
            ],
            [
                'key' => 'description',
                'label' => 'Mô tả',
                'format' => 'text',
            ],
            [
                'key' => 'amount',
                'label' => 'Giá trị',
                'format' => 'currency',
                'align' => 'right',
            ],
            [
                'key' => 'paid_at',
                'label' => 'Ngày thanh toán',
                'format' => 'datetime',
            ],
            [
                'key' => 'duration_months',
                'label' => 'Thời gian sử dụng (tháng)',
                'format' => 'number',
            ],
            [
                'key' => 'expires_at',
                'label' => 'Thời gian hết hạn',
                'format' => 'datetime',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
        'filters' => ['contract_type', 'search', 'date_range'],
    ],
    '1.2.1' => [
        'title' => 'Danh sách nhân viên',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'select',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Họ tên',
                'format' => 'text',
            ],
            [
                'key' => 'role',
                'label' => 'Chức vụ',
                'format' => 'text',
            ],
            [
                'key' => 'employee_code',
                'label' => 'Mã nhân viên',
                'format' => 'text',
            ],
            [
                'key' => 'base_salary',
                'label' => 'Lương cứng',
                'format' => 'currency',
                'align' => 'right',
            ],
            [
                'key' => 'phone',
                'label' => 'Số điện thoại',
                'format' => 'text',
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'format' => 'text',
            ],
            [
                'key' => 'leader',
                'label' => 'Trưởng nhóm',
                'format' => 'text',
            ],
            [
                'key' => 'receive_data',
                'label' => 'Nhận dữ liệu',
                'format' => 'boolean',
            ],
            [
                'key' => 'shift',
                'label' => 'Ca làm việc',
                'format' => 'text',
            ],
            [
                'key' => 'active',
                'label' => 'Đang sử dụng',
                'format' => 'boolean',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Ngày cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thao tác',
                'format' => 'text',
            ],
        ],
        'source' => 'users',
        'create_url' => '/admin/users/create',
        'filters' => ['role', 'leader', 'receive_data', 'active', 'search'],
    ],
    '1.2.2' => [
        'title' => 'Quản lý đội, nhóm',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'type',
                'label' => 'Loại nhóm',
                'format' => 'text',
            ],
            [
                'key' => 'code',
                'label' => 'Mã',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên',
                'format' => 'text',
            ],
            [
                'key' => 'leader',
                'label' => 'Trưởng nhóm',
                'format' => 'text',
            ],
            [
                'key' => 'member_count',
                'label' => 'Số thành viên',
                'format' => 'number',
            ],
            [
                'key' => 'members',
                'label' => 'Thành viên',
                'format' => 'text',
            ],
            [
                'key' => 'parent',
                'label' => 'Nhóm liên kết',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thêm',
                'format' => 'text',
            ],
        ],
        'source' => 'teams',
        'create_url' => '/admin/teams/create',
        'filters' => ['type', 'leader', 'search'],
    ],
    '1.2.3' => [
        'title' => 'Ca làm việc',
        'columns' => [
            [
                'key' => 'name',
                'label' => 'Ca làm việc',
                'format' => 'text',
            ],
            [
                'key' => 'from_hour',
                'label' => 'Từ (h)',
                'format' => 'text',
            ],
            [
                'key' => 'to_hour',
                'label' => 'Đến (h)',
                'format' => 'text',
            ],
            [
                'key' => 'note',
                'label' => 'Ghi chú',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
        'kind' => 'form',
    ],
    '1.2.4' => [
        'title' => 'Danh sách cấu hình chia số',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên',
                'format' => 'text',
            ],
            [
                'key' => 'allocation_rule',
                'label' => 'Kiểu số - Người nhận - Cách chia',
                'format' => 'text',
            ],
            [
                'key' => 'products',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'sales',
                'label' => 'Sales',
                'format' => 'text',
            ],
            [
                'key' => 'care_users',
                'label' => 'CSKH',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '1.2.5' => [
        'title' => 'Cấu hình tài khoản xem báo cáo',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'account',
                'label' => 'Tài khoản',
                'format' => 'text',
            ],
            [
                'key' => 'visible_teams',
                'label' => 'Nhóm được xem báo cáo',
                'format' => 'text',
            ],
            [
                'key' => 'team_type',
                'label' => 'Kiểu nhóm',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thêm',
                'format' => 'text',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '1.2.6' => [
        'title' => 'Danh sách cấu hình chia số care đơn',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'care_user',
                'label' => 'User care đơn',
                'format' => 'text',
            ],
            [
                'key' => 'quota',
                'label' => 'Định mức',
                'format' => 'number',
            ],
            [
                'key' => 'receive_data',
                'label' => 'Nhận data',
                'format' => 'boolean',
            ],
            [
                'key' => 'sales_teams',
                'label' => 'Nhóm Sales',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thao tác',
                'format' => 'text',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '1.3.1' => [
        'title' => 'Quản lý sản phẩm',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'category',
                'label' => 'Phân loại',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Tên / mã sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'unit',
                'label' => 'Đ.vị tính',
                'format' => 'text',
            ],
            [
                'key' => 'cost_price',
                'label' => 'Giá nhập',
                'format' => 'currency',
                'align' => 'right',
            ],
            [
                'key' => 'unit_price',
                'label' => 'Đơn giá',
                'format' => 'currency',
                'align' => 'right',
            ],
            [
                'key' => 'vat',
                'label' => 'VAT (%)',
                'format' => 'percent',
            ],
            [
                'key' => 'vat_code',
                'label' => 'Mã VAT',
                'format' => 'text',
            ],
            [
                'key' => 'price_after_vat',
                'label' => 'Đơn giá sau VAT (tham khảo)',
                'format' => 'currency',
                'align' => 'right',
            ],
            [
                'key' => 'weight',
                'label' => 'KL(gram)',
                'format' => 'number',
            ],
            [
                'key' => 'inactive',
                'label' => 'Ngừng KD',
                'format' => 'boolean',
            ],
            [
                'key' => 'marketing',
                'label' => 'Marketing',
                'format' => 'boolean',
            ],
            [
                'key' => 'sale',
                'label' => 'Sale',
                'format' => 'boolean',
            ],
            [
                'key' => 'care',
                'label' => 'Chăm sóc KH',
                'format' => 'boolean',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thao tác',
                'format' => 'text',
            ],
        ],
        'source' => 'products',
        'create_url' => '/admin/products/create',
        'dialogs' => ['1.3.1-dialog-create', '1.3.1-dialog-ph#U00e2n lo#U1ea1i s#U1ea3n ph#U1ea9m', '1.3.1-dialog-thu#U1ed9c t#U00ednh s#U1ea3n ph#U1ea9m', '1.3.1-dialog-thu#U1ed9c t#U00ednh gi#U00e1 tr#U1ecb'],
    ],
    '1.3.2' => [
        'title' => 'Danh sách combo',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'code',
                'label' => 'Mã combo',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên combo',
                'format' => 'text',
            ],
            [
                'key' => 'product_count',
                'label' => 'Tổng số SP',
                'format' => 'number',
            ],
            [
                'key' => 'original_total',
                'label' => 'Tổng giá gốc',
                'format' => 'currency',
                'align' => 'right',
            ],
            [
                'key' => 'combo_total',
                'label' => 'Tổng giá combo',
                'format' => 'currency',
                'align' => 'right',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái',
                'format' => 'status',
            ],
            [
                'key' => 'applied_at',
                'label' => 'Thời gian áp dụng',
                'format' => 'text',
            ],
            [
                'key' => 'limit_quantity',
                'label' => 'Số lượng giới hạn',
                'format' => 'number',
            ],
            [
                'key' => 'sold',
                'label' => 'Đã bán',
                'format' => 'number',
            ],
            [
                'key' => 'remaining',
                'label' => 'Còn lại',
                'format' => 'number',
            ],
            [
                'key' => 'shipping_support',
                'label' => 'Hỗ trợ phí VC',
                'format' => 'currency',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'combos',
        'editable' => true,
        'dialogs' => ['1.3.2-dialog-create'],
    ],
    '1.7.1' => [
        'title' => 'Lịch sử đăng nhập',
        'columns' => [
            [
                'key' => 'ip_address',
                'label' => 'IPAddress',
                'format' => 'text',
            ],
            [
                'key' => 'company',
                'label' => 'Đơn vị',
                'format' => 'text',
            ],
            [
                'key' => 'account',
                'label' => 'Tài khoản',
                'format' => 'text',
            ],
            [
                'key' => 'access_code',
                'label' => 'Mã truy cập',
                'format' => 'text',
            ],
            [
                'key' => 'browser',
                'label' => 'Mã browser',
                'format' => 'text',
            ],
            [
                'key' => 'created_at',
                'label' => 'Ngày thực hiện',
                'format' => 'datetime',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái',
                'format' => 'status',
            ],
        ],
        'source' => 'activity_logs',
        'filters' => ['role', 'user', 'status', 'sort', 'search', 'date_range'],
    ],
    '1.7.2' => [
        'title' => 'Quản lý cho phép tài khoản đăng nhập',
        'columns' => [
            [
                'key' => 'company',
                'label' => 'Đơn vị',
                'format' => 'text',
            ],
            [
                'key' => 'account',
                'label' => 'Tài khoản',
                'format' => 'text',
            ],
            [
                'key' => 'access_code',
                'label' => 'Mã truy cập',
                'format' => 'text',
            ],
            [
                'key' => 'login_at',
                'label' => 'Ngày đăng nhập',
                'format' => 'datetime',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái',
                'format' => 'status',
            ],
            [
                'key' => 'actions',
                'label' => 'Thao tác',
                'format' => 'text',
            ],
        ],
        'source' => 'login_permissions',
        'editable' => true,
    ],
    '1.7.3' => [
        'title' => 'Lịch sử lọc data chốt đơn',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'filter_form',
                'label' => 'Form lọc / Trang lọc / Dữ liệu lọc tối đa',
                'format' => 'text',
            ],
            [
                'key' => 'closing_status',
                'label' => 'Trạng thái chốt đơn',
                'format' => 'text',
            ],
            [
                'key' => 'delivery_status',
                'label' => 'Trạng thái giao hàng',
                'format' => 'text',
            ],
            [
                'key' => 'date_filter',
                'label' => 'Kiểu ngày / Ngày lọc',
                'format' => 'text',
            ],
            [
                'key' => 'user',
                'label' => 'User',
                'format' => 'text',
            ],
            [
                'key' => 'created_at',
                'label' => 'Ngày lọc',
                'format' => 'datetime',
            ],
        ],
        'source' => 'activity_logs',
    ],
    '1.8.1' => [
        'title' => 'Quản lý danh mục tác nghiệp',
        'columns' => [
            [
                'key' => 'id',
                'label' => 'Id',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên',
                'format' => 'text',
            ],
            [
                'key' => 'sort_order',
                'label' => 'STT',
                'format' => 'number',
            ],
            [
                'key' => 'is_start',
                'label' => 'Khởi đầu',
                'format' => 'boolean',
            ],
            [
                'key' => 'pool',
                'label' => 'Kho số',
                'format' => 'boolean',
            ],
            [
                'key' => 'duration',
                'label' => 'Sửa giờ',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thêm',
                'format' => 'text',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '1.8.2' => [
        'title' => 'Thiết lập tác nghiệp',
        'columns' => [
            [
                'key' => 'id',
                'label' => 'Id',
                'format' => 'text',
            ],
            [
                'key' => 'condition',
                'label' => 'Nếu',
                'format' => 'text',
            ],
            [
                'key' => 'result',
                'label' => 'Kết quả',
                'format' => 'text',
            ],
            [
                'key' => 'next_operation',
                'label' => 'Thì',
                'format' => 'text',
            ],
            [
                'key' => 'delay',
                'label' => 'Sau bao lâu',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thêm',
                'format' => 'text',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '1.9' => [
        'title' => 'Thiết lập chiết khấu, COD',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'order_from',
                'label' => 'Giá trị đơn hàng từ (trở lên)',
                'format' => 'currency',
            ],
            [
                'key' => 'discount_value',
                'label' => 'Giá trị chiết khấu',
                'format' => 'currency',
            ],
            [
                'key' => 'calculation_type',
                'label' => 'Tính theo',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thêm',
                'format' => 'text',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
        'kind' => 'split',
    ],
    '1.10' => [
        'title' => 'Import contact',
        'columns' => [
            [
                'key' => 'filename',
                'label' => 'Tên file',
                'format' => 'text',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái',
                'format' => 'status',
            ],
            [
                'key' => 'total_rows',
                'label' => 'Tổng dòng',
                'format' => 'number',
            ],
            [
                'key' => 'valid_rows',
                'label' => 'Hợp lệ',
                'format' => 'number',
            ],
            [
                'key' => 'invalid_rows',
                'label' => 'Không hợp lệ',
                'format' => 'number',
            ],
            [
                'key' => 'created_at',
                'label' => 'Ngày import',
                'format' => 'datetime',
            ],
        ],
        'source' => 'lead_imports',
        'kind' => 'import',
    ],
    '1.11' => [
        'title' => 'Cấu hình Facebook của đơn vị',
        'columns' => [
            [
                'key' => 'fanpage',
                'label' => 'Fanpage',
                'format' => 'text',
            ],
            [
                'key' => 'fb_creator',
                'label' => 'FB Creator',
                'format' => 'text',
            ],
            [
                'key' => 'pushsale_user',
                'label' => 'Pushsale User',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'integrations',
        'editable' => true,
    ],
    '1.13.1' => [
        'title' => 'Quản lý số blacklist',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'phone',
                'label' => 'Số blacklist',
                'format' => 'text',
            ],
            [
                'key' => 'reason',
                'label' => 'Lý do',
                'format' => 'text',
            ],
            [
                'key' => 'order_code',
                'label' => 'Đơn hàng',
                'format' => 'text',
            ],
            [
                'key' => 'creation_type',
                'label' => 'Kiểu tạo',
                'format' => 'text',
            ],
            [
                'key' => 'creator',
                'label' => 'Người tạo',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '2.4.1' => [
        'title' => 'Kết nối dữ liệu',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'marketer',
                'label' => 'Marketing',
                'format' => 'text',
            ],
            [
                'key' => 'source',
                'label' => 'Tên nguồn kết nối / Url nguồn dữ liệu',
                'format' => 'text',
            ],
            [
                'key' => 'channel',
                'label' => 'Loại kết nối / Kênh quảng cáo',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'sale_priority',
                'label' => 'Ưu tiên sale',
                'format' => 'text',
            ],
            [
                'key' => 'allocation',
                'label' => 'Cấu hình chia số',
                'format' => 'text',
            ],
            [
                'key' => 'webhook_url',
                'label' => 'Url kết nối V2',
                'format' => 'text',
            ],
            [
                'key' => 'manual_import',
                'label' => 'Nhập TC',
                'format' => 'boolean',
            ],
            [
                'key' => 'approved',
                'label' => 'Duyệt',
                'format' => 'boolean',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thêm',
                'format' => 'text',
            ],
        ],
        'source' => 'marketing_sources',
        'template_alias' => '2.4.1',
    ],
    '2.4.2' => [
        'title' => 'Kết nối dữ liệu',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'marketer',
                'label' => 'Marketing',
                'format' => 'text',
            ],
            [
                'key' => 'source',
                'label' => 'Tên nguồn kết nối / Url nguồn dữ liệu',
                'format' => 'text',
            ],
            [
                'key' => 'channel',
                'label' => 'Loại kết nối / Kênh quảng cáo',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'sale_priority',
                'label' => 'Ưu tiên sale',
                'format' => 'text',
            ],
            [
                'key' => 'allocation',
                'label' => 'Cấu hình chia số',
                'format' => 'text',
            ],
            [
                'key' => 'webhook_url',
                'label' => 'Url kết nối V2',
                'format' => 'text',
            ],
            [
                'key' => 'manual_import',
                'label' => 'Nhập TC',
                'format' => 'boolean',
            ],
            [
                'key' => 'approved',
                'label' => 'Duyệt',
                'format' => 'boolean',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
            [
                'key' => 'actions',
                'label' => 'Thêm',
                'format' => 'text',
            ],
        ],
        'source' => 'marketing_sources',
    ],
    '2.6.1' => [
        'title' => 'Import contact',
        'columns' => [
            [
                'key' => 'filename',
                'label' => 'Tên file',
                'format' => 'text',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái',
                'format' => 'status',
            ],
            [
                'key' => 'total_rows',
                'label' => 'Tổng dòng',
                'format' => 'number',
            ],
            [
                'key' => 'valid_rows',
                'label' => 'Hợp lệ',
                'format' => 'number',
            ],
            [
                'key' => 'invalid_rows',
                'label' => 'Không hợp lệ',
                'format' => 'number',
            ],
            [
                'key' => 'created_at',
                'label' => 'Ngày import',
                'format' => 'datetime',
            ],
        ],
        'source' => 'lead_imports',
        'kind' => 'import',
        'template_alias' => '1.10',
    ],
    '2.6.2' => [
        'title' => 'Nhập data thủ công',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'customer_name',
                'label' => 'Họ tên',
                'format' => 'text',
            ],
            [
                'key' => 'customer_phone',
                'label' => 'Số điện thoại',
                'format' => 'text',
            ],
            [
                'key' => 'message',
                'label' => 'Tin nhắn',
                'format' => 'text',
            ],
            [
                'key' => 'created_at',
                'label' => 'Ngày tạo',
                'format' => 'datetime',
            ],
        ],
        'source' => 'lead_ingestions',
        'kind' => 'form',
        'upsell' => true,
    ],
    '2.6.3' => [
        'title' => 'Kết nối các đơn vị đối tác',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'marketer',
                'label' => 'Marketing',
                'format' => 'text',
            ],
            [
                'key' => 'source',
                'label' => 'Tên nguồn kết nối',
                'format' => 'text',
            ],
            [
                'key' => 'url',
                'label' => 'Đường link',
                'format' => 'text',
            ],
            [
                'key' => 'channel',
                'label' => 'Loại kết nối / Kênh quảng cáo',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'sale_priority',
                'label' => 'Ưu tiên sale',
                'format' => 'text',
            ],
            [
                'key' => 'token',
                'label' => 'Token kết nối',
                'format' => 'text',
            ],
            [
                'key' => 'webhook_url',
                'label' => 'Url kết nối',
                'format' => 'text',
            ],
            [
                'key' => 'manual_import',
                'label' => 'Nhập TC',
                'format' => 'boolean',
            ],
            [
                'key' => 'approved',
                'label' => 'Duyệt',
                'format' => 'boolean',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'integrations',
        'editable' => true,
    ],
    '2.6.4' => [
        'title' => 'Kho số seeding (tối đa 1000)',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'phone',
                'label' => 'Số seeding',
                'format' => 'text',
            ],
            [
                'key' => 'creator',
                'label' => 'Người tạo',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '3.2' => [
        'title' => 'Quản lý chiến dịch chăm sóc',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'company',
                'label' => 'Đơn vị',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên chiến dịch',
                'format' => 'text',
            ],
            [
                'key' => 'customer_condition',
                'label' => 'Điều kiện khách hàng',
                'format' => 'text',
            ],
            [
                'key' => 'repeat_days',
                'label' => 'Số ngày lặp lại',
                'format' => 'number',
            ],
            [
                'key' => 'starts_at',
                'label' => 'Ngày bắt đầu',
                'format' => 'date',
            ],
            [
                'key' => 'ends_at',
                'label' => 'Ngày kết thúc',
                'format' => 'date',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái',
                'format' => 'status',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '3.3.1' => [
        'title' => 'Thống kê khách hàng đa chiều',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'dimension',
                'label' => 'Chỉ số',
                'format' => 'text',
            ],
            [
                'key' => 'quantity',
                'label' => 'Số lượng',
                'format' => 'number',
            ],
            [
                'key' => 'ratio',
                'label' => 'Tỉ trọng',
                'format' => 'percent',
            ],
        ],
        'source' => 'customer_multidimensional',
        'kind' => 'report',
    ],
    '3.3.2' => [
        'title' => 'Thống kê khách hàng chi trả',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'customer_type',
                'label' => 'Loại khách',
                'format' => 'text',
            ],
            [
                'key' => 'delivery_status',
                'label' => 'Trạng thái giao hàng',
                'format' => 'text',
            ],
            [
                'key' => 'customer_count',
                'label' => 'Số lượng khách',
                'format' => 'number',
            ],
            [
                'key' => 'ratio',
                'label' => 'Phần trăm',
                'format' => 'percent',
            ],
            [
                'key' => 'description',
                'label' => 'Mô tả',
                'format' => 'text',
            ],
        ],
        'source' => 'customer_spending',
        'kind' => 'report',
    ],
    '4.2' => [
        'title' => 'Hồ sơ khách hàng',
        'columns' => [
            [
                'key' => 'order_code',
                'label' => 'Mã đơn',
                'format' => 'text',
            ],
            [
                'key' => 'source',
                'label' => 'Nguồn dữ liệu / Ngày data về',
                'format' => 'text',
            ],
            [
                'key' => 'customer',
                'label' => 'Họ tên / Số điện thoại',
                'format' => 'text',
            ],
            [
                'key' => 'address',
                'label' => 'Địa chỉ',
                'format' => 'text',
            ],
            [
                'key' => 'message',
                'label' => 'Tin nhắn',
                'format' => 'text',
            ],
            [
                'key' => 'sale',
                'label' => 'Sale / Ngày nhận data',
                'format' => 'text',
            ],
            [
                'key' => 'operation',
                'label' => 'Tác nghiệp / Ngày chốt đơn',
                'format' => 'text',
            ],
            [
                'key' => 'result',
                'label' => 'Kết quả / Ngày sale tác nghiệp',
                'format' => 'text',
            ],
            [
                'key' => 'products',
                'label' => 'Sản phẩm - Số lượng - Đơn giá',
                'format' => 'text',
            ],
            [
                'key' => 'money',
                'label' => 'Thành tiền / CK/VAT / Phí VC/Tổng tiền',
                'format' => 'text',
            ],
            [
                'key' => 'deposit',
                'label' => 'Khách đặt cọc',
                'format' => 'currency',
            ],
            [
                'key' => 'shipping',
                'label' => 'Kho / PTGH / Mã giao vận',
                'format' => 'text',
            ],
            [
                'key' => 'delivery',
                'label' => 'Trạng thái giao hàng / Ngày muốn nhận hàng',
                'format' => 'text',
            ],
            [
                'key' => 'internal_note',
                'label' => 'ĐSNB',
                'format' => 'text',
            ],
            [
                'key' => 'actions',
                'label' => 'Thao tác',
                'format' => 'text',
            ],
        ],
        'source' => 'customer_orders',
        'kind' => 'customer_profile',
        'upsell' => true,
    ],
    '4.3' => [
        'title' => 'Bảng xếp hạng Sales',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'sale',
                'label' => 'SALE',
                'format' => 'text',
            ],
            [
                'key' => 'new_customers',
                'label' => 'KHÁCH HÀNG MỚI',
                'format' => 'text',
            ],
            [
                'key' => 'old_customers',
                'label' => 'KHÁCH HÀNG CŨ',
                'format' => 'text',
            ],
            [
                'key' => 'total',
                'label' => 'TỔNG CHUNG',
                'format' => 'text',
            ],
        ],
        'source' => 'sales_ranking',
        'kind' => 'ranking',
    ],
    '4.6.1' => [
        'title' => 'Báo cáo tỉ lệ chốt đơn theo tác nghiệp',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'sale',
                'label' => 'SALE',
                'format' => 'text',
            ],
            [
                'key' => 'total_contacts',
                'label' => 'Tổng contact',
                'format' => 'number',
            ],
            [
                'key' => 'total_closed',
                'label' => 'Tổng chốt đơn',
                'format' => 'number',
            ],
            [
                'key' => 'total_rate',
                'label' => 'Tổng tỷ lệ',
                'format' => 'percent',
            ],
            [
                'key' => 'revenue',
                'label' => 'Tổng doanh số',
                'format' => 'currency',
            ],
            [
                'key' => 'call_1',
                'label' => 'Gọi lần 1',
                'format' => 'text',
            ],
            [
                'key' => 'call_2',
                'label' => 'Gọi lần 2',
                'format' => 'text',
            ],
            [
                'key' => 'call_3',
                'label' => 'Gọi lần 3',
                'format' => 'text',
            ],
            [
                'key' => 'call_4',
                'label' => 'Gọi lần 4',
                'format' => 'text',
            ],
            [
                'key' => 'call_5',
                'label' => 'Gọi lần 5',
                'format' => 'text',
            ],
            [
                'key' => 'call_6',
                'label' => 'Gọi lần 6',
                'format' => 'text',
            ],
            [
                'key' => 'care_1',
                'label' => 'Chăm sóc lần 1',
                'format' => 'text',
            ],
            [
                'key' => 'care_2',
                'label' => 'Chăm sóc lần 2',
                'format' => 'text',
            ],
            [
                'key' => 'care_3',
                'label' => 'Chăm sóc lần 3',
                'format' => 'text',
            ],
            [
                'key' => 'skipped',
                'label' => 'Bỏ qua',
                'format' => 'text',
            ],
        ],
        'source' => 'sale_operation_rate',
        'kind' => 'report',
    ],
    '4.6.2' => [
        'title' => 'Báo cáo công việc sale',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'sale',
                'label' => 'SALE',
                'format' => 'text',
            ],
            [
                'key' => 'total_contacts',
                'label' => 'Tổng contact',
                'format' => 'number',
            ],
            [
                'key' => 'untouched',
                'label' => 'Tổng contact chưa tác nghiệp',
                'format' => 'number',
            ],
            [
                'key' => 'call_1',
                'label' => 'Gọi lần 1',
                'format' => 'text',
            ],
            [
                'key' => 'call_2',
                'label' => 'Gọi lần 2',
                'format' => 'text',
            ],
            [
                'key' => 'call_3',
                'label' => 'Gọi lần 3',
                'format' => 'text',
            ],
            [
                'key' => 'call_4',
                'label' => 'Gọi lần 4',
                'format' => 'text',
            ],
            [
                'key' => 'call_5',
                'label' => 'Gọi lần 5',
                'format' => 'text',
            ],
            [
                'key' => 'call_6',
                'label' => 'Gọi lần 6',
                'format' => 'text',
            ],
            [
                'key' => 'care_1',
                'label' => 'Chăm sóc lần 1',
                'format' => 'text',
            ],
            [
                'key' => 'care_2',
                'label' => 'Chăm sóc lần 2',
                'format' => 'text',
            ],
            [
                'key' => 'care_3',
                'label' => 'Chăm sóc lần 3',
                'format' => 'text',
            ],
            [
                'key' => 'skipped',
                'label' => 'Bỏ qua',
                'format' => 'text',
            ],
        ],
        'source' => 'sale_work',
        'kind' => 'report',
    ],
    '4.6.3' => [
        'title' => 'Báo cáo nhóm sale',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'team',
                'label' => 'Nhóm sale',
                'format' => 'text',
            ],
            [
                'key' => 'total_contacts',
                'label' => 'Tổng contact',
                'format' => 'number',
            ],
            [
                'key' => 'closed',
                'label' => 'Chốt đơn',
                'format' => 'number',
            ],
            [
                'key' => 'rate',
                'label' => 'Tỷ lệ chốt',
                'format' => 'percent',
            ],
            [
                'key' => 'revenue',
                'label' => 'Doanh số',
                'format' => 'currency',
            ],
            [
                'key' => 'delivered',
                'label' => 'Đã giao',
                'format' => 'number',
            ],
            [
                'key' => 'delivered_revenue',
                'label' => 'Doanh số đã giao',
                'format' => 'currency',
            ],
        ],
        'source' => 'sale_team',
        'kind' => 'report',
    ],
    '4.6.4' => [
        'title' => 'Báo cáo data sale',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'sale',
                'label' => 'Sale',
                'format' => 'text',
            ],
            [
                'key' => 'new_contacts',
                'label' => 'Contact mới',
                'format' => 'number',
            ],
            [
                'key' => 'old_contacts',
                'label' => 'Contact cũ',
                'format' => 'number',
            ],
            [
                'key' => 'operated',
                'label' => 'Đã tác nghiệp',
                'format' => 'number',
            ],
            [
                'key' => 'untouched',
                'label' => 'Chưa tác nghiệp',
                'format' => 'number',
            ],
            [
                'key' => 'closed',
                'label' => 'Chốt đơn',
                'format' => 'number',
            ],
            [
                'key' => 'rate',
                'label' => 'Tỷ lệ',
                'format' => 'percent',
            ],
            [
                'key' => 'revenue',
                'label' => 'Doanh số',
                'format' => 'currency',
            ],
        ],
        'source' => 'sale_data',
        'kind' => 'report',
    ],
    '4.6.5' => [
        'title' => 'Báo cáo tối ưu Sale',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'sale',
                'label' => 'Sale',
                'format' => 'text',
            ],
            [
                'key' => 'contacts',
                'label' => 'Contact nhận',
                'format' => 'number',
            ],
            [
                'key' => 'operation_rate',
                'label' => 'Tỷ lệ tác nghiệp',
                'format' => 'percent',
            ],
            [
                'key' => 'closing_rate',
                'label' => 'Tỷ lệ chốt',
                'format' => 'percent',
            ],
            [
                'key' => 'revenue',
                'label' => 'Doanh số',
                'format' => 'currency',
            ],
            [
                'key' => 'score',
                'label' => 'Điểm tối ưu',
                'format' => 'number',
            ],
        ],
        'source' => 'sale_optimization',
        'kind' => 'report',
    ],
    '5.1' => [
        'title' => 'Tác nghiệp vận đơn',
        'columns' => [
            [
                'key' => 'select',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'order_info',
                'label' => 'Sale / Ngày data về / Mã đơn',
                'format' => 'text',
            ],
            [
                'key' => 'shipping',
                'label' => 'Kho / PTGH / Mã giao vận',
                'format' => 'text',
            ],
            [
                'key' => 'care',
                'label' => 'Ngày cập nhật care đơn / Care đơn / Ghi chú kế toán',
                'format' => 'text',
            ],
            [
                'key' => 'delivery',
                'label' => 'Ngày cập nhật / Trạng thái giao hàng / Ngày đăng đơn',
                'format' => 'text',
            ],
            [
                'key' => 'customer',
                'label' => 'Họ tên / Số điện thoại / Ngày muốn nhận hàng',
                'format' => 'text',
            ],
            [
                'key' => 'address',
                'label' => 'Địa chỉ / Ghi chú giao hàng / Hóa đơn điện tử',
                'format' => 'text',
            ],
            [
                'key' => 'products',
                'label' => 'Sản phẩm - Số lượng - Đơn giá',
                'format' => 'text',
            ],
            [
                'key' => 'money',
                'label' => 'Thành tiền / CK / VAT SP / Phí VC / Tổng tiền',
                'format' => 'text',
            ],
            [
                'key' => 'deposit',
                'label' => 'Đặt cọc',
                'format' => 'currency',
            ],
            [
                'key' => 'collect',
                'label' => 'Tiền thu của khách',
                'format' => 'currency',
            ],
            [
                'key' => 'carrier_fee',
                'label' => 'Giá dịch vụ VC',
                'format' => 'currency',
            ],
            [
                'key' => 'shipping_support',
                'label' => 'Phí VC hỗ trợ khách',
                'format' => 'currency',
            ],
            [
                'key' => 'internal_note',
                'label' => 'ĐSNB',
                'format' => 'text',
            ],
            [
                'key' => 'actions',
                'label' => 'Thao tác',
                'format' => 'text',
            ],
        ],
        'source' => 'warehouse_orders',
        'kind' => 'warehouse_operations',
        'upsell' => true,
    ],
    '5.2.1' => [
        'title' => 'Danh sách kho',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên kho',
                'format' => 'text',
            ],
            [
                'key' => 'phone',
                'label' => 'Số điện thoại',
                'format' => 'text',
            ],
            [
                'key' => 'province',
                'label' => 'Tỉnh/TP',
                'format' => 'text',
            ],
            [
                'key' => 'district',
                'label' => 'Quận/Huyện',
                'format' => 'text',
            ],
            [
                'key' => 'ward',
                'label' => 'Phường/Xã',
                'format' => 'text',
            ],
            [
                'key' => 'address',
                'label' => 'Địa chỉ',
                'format' => 'text',
            ],
            [
                'key' => 'manager',
                'label' => 'Quản kho',
                'format' => 'text',
            ],
            [
                'key' => 'vtp_code',
                'label' => 'Mã VTP',
                'format' => 'text',
            ],
            [
                'key' => 'ghn_code',
                'label' => 'Mã GHN',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'warehouses',
        'create_url' => '/admin/warehouses/create',
        'dialogs' => ['5.2.1-create-dialog'],
    ],
    '5.2.2' => [
        'title' => 'Danh sách sản phẩm kho',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'warehouse',
                'label' => 'Kho',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'uom',
                'label' => 'Đơn vị tính',
                'format' => 'text',
            ],
            [
                'key' => 'batch_code',
                'label' => 'Mã lô',
                'format' => 'text',
            ],
            [
                'key' => 'expiry_date',
                'label' => 'Ngày hết hạn',
                'format' => 'date',
            ],
            [
                'key' => 'location',
                'label' => 'Vị trí',
                'format' => 'text',
            ],
            [
                'key' => 'stock',
                'label' => 'Tồn kho',
                'format' => 'number',
            ],
            [
                'key' => 'pending',
                'label' => 'Chờ xuất bán hàng',
                'format' => 'number',
            ],
            [
                'key' => 'low_stock',
                'label' => 'SL sắp hết hàng',
                'format' => 'number',
            ],
            [
                'key' => 'discontinued',
                'label' => 'Ngừng KD',
                'format' => 'boolean',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'inventory',
    ],
    '5.3.1' => [
        'title' => 'Phiếu nhập / xuất kho',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'sku',
                'label' => 'Mã sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'uom',
                'label' => 'Đv tính',
                'format' => 'text',
            ],
            [
                'key' => 'document_quantity',
                'label' => 'SL chứng từ',
                'format' => 'number',
            ],
            [
                'key' => 'quantity',
                'label' => 'Số lượng',
                'format' => 'number',
            ],
            [
                'key' => 'unit_cost',
                'label' => 'Giá nhập',
                'format' => 'currency',
            ],
            [
                'key' => 'total',
                'label' => 'Thành tiền',
                'format' => 'currency',
            ],
            [
                'key' => 'batch_code',
                'label' => 'Lô',
                'format' => 'text',
            ],
            [
                'key' => 'expiry_date',
                'label' => 'Ngày hết hạn',
                'format' => 'date',
            ],
            [
                'key' => 'location',
                'label' => 'Mã vị trí',
                'format' => 'text',
            ],
            [
                'key' => 'note',
                'label' => 'Ghi chú',
                'format' => 'text',
            ],
        ],
        'source' => 'generic',
        'kind' => 'warehouse_voucher',
        'editable' => true,
    ],
    '5.3.2' => [
        'title' => 'Danh sách phiếu xuất/nhập kho',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'warehouse',
                'label' => 'Kho',
                'format' => 'text',
            ],
            [
                'key' => 'type',
                'label' => 'Loại phiếu',
                'format' => 'text',
            ],
            [
                'key' => 'voucher_code',
                'label' => 'Mã phiếu',
                'format' => 'text',
            ],
            [
                'key' => 'performed_at',
                'label' => 'Ngày thực hiện',
                'format' => 'datetime',
            ],
            [
                'key' => 'total_quantity',
                'label' => 'Tổng số lượng',
                'format' => 'number',
            ],
            [
                'key' => 'total_value',
                'label' => 'Tổng giá trị',
                'format' => 'currency',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái',
                'format' => 'status',
            ],
            [
                'key' => 'note',
                'label' => 'Ghi chú',
                'format' => 'text',
            ],
            [
                'key' => 'internal_voucher',
                'label' => 'Phiếu XNNB',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'warehouse_vouchers',
        'editable' => true,
    ],
    '5.3.3' => [
        'title' => 'Lịch sử nhập / xuất kho (Thẻ kho)',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'warehouse',
                'label' => 'Kho',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'type',
                'label' => 'Nghiệp vụ',
                'format' => 'text',
            ],
            [
                'key' => 'quantity',
                'label' => 'Số lượng nhập/xuất',
                'format' => 'number',
            ],
            [
                'key' => 'pending',
                'label' => 'Số lượng chờ xuất',
                'format' => 'number',
            ],
            [
                'key' => 'reference',
                'label' => 'Mã đơn/Mã phiếu',
                'format' => 'text',
            ],
            [
                'key' => 'note',
                'label' => 'Ghi chú',
                'format' => 'text',
            ],
            [
                'key' => 'created_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'movements',
    ],
    '5.4' => [
        'title' => 'Danh sách biên bản',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'manager',
                'label' => 'Tên quản lý',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên biên bản',
                'format' => 'text',
            ],
            [
                'key' => 'document_date',
                'label' => 'Ngày biên bản',
                'format' => 'date',
            ],
            [
                'key' => 'carrier',
                'label' => 'Đơn vị giao hàng',
                'format' => 'text',
            ],
            [
                'key' => 'order_count',
                'label' => 'Số đơn',
                'format' => 'number',
            ],
            [
                'key' => 'product_count',
                'label' => 'Số sản phẩm',
                'format' => 'number',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái',
                'format' => 'status',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '5.5.1' => [
        'title' => 'Bảng tổng hợp sản phẩm nhập, xuất theo ngày',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'warehouse',
                'label' => 'Kho',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'batch_code',
                'label' => 'Mã lô',
                'format' => 'text',
            ],
            [
                'key' => 'opening',
                'label' => 'Đầu kỳ',
                'format' => 'number',
            ],
            [
                'key' => 'intake',
                'label' => 'Nhập kho',
                'format' => 'number',
            ],
            [
                'key' => 'export',
                'label' => 'Xuất kho',
                'format' => 'number',
            ],
            [
                'key' => 'pending',
                'label' => 'Chờ xuất',
                'format' => 'number',
            ],
            [
                'key' => 'closing',
                'label' => 'Cuối kỳ',
                'format' => 'number',
            ],
            [
                'key' => 'available',
                'label' => 'Tồn chưa lên đơn',
                'format' => 'number',
            ],
        ],
        'source' => 'inventory_daily',
        'kind' => 'report',
    ],
    '5.5.2' => [
        'title' => 'Bảng tổng hợp chờ xuất theo ngày',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'warehouse',
                'label' => 'Kho',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'batch_code',
                'label' => 'Mã lô',
                'format' => 'text',
            ],
            [
                'key' => 'opening',
                'label' => 'Đầu kỳ',
                'format' => 'number',
            ],
            [
                'key' => 'pending',
                'label' => 'Chờ xuất',
                'format' => 'number',
            ],
            [
                'key' => 'sold_export',
                'label' => 'Xuất bán hàng',
                'format' => 'number',
            ],
            [
                'key' => 'closing',
                'label' => 'Cuối kỳ',
                'format' => 'number',
            ],
        ],
        'source' => 'inventory_pending',
        'kind' => 'report',
    ],
    '5.5.4' => [
        'title' => 'Báo cáo tổng hợp phát sinh kho',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'warehouse',
                'label' => 'Kho',
                'format' => 'text',
            ],
            [
                'key' => 'product',
                'label' => 'Sản phẩm',
                'format' => 'text',
            ],
            [
                'key' => 'total_quantity',
                'label' => 'Tổng số lượng',
                'format' => 'number',
            ],
            [
                'key' => 'total_pending',
                'label' => 'Tổng số lượng chờ xuất',
                'format' => 'number',
            ],
            [
                'key' => 'quantity',
                'label' => 'Số lượng',
                'format' => 'number',
            ],
            [
                'key' => 'pending',
                'label' => 'Số lượng chờ xuất',
                'format' => 'number',
            ],
        ],
        'source' => 'inventory_summary',
        'kind' => 'report',
    ],
    '5.5.5' => [
        'title' => 'Báo cáo care đơn',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'care_user',
                'label' => 'TK vận đơn',
                'format' => 'text',
            ],
            [
                'key' => 'received',
                'label' => 'Đã nhận',
                'format' => 'number',
            ],
            [
                'key' => 'care_actions',
                'label' => 'TN care đơn',
                'format' => 'number',
            ],
            [
                'key' => 'caring',
                'label' => 'Đang care',
                'format' => 'number',
            ],
            [
                'key' => 'uncared',
                'label' => 'Chưa care',
                'format' => 'number',
            ],
            [
                'key' => 'success',
                'label' => 'Care thành công',
                'format' => 'number',
            ],
            [
                'key' => 'returned',
                'label' => 'Hoàn đơn',
                'format' => 'number',
            ],
            [
                'key' => 'success_rate',
                'label' => 'Tỉ lệ thành công',
                'format' => 'percent',
            ],
            [
                'key' => 'auto_success',
                'label' => 'Care thành công (Auto)',
                'format' => 'number',
            ],
            [
                'key' => 'auto_return',
                'label' => 'Hoàn đơn (Auto)',
                'format' => 'number',
            ],
        ],
        'source' => 'care_report',
        'kind' => 'report',
    ],
    '5.5.6' => [
        'title' => 'Báo cáo sửa số điện thoại giao hàng',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'sale',
                'label' => 'Sales',
                'format' => 'text',
            ],
            [
                'key' => 'team',
                'label' => 'Tên nhóm',
                'format' => 'text',
            ],
            [
                'key' => 'quantity',
                'label' => 'Số lượng',
                'format' => 'number',
            ],
            [
                'key' => 'export',
                'label' => 'Xuất Excel',
                'format' => 'text',
            ],
        ],
        'source' => 'phone_corrections',
        'kind' => 'report',
    ],
    '5.5.7' => [
        'title' => 'Tổng hợp trạng thái giao hàng theo vận đơn',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'care_user',
                'label' => 'TK vận đơn',
                'format' => 'text',
            ],
            [
                'key' => 'pending',
                'label' => 'Chờ vận đơn',
                'format' => 'text',
            ],
            [
                'key' => 'shipping',
                'label' => 'Đang giao',
                'format' => 'text',
            ],
            [
                'key' => 'delivered',
                'label' => 'Đã giao',
                'format' => 'text',
            ],
            [
                'key' => 'returned',
                'label' => 'Hoàn đơn',
                'format' => 'text',
            ],
            [
                'key' => 'cancelled',
                'label' => 'Hủy',
                'format' => 'text',
            ],
            [
                'key' => 'total',
                'label' => 'Tổng',
                'format' => 'text',
            ],
        ],
        'source' => 'delivery_by_care',
        'kind' => 'report',
    ],
    '5.5.8' => [
        'title' => 'Báo cáo tác nghiệp care đơn',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'order_code',
                'label' => 'Mã đơn',
                'format' => 'text',
            ],
            [
                'key' => 'care_user',
                'label' => 'Vận đơn',
                'format' => 'text',
            ],
            [
                'key' => 'care_status',
                'label' => 'Trạng thái care',
                'format' => 'text',
            ],
            [
                'key' => 'note',
                'label' => 'Ghi chú',
                'format' => 'text',
            ],
            [
                'key' => 'operated_at',
                'label' => 'Ngày tác nghiệp',
                'format' => 'datetime',
            ],
            [
                'key' => 'previous_status',
                'label' => 'Trạng thái care cũ',
                'format' => 'text',
            ],
            [
                'key' => 'export',
                'label' => 'Xuất Excel',
                'format' => 'text',
            ],
        ],
        'source' => 'care_operations',
        'kind' => 'report',
    ],
    '5.8.2' => [
        'title' => 'Phân bổ data care đơn',
        'columns' => [
            [
                'key' => 'care_user',
                'label' => 'User care đơn',
                'format' => 'text',
            ],
            [
                'key' => 'account',
                'label' => 'Tài khoản',
                'format' => 'text',
            ],
            [
                'key' => 'contacts',
                'label' => 'Số contact',
                'format' => 'number',
            ],
            [
                'key' => 'receive_data',
                'label' => 'Nhận data',
                'format' => 'boolean',
            ],
            [
                'key' => 'active',
                'label' => 'Đang sử dụng',
                'format' => 'boolean',
            ],
        ],
        'source' => 'care_allocation',
        'editable' => true,
    ],
    '6.2.1' => [
        'title' => 'Quản lý chi phí đơn vị',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên',
                'format' => 'text',
            ],
            [
                'key' => 'year',
                'label' => 'Năm',
                'format' => 'number',
            ],
            [
                'key' => 'month',
                'label' => 'Tháng',
                'format' => 'number',
            ],
            [
                'key' => 'group',
                'label' => 'Danh mục nhóm chi phí',
                'format' => 'text',
            ],
            [
                'key' => 'category',
                'label' => 'Danh mục chi phí',
                'format' => 'text',
            ],
            [
                'key' => 'unit',
                'label' => 'Đơn vị tính',
                'format' => 'text',
            ],
            [
                'key' => 'unit_price',
                'label' => 'Đơn giá',
                'format' => 'currency',
            ],
            [
                'key' => 'quantity',
                'label' => 'Số lượng',
                'format' => 'number',
            ],
            [
                'key' => 'total',
                'label' => 'Thành tiền',
                'format' => 'currency',
            ],
            [
                'key' => 'invoice',
                'label' => 'Hóa đơn',
                'format' => 'text',
            ],
            [
                'key' => 'note',
                'label' => 'Ghi chú',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '6.2.2' => [
        'title' => 'Danh mục chi phí',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'group',
                'label' => 'Nhóm chi phí',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '6.2.3' => [
        'title' => 'Danh mục nhóm chi phí',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '6.2.4' => [
        'title' => 'Danh mục đơn vị tính',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'name',
                'label' => 'Tên',
                'format' => 'text',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '6.3.5' => [
        'title' => 'Tổng kết kế hoạch tháng',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'account',
                'label' => 'Tài khoản',
                'format' => 'text',
            ],
            [
                'key' => 'role',
                'label' => 'Chức vụ',
                'format' => 'text',
            ],
            [
                'key' => 'kpi',
                'label' => 'Tên KPI',
                'format' => 'text',
            ],
            [
                'key' => 'budget',
                'label' => 'Ngân sách/tháng',
                'format' => 'currency',
            ],
            [
                'key' => 'clicks',
                'label' => 'Số click/tháng',
                'format' => 'number',
            ],
            [
                'key' => 'contacts',
                'label' => 'Số contact/tháng',
                'format' => 'number',
            ],
            [
                'key' => 'revenue_target',
                'label' => 'Doanh số/tháng',
                'format' => 'currency',
            ],
            [
                'key' => 'actual_revenue',
                'label' => 'Doanh số thực tế/tháng',
                'format' => 'currency',
            ],
            [
                'key' => 'working_days',
                'label' => 'Số ngày làm việc/tháng',
                'format' => 'number',
            ],
            [
                'key' => 'actual_days',
                'label' => 'Số ngày làm việc thực tế',
                'format' => 'number',
            ],
            [
                'key' => 'base_salary',
                'label' => 'Lương cứng',
                'format' => 'currency',
            ],
            [
                'key' => 'bonus',
                'label' => 'Tiền thưởng (% Doanh số)',
                'format' => 'currency',
            ],
            [
                'key' => 'income',
                'label' => 'Tổng thu nhập',
                'format' => 'currency',
            ],
            [
                'key' => 'locked',
                'label' => 'Chốt dữ liệu',
                'format' => 'boolean',
            ],
            [
                'key' => 'updated_at',
                'label' => 'Cập nhật thực tế',
                'format' => 'datetime',
            ],
        ],
        'source' => 'monthly_plan',
        'editable' => true,
        'kind' => 'report',
    ],
    '6.4' => [
        'title' => 'Danh sách xử lý xuất hóa đơn điện tử',
        'columns' => [
            [
                'key' => 'id',
                'label' => '#',
                'format' => 'text',
            ],
            [
                'key' => 'code_type',
                'label' => 'Loại mã đơn',
                'format' => 'text',
            ],
            [
                'key' => 'order_code',
                'label' => 'Mã đơn',
                'format' => 'text',
            ],
            [
                'key' => 'process_type',
                'label' => 'Loại xử lý',
                'format' => 'text',
            ],
            [
                'key' => 'processed_at',
                'label' => 'Ngày xử lý',
                'format' => 'datetime',
            ],
            [
                'key' => 'status',
                'label' => 'Trạng thái xử lý',
                'format' => 'status',
            ],
            [
                'key' => 'note',
                'label' => 'Ghi chú',
                'format' => 'text',
            ],
            [
                'key' => 'duration_ms',
                'label' => 'Thời gian xử lý(ms)',
                'format' => 'number',
            ],
            [
                'key' => 'attempts',
                'label' => 'Số lần xử lý',
                'format' => 'number',
            ],
            [
                'key' => 'completed',
                'label' => 'Trạng thái hoàn thành',
                'format' => 'boolean',
            ],
            [
                'key' => 'batch_id',
                'label' => 'BatchId',
                'format' => 'text',
            ],
            [
                'key' => 'created_at',
                'label' => 'Ngày tạo',
                'format' => 'datetime',
            ],
        ],
        'source' => 'generic',
        'editable' => true,
    ],
    '8.5.4' => [
        'title' => 'Biểu đồ xu hướng',
        'columns' => [
            [
                'key' => 'period',
                'label' => 'Thời gian',
                'format' => 'text',
            ],
            [
                'key' => 'value',
                'label' => 'Giá trị',
                'format' => 'currency',
            ],
            [
                'key' => 'comparison',
                'label' => 'So sánh',
                'format' => 'currency',
            ],
            [
                'key' => 'change',
                'label' => 'Tăng/giảm',
                'format' => 'percent',
            ],
        ],
        'source' => 'trend',
        'kind' => 'trend',
    ],
    '8.5.5' => [
        'title' => 'Bảng tổng hợp kết quả chia data trong ngày',
        'columns' => [
            [
                'key' => 'day',
                'label' => 'Day',
                'format' => 'text',
            ],
            [
                'key' => 'sale',
                'label' => 'Sale',
                'format' => 'text',
            ],
            [
                'key' => 'new_contacts',
                'label' => 'Contact mới',
                'format' => 'number',
            ],
            [
                'key' => 'duplicate_contacts',
                'label' => 'Contact trùng',
                'format' => 'number',
            ],
            [
                'key' => 'old_contacts',
                'label' => 'Contact cũ',
                'format' => 'number',
            ],
            [
                'key' => 'care',
                'label' => 'CSKH',
                'format' => 'number',
            ],
            [
                'key' => 'manual',
                'label' => 'Thủ công',
                'format' => 'number',
            ],
            [
                'key' => 'team',
                'label' => 'Team',
                'format' => 'text',
            ],
        ],
        'source' => 'allocation_summary',
        'kind' => 'report',
    ],
    '8.5.9' => [
        'title' => 'Power dashboard',
        'columns' => [
            [
                'key' => 'account',
                'label' => 'Tài khoản',
                'format' => 'text',
            ],
            [
                'key' => 'contacts',
                'label' => 'Số contact',
                'format' => 'number',
            ],
            [
                'key' => 'closed',
                'label' => 'Số đơn chốt',
                'format' => 'number',
            ],
            [
                'key' => 'rate',
                'label' => 'Tỷ lệ chốt',
                'format' => 'percent',
            ],
            [
                'key' => 'cost_per_contact',
                'label' => 'Giá contact',
                'format' => 'currency',
            ],
            [
                'key' => 'budget_ratio',
                'label' => 'Ngân sách/DS',
                'format' => 'percent',
            ],
            [
                'key' => 'revenue',
                'label' => 'Doanh số',
                'format' => 'currency',
            ],
        ],
        'source' => 'power_dashboard',
        'kind' => 'power_dashboard',
    ],
    '8.5.10' => [
        'title' => 'Thống kê mua lại',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'metric',
                'label' => 'Chỉ số',
                'format' => 'text',
            ],
            [
                'key' => 'purchase_1',
                'label' => 'Mua lần 1',
                'format' => 'number',
            ],
            [
                'key' => 'purchase_2',
                'label' => 'Mua lần 2',
                'format' => 'number',
            ],
            [
                'key' => 'purchase_3',
                'label' => 'Mua lần 3',
                'format' => 'number',
            ],
            [
                'key' => 'purchase_n',
                'label' => 'Mua lần n',
                'format' => 'number',
            ],
        ],
        'source' => 'repurchase',
        'kind' => 'report',
    ],
    '8.5.11' => [
        'title' => 'Thống kê mua lại theo số sản phẩm',
        'columns' => [
            [
                'key' => 'purchase_no',
                'label' => 'Lần mua',
                'format' => 'text',
            ],
            [
                'key' => 'product_1',
                'label' => 'Mua 1 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_2',
                'label' => 'Mua 2 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_3',
                'label' => 'Mua 3 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_4',
                'label' => 'Mua 4 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_5',
                'label' => 'Mua 5 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_6',
                'label' => 'Mua 6 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_7',
                'label' => 'Mua 7 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_8',
                'label' => 'Mua 8 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_9',
                'label' => 'Mua 9 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_10',
                'label' => 'Mua 10 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_11',
                'label' => 'Mua 11 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_12',
                'label' => 'Mua 12 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_13',
                'label' => 'Mua 13 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_14',
                'label' => 'Mua 14 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_15',
                'label' => 'Mua 15 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_16',
                'label' => 'Mua 16 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_17',
                'label' => 'Mua 17 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_18',
                'label' => 'Mua 18 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_19',
                'label' => 'Mua 19 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_20',
                'label' => 'Mua 20 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_21',
                'label' => 'Mua 21 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_22',
                'label' => 'Mua 22 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_23',
                'label' => 'Mua 23 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_24',
                'label' => 'Mua 24 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_25',
                'label' => 'Mua 25 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_26',
                'label' => 'Mua 26 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_27',
                'label' => 'Mua 27 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_28',
                'label' => 'Mua 28 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_29',
                'label' => 'Mua 29 SP',
                'format' => 'number',
            ],
            [
                'key' => 'product_30',
                'label' => 'Mua 30 SP',
                'format' => 'number',
            ],
        ],
        'source' => 'repurchase_products',
        'kind' => 'report',
    ],
    '8.5.15' => [
        'title' => 'Bảng tổng hợp chia data trong ngày v2',
        'columns' => [
            [
                'key' => 'day',
                'label' => 'Day',
                'format' => 'text',
            ],
            [
                'key' => 'user',
                'label' => 'User',
                'format' => 'text',
            ],
            [
                'key' => 'receive',
                'label' => 'Nhận số',
                'format' => 'boolean',
            ],
            [
                'key' => 'quota',
                'label' => 'Định mức',
                'format' => 'number',
            ],
            [
                'key' => 'wave',
                'label' => 'Wave',
                'format' => 'number',
            ],
            [
                'key' => 'new_contacts',
                'label' => 'Số mới',
                'format' => 'number',
            ],
            [
                'key' => 'duplicate_new',
                'label' => 'Số mới trùng',
                'format' => 'number',
            ],
            [
                'key' => 'old_contacts',
                'label' => 'Số khách cũ',
                'format' => 'number',
            ],
            [
                'key' => 'duplicate_old',
                'label' => 'Số khách cũ trùng',
                'format' => 'number',
            ],
            [
                'key' => 'care',
                'label' => 'Số CSKH',
                'format' => 'number',
            ],
        ],
        'source' => 'allocation_v2',
        'kind' => 'report',
    ],
    '8.5.16' => [
        'title' => 'Báo cáo care đơn',
        'columns' => [
            [
                'key' => 'index',
                'label' => 'STT',
                'format' => 'text',
            ],
            [
                'key' => 'care_user',
                'label' => 'TK vận đơn',
                'format' => 'text',
            ],
            [
                'key' => 'received',
                'label' => 'Đã nhận',
                'format' => 'number',
            ],
            [
                'key' => 'care_actions',
                'label' => 'TN care đơn',
                'format' => 'number',
            ],
            [
                'key' => 'caring',
                'label' => 'Đang care',
                'format' => 'number',
            ],
            [
                'key' => 'uncared',
                'label' => 'Chưa care',
                'format' => 'number',
            ],
            [
                'key' => 'success',
                'label' => 'Care thành công',
                'format' => 'number',
            ],
            [
                'key' => 'returned',
                'label' => 'Hoàn đơn',
                'format' => 'number',
            ],
            [
                'key' => 'success_rate',
                'label' => 'Tỉ lệ thành công',
                'format' => 'percent',
            ],
            [
                'key' => 'auto_success',
                'label' => 'Care thành công (Auto)',
                'format' => 'number',
            ],
            [
                'key' => 'auto_return',
                'label' => 'Hoàn đơn (Auto)',
                'format' => 'number',
            ],
        ],
        'source' => 'care_report',
        'kind' => 'report',
        'template_alias' => '5.5.5',
    ],
    '8.5.17' => [
        'title' => 'Bảng tổng hợp chia số care đơn trong ngày',
        'columns' => [
            [
                'key' => 'day',
                'label' => 'Day',
                'format' => 'text',
            ],
            [
                'key' => 'user',
                'label' => 'User',
                'format' => 'text',
            ],
            [
                'key' => 'receive',
                'label' => 'Nhận số',
                'format' => 'boolean',
            ],
            [
                'key' => 'quota',
                'label' => 'Định mức',
                'format' => 'number',
            ],
            [
                'key' => 'wave',
                'label' => 'Wave',
                'format' => 'number',
            ],
            [
                'key' => 'new_contacts',
                'label' => 'Số mới',
                'format' => 'number',
            ],
        ],
        'source' => 'care_allocation_daily',
        'kind' => 'report',
    ],
];
