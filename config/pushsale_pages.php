<?php

return array (
  '1.1.2' => 
  array (
    'title' => 'Lịch sử đăng ký gói dịch vụ',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'unit_payment',
        'label' => 'Đơn vị / Mã thanh toán',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'contract_type',
        'label' => 'Loại hợp đồng',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'description',
        'label' => 'Mô tả',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'amount',
        'label' => 'Giá trị',
        'format' => 'currency',
        'align' => 'right',
      ),
      5 => 
      array (
        'key' => 'paid_at',
        'label' => 'Ngày thanh toán',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'duration_months',
        'label' => 'Thời gian sử dụng (tháng)',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'expires_at',
        'label' => 'Thời gian hết hạn',
        'format' => 'datetime',
      ),
      8 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'subscriptions',
    'editable' => true,
    'filters' => 
    array (
      0 => 'contract_type',
      1 => 'search',
      2 => 'date_range',
    ),
    'slug' => '1-1-2-lich-su-dang-ky-goi-dich-vu',
    'component' => 'Page_1_1_2',
    'resource_key' => '1.1.2',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'payment_code',
        'label' => 'Mã thanh toán',
        'type' => 'text',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'contract_type',
        'label' => 'Loại hợp đồng',
        'type' => 'select',
        'options' => 
        array (
          0 => 'Mới',
          1 => 'Gia hạn',
          2 => 'Nâng cấp',
        ),
      ),
      2 => 
      array (
        'key' => 'description',
        'label' => 'Mô tả',
        'type' => 'textarea',
      ),
      3 => 
      array (
        'key' => 'amount',
        'label' => 'Giá trị',
        'type' => 'currency',
        'required' => true,
      ),
      4 => 
      array (
        'key' => 'paid_at',
        'label' => 'Ngày thanh toán',
        'type' => 'datetime-local',
      ),
      5 => 
      array (
        'key' => 'duration_months',
        'label' => 'Thời gian sử dụng (tháng)',
        'type' => 'number',
      ),
      6 => 
      array (
        'key' => 'expires_at',
        'label' => 'Thời gian hết hạn',
        'type' => 'datetime-local',
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'unit_payment',
        'label' => 'Đơn vị / Mã thanh toán',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'contract_type',
        'label' => 'Loại hợp đồng',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'description',
        'label' => 'Mô tả',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'amount',
        'label' => 'Giá trị',
        'format' => 'currency',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'paid_at',
        'label' => 'Ngày thanh toán',
        'format' => 'datetime',
      ),
      7 => 
      array (
        'key' => 'duration_months',
        'label' => 'Thời gian sử dụng (tháng)',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'expires_at',
        'label' => 'Thời gian hết hạn',
        'format' => 'datetime',
      ),
      9 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
  ),
  '1.2.1' => 
  array (
    'title' => 'Danh sách nhân viên',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'select',
        'label' => '#',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'label' => 'Họ tên',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'role',
        'label' => 'Chức vụ',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'employee_code',
        'label' => 'Mã nhân viên',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'base_salary',
        'label' => 'Lương cứng',
        'format' => 'currency',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'phone',
        'label' => 'Số điện thoại',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'email',
        'label' => 'Email',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'leader',
        'label' => 'Trưởng nhóm',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'receive_data',
        'label' => 'Nhận dữ liệu',
        'format' => 'boolean',
      ),
      10 => 
      array (
        'key' => 'shift',
        'label' => 'Ca làm việc',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'active',
        'label' => 'Đang sử dụng',
        'format' => 'boolean',
      ),
      12 => 
      array (
        'key' => 'updated_at',
        'label' => 'Ngày cập nhật',
        'format' => 'datetime',
      ),
      13 => 
      array (
        'key' => 'actions',
        'label' => 'Thao tác',
        'format' => 'text',
      ),
    ),
    'source' => 'users',
    'create_url' => '/admin/users/create',
    'filters' => 
    array (
      0 => 'role',
      1 => 'leader',
      2 => 'receive_data',
      3 => 'active',
      4 => 'search',
    ),
    'slug' => '1-2-1-danh-sach-nhan-vien',
    'component' => 'Page_1_2_1',
  ),
  '1.2.2' => 
  array (
    'title' => 'Quản lý đội, nhóm',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'type',
        'label' => 'Loại nhóm',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'code',
        'label' => 'Mã',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'leader',
        'label' => 'Trưởng nhóm',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'member_count',
        'label' => 'Số thành viên',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'members',
        'label' => 'Thành viên',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'parent',
        'label' => 'Nhóm liên kết',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      9 => 
      array (
        'key' => 'actions',
        'label' => 'Thêm',
        'format' => 'text',
      ),
    ),
    'source' => 'teams',
    'create_url' => '/admin/teams/create',
    'filters' => 
    array (
      0 => 'type',
      1 => 'leader',
      2 => 'search',
    ),
    'slug' => '1-2-2-quan-ly-doi-nhom',
    'component' => 'Page_1_2_2',
  ),
  '1.2.3' => 
  array (
    'title' => 'Ca làm việc',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Ca làm việc',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'from_hour',
        'label' => 'Từ (h)',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'to_hour',
        'label' => 'Đến (h)',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'work_shifts',
    'editable' => true,
    'kind' => 'inline_settings',
    'slug' => '1-2-3-ca-lam-viec',
    'component' => 'Page_1_2_3',
    'resource_key' => '1.2.3',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Ca làm việc',
        'type' => 'text',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'from_hour',
        'label' => 'Từ (h)',
        'type' => 'time',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'to_hour',
        'label' => 'Đến (h)',
        'type' => 'time',
        'required' => true,
      ),
      3 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'type' => 'textarea',
      ),
      4 => 
      array (
        'key' => 'is_active',
        'label' => 'Đang sử dụng',
        'type' => 'checkbox',
        'default' => true,
      ),
    ),
    'grid_enabled' => false,
    'inline_form' => true,
    'inline_fields' => 
    array (
      0 => 
      array (
        'key' => 'shift_1_from',
        'label' => 'Ca 1 từ (h)',
        'type' => 'number',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'shift_1_to',
        'label' => 'Ca 1 đến (h)',
        'type' => 'number',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'shift_2_from',
        'label' => 'Ca 2 từ (h)',
        'type' => 'number',
        'required' => true,
      ),
      3 => 
      array (
        'key' => 'shift_2_to',
        'label' => 'Ca 2 đến (h)',
        'type' => 'number',
        'required' => true,
      ),
      4 => 
      array (
        'key' => 'shift_3_from',
        'label' => 'Ca 3 từ (h)',
        'type' => 'number',
        'required' => true,
      ),
      5 => 
      array (
        'key' => 'shift_3_to',
        'label' => 'Ca 3 đến (h)',
        'type' => 'number',
        'required' => true,
      ),
    ),
  ),
  '1.2.4' => 
  array (
    'title' => 'Danh sách cấu hình chia số',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'allocation_rule',
        'label' => 'Kiểu số - Người nhận - Cách chia',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'products',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'sales',
        'label' => 'Sales',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'care_users',
        'label' => 'CSKH',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'lead_distribution_rules',
    'editable' => true,
    'slug' => '1-2-4-danh-sach-cau-hinh-chia-so',
    'component' => 'Page_1_2_4',
    'resource_key' => '1.2.4',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Tên cấu hình',
        'type' => 'text',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'number_type',
        'label' => 'Kiểu số',
        'type' => 'select',
        'options' => 
        array (
          'new' => 'Số mới',
          'old' => 'Khách cũ',
          'care' => 'CSKH',
        ),
      ),
      2 => 
      array (
        'key' => 'recipient_type',
        'label' => 'Người nhận',
        'type' => 'select',
        'options' => 
        array (
          'sales' => 'Sales',
          'care' => 'CSKH',
          'both' => 'Sales + CSKH',
        ),
      ),
      3 => 
      array (
        'key' => 'allocation_method',
        'label' => 'Cách chia',
        'type' => 'select',
        'options' => 
        array (
          'round_robin' => 'Luân phiên',
          'quota' => 'Theo định mức',
          'manual' => 'Thủ công',
        ),
      ),
      4 => 
      array (
        'key' => 'product_ids',
        'label' => 'Sản phẩm',
        'type' => 'multiselect',
        'option_source' => 'products',
      ),
      5 => 
      array (
        'key' => 'sale_user_ids',
        'label' => 'Sales',
        'type' => 'multiselect',
        'option_source' => 'sales',
      ),
      6 => 
      array (
        'key' => 'care_user_ids',
        'label' => 'CSKH',
        'type' => 'multiselect',
        'option_source' => 'careUsers',
      ),
      7 => 
      array (
        'key' => 'is_active',
        'label' => 'Đang áp dụng',
        'type' => 'checkbox',
        'default' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'allocation_rule',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'products',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'sales',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'care_users',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      8 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '1.2.5' => 
  array (
    'title' => 'Cấu hình tài khoản xem báo cáo',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'account',
        'label' => 'Tài khoản',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'visible_teams',
        'label' => 'Nhóm được xem báo cáo',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'team_type',
        'label' => 'Kiểu nhóm',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      5 => 
      array (
        'key' => 'actions',
        'label' => 'Thêm',
        'format' => 'text',
      ),
    ),
    'source' => 'report_access_rules',
    'editable' => true,
    'slug' => '1-2-5-cau-hinh-tai-khoan-xem-bao-cao',
    'component' => 'Page_1_2_5',
    'resource_key' => '1.2.5',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'user_id',
        'label' => 'Tài khoản',
        'type' => 'select',
        'option_source' => 'users',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'team_ids',
        'label' => 'Nhóm được xem báo cáo',
        'type' => 'multiselect',
        'option_source' => 'teams',
      ),
      2 => 
      array (
        'key' => 'team_type',
        'label' => 'Kiểu nhóm',
        'type' => 'select',
        'options' => 
        array (
          'sale' => 'Sales',
          'marketing' => 'Marketing',
          'warehouse' => 'Kho',
          'all' => 'Tất cả',
        ),
      ),
      3 => 
      array (
        'key' => 'is_active',
        'label' => 'Đang áp dụng',
        'type' => 'checkbox',
        'default' => true,
      ),
    ),
  ),
  '1.2.6' => 
  array (
    'title' => 'Danh sách cấu hình chia số care đơn',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'care_user',
        'label' => 'User care đơn',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'quota',
        'label' => 'Định mức',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'receive_data',
        'label' => 'Nhận data',
        'format' => 'boolean',
      ),
      4 => 
      array (
        'key' => 'sales_teams',
        'label' => 'Nhóm Sales',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'actions',
        'label' => 'Thao tác',
        'format' => 'text',
      ),
    ),
    'source' => 'care_distribution_rules',
    'editable' => true,
    'slug' => '1-2-6-danh-sach-cau-hinh-chia-so-care-don',
    'component' => 'Page_1_2_6',
    'resource_key' => '1.2.6',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'care_user_id',
        'label' => 'User care đơn',
        'type' => 'select',
        'option_source' => 'careUsers',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'quota',
        'label' => 'Định mức',
        'type' => 'number',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'receive_data',
        'label' => 'Nhận data',
        'type' => 'checkbox',
        'default' => true,
      ),
      3 => 
      array (
        'key' => 'sale_team_ids',
        'label' => 'Nhóm Sales',
        'type' => 'multiselect',
        'option_source' => 'saleTeams',
      ),
      4 => 
      array (
        'key' => 'warehouse_team_id',
        'label' => 'Nhóm vận đơn',
        'type' => 'select',
        'option_source' => 'warehouseTeams',
      ),
    ),
  ),
  '1.3.1' => 
  array (
    'title' => 'Quản lý sản phẩm',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'category',
        'label' => 'Phân loại',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'product',
        'label' => 'Tên / mã sản phẩm',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'unit',
        'label' => 'Đ.vị tính',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'cost_price',
        'label' => 'Giá nhập',
        'format' => 'currency',
        'align' => 'right',
      ),
      5 => 
      array (
        'key' => 'unit_price',
        'label' => 'Đơn giá',
        'format' => 'currency',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'vat',
        'label' => 'VAT (%)',
        'format' => 'percent',
      ),
      7 => 
      array (
        'key' => 'vat_code',
        'label' => 'Mã VAT',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'price_after_vat',
        'label' => 'Đơn giá sau VAT (tham khảo)',
        'format' => 'currency',
        'align' => 'right',
      ),
      9 => 
      array (
        'key' => 'weight',
        'label' => 'KL(gram)',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'inactive',
        'label' => 'Ngừng KD',
        'format' => 'boolean',
      ),
      11 => 
      array (
        'key' => 'marketing',
        'label' => 'Marketing',
        'format' => 'boolean',
      ),
      12 => 
      array (
        'key' => 'sale',
        'label' => 'Sale',
        'format' => 'boolean',
      ),
      13 => 
      array (
        'key' => 'care',
        'label' => 'Chăm sóc KH',
        'format' => 'boolean',
      ),
      14 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      15 => 
      array (
        'key' => 'actions',
        'label' => 'Thao tác',
        'format' => 'text',
      ),
    ),
    'source' => 'products',
    'dialogs' => 
    array (
      0 => '1.3.1-dialog-create',
      1 => '1.3.1-dialog-ph#U00e2n lo#U1ea1i s#U1ea3n ph#U1ea9m',
      2 => '1.3.1-dialog-thu#U1ed9c t#U00ednh s#U1ea3n ph#U1ea9m',
      3 => '1.3.1-dialog-thu#U1ed9c t#U00ednh gi#U00e1 tr#U1ecb',
    ),
    'slug' => '1-3-1-quan-ly-san-pham',
    'component' => 'Page_1_3_1',
    'editable' => true,
    'resource_key' => '1.3.1:product',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Tên sản phẩm',
        'type' => 'text',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'sku',
        'label' => 'Mã sản phẩm',
        'type' => 'text',
      ),
      2 => 
      array (
        'key' => 'unit',
        'label' => 'Đơn vị tính',
        'type' => 'text',
      ),
      3 => 
      array (
        'key' => 'cost_price',
        'label' => 'Giá vốn',
        'type' => 'currency',
      ),
      4 => 
      array (
        'key' => 'unit_price',
        'label' => 'Đơn giá',
        'type' => 'currency',
        'required' => true,
      ),
      5 => 
      array (
        'key' => 'vat_percent',
        'label' => 'VAT (%)',
        'type' => 'number',
      ),
      6 => 
      array (
        'key' => 'vat_code',
        'label' => 'Mã VAT',
        'type' => 'text',
      ),
      7 => 
      array (
        'key' => 'weight_grams',
        'label' => 'Khối lượng (gram)',
        'type' => 'number',
      ),
      8 => 
      array (
        'key' => 'category_ids',
        'label' => 'Phân loại',
        'type' => 'multiselect',
        'option_source' => 'productCategories',
      ),
      9 => 
      array (
        'key' => 'attribute_value_ids',
        'label' => 'Thuộc tính',
        'type' => 'multiselect',
        'option_source' => 'productAttributeValues',
      ),
      10 => 
      array (
        'key' => 'available_marketing',
        'label' => 'Marketing',
        'type' => 'checkbox',
        'default' => true,
      ),
      11 => 
      array (
        'key' => 'available_sale',
        'label' => 'Sale',
        'type' => 'checkbox',
        'default' => true,
      ),
      12 => 
      array (
        'key' => 'available_care',
        'label' => 'Care',
        'type' => 'checkbox',
        'default' => true,
      ),
      13 => 
      array (
        'key' => 'is_active',
        'label' => 'Đang kinh doanh',
        'type' => 'checkbox',
        'default' => true,
      ),
    ),
    'dialog_resources' => 
    array (
      '1.3.1-dialog-create' => '1.3.1:product',
      '1.3.1-dialog-ph#U00e2n lo#U1ea1i s#U1ea3n ph#U1ea9m' => '1.3.1:category',
      '1.3.1-dialog-thu#U1ed9c t#U00ednh s#U1ea3n ph#U1ea9m' => '1.3.1:attribute',
      '1.3.1-dialog-thu#U1ed9c t#U00ednh gi#U00e1 tr#U1ecb' => '1.3.1:attribute-value',
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'category',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'unit',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'cost_price',
        'format' => 'currency',
        'align' => 'right',
      ),
      5 => 
      array (
        'key' => 'unit_price',
        'format' => 'currency',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'vat',
        'format' => 'percent',
      ),
      7 => 
      array (
        'key' => 'vat_code',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'price_after_vat',
        'format' => 'currency',
      ),
      9 => 
      array (
        'key' => 'weight',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'inactive',
        'format' => 'boolean',
      ),
      11 => 
      array (
        'key' => 'marketing',
        'format' => 'boolean',
      ),
      12 => 
      array (
        'key' => 'sale',
        'format' => 'boolean',
      ),
      13 => 
      array (
        'key' => 'care',
        'format' => 'boolean',
      ),
      14 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      15 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '1.3.2' => [
    'title' => 'Danh sách combo',
    'columns' => [
      ['key' => 'id', 'label' => '#', 'format' => 'text'],
      ['key' => 'code', 'label' => 'Mã combo', 'format' => 'text'],
      ['key' => 'name', 'label' => 'Tên combo', 'format' => 'text'],
      ['key' => 'components', 'label' => 'Sản phẩm trong combo', 'format' => 'text'],
      ['key' => 'product_count', 'label' => 'Tổng số SP', 'format' => 'number'],
      ['key' => 'original_total', 'label' => 'Tổng giá gốc', 'format' => 'currency', 'align' => 'right'],
      ['key' => 'combo_total', 'label' => 'Tổng giá combo', 'format' => 'currency', 'align' => 'right'],
      ['key' => 'status', 'label' => 'Trạng thái', 'format' => 'status'],
      ['key' => 'applied_at', 'label' => 'Thời gian áp dụng', 'format' => 'text'],
      ['key' => 'updated_at', 'label' => 'Cập nhật', 'format' => 'datetime'],
      ['key' => 'actions', 'label' => 'Thao tác', 'format' => 'actions'],
    ],
    'source' => 'combos',
    'editable' => true,
    'dialogs' => ['1.3.2-dialog-create'],
    'slug' => '1-3-2-danh-sach-combo',
    'component' => 'Page_1_3_2',
    'resource_key' => '1.3.2',
    'form_fields' => [
      ['key' => 'name', 'label' => 'Tên combo', 'type' => 'text', 'required' => true],
      ['key' => 'sku', 'label' => 'Mã combo', 'type' => 'text'],
      ['key' => 'unit_price', 'label' => 'Giá combo', 'type' => 'currency', 'required' => true],
      ['key' => 'component_product_ids', 'label' => 'Sản phẩm trong combo', 'type' => 'multiselect', 'option_source' => 'products'],
      ['key' => 'component_items', 'label' => 'Chi tiết sản phẩm trong combo', 'type' => 'combo-items'],
      ['key' => 'is_active', 'label' => 'Đang áp dụng', 'type' => 'checkbox', 'default' => true],
    ],
    'dialog_resources' => [
      '1.3.2-dialog-create' => '1.3.2',
    ],
    'display_columns' => [
      ['key' => 'id', 'format' => 'text'],
      ['key' => 'code', 'format' => 'text'],
      ['key' => 'name', 'format' => 'text'],
      ['key' => 'components', 'format' => 'text'],
      ['key' => 'product_count', 'format' => 'number'],
      ['key' => 'original_total', 'format' => 'currency'],
      ['key' => 'combo_total', 'format' => 'currency'],
      ['key' => 'status', 'format' => 'status'],
      ['key' => 'applied_at', 'format' => 'date'],
      ['key' => 'updated_at', 'format' => 'datetime'],
      ['key' => 'actions', 'format' => 'actions'],
    ],
  ],
  '1.7.1' => 
  array (
    'title' => 'Lịch sử đăng nhập',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'ip_address',
        'label' => 'IPAddress',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'company',
        'label' => 'Đơn vị',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'account',
        'label' => 'Tài khoản',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'access_code',
        'label' => 'Mã truy cập',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'browser',
        'label' => 'Mã browser',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'created_at',
        'label' => 'Ngày thực hiện',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'format' => 'status',
      ),
    ),
    'source' => 'activity_logs',
    'filters' => 
    array (
      0 => 'role',
      1 => 'user',
      2 => 'status',
      3 => 'sort',
      4 => 'search',
      5 => 'date_range',
    ),
    'slug' => '1-7-1-lich-su-dang-nhap',
    'component' => 'Page_1_7_1',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'ip_address',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'company',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'account',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'access_code',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'browser',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'created_at',
        'format' => 'datetime',
      ),
      7 => 
      array (
        'key' => 'status',
        'format' => 'status',
      ),
      8 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '1.7.2' => 
  array (
    'title' => 'Quản lý cho phép tài khoản đăng nhập',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'company',
        'label' => 'Đơn vị',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'account',
        'label' => 'Tài khoản',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'access_code',
        'label' => 'Mã truy cập',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'login_at',
        'label' => 'Ngày đăng nhập',
        'format' => 'datetime',
      ),
      4 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'format' => 'status',
      ),
      5 => 
      array (
        'key' => 'actions',
        'label' => 'Thao tác',
        'format' => 'text',
      ),
    ),
    'source' => 'login_permissions',
    'editable' => false,
    'slug' => '1-7-2-quan-ly-cho-phep-tai-khoan-dang-nhap',
    'component' => 'Page_1_7_2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'company',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'account',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'access_code',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'login_at',
        'format' => 'datetime',
      ),
      4 => 
      array (
        'key' => 'status',
        'format' => 'status',
      ),
      5 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '1.7.3' => 
  array (
    'title' => 'Lịch sử lọc data chốt đơn',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'filter_form',
        'label' => 'Form lọc / Trang lọc / Dữ liệu lọc tối đa',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'closing_status',
        'label' => 'Trạng thái chốt đơn',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'delivery_status',
        'label' => 'Trạng thái giao hàng',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'date_filter',
        'label' => 'Kiểu ngày / Ngày lọc',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'user',
        'label' => 'User',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'created_at',
        'label' => 'Ngày lọc',
        'format' => 'datetime',
      ),
    ),
    'source' => 'activity_logs',
    'slug' => '1-7-3-lich-su-loc-data-chot-don',
    'component' => 'Page_1_7_3',
  ),
  '1.8.1' => 
  array (
    'title' => 'Quản lý danh mục tác nghiệp',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => 'Id',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'sort_order',
        'label' => 'STT',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'is_start',
        'label' => 'Khởi đầu',
        'format' => 'boolean',
      ),
      4 => 
      array (
        'key' => 'pool',
        'label' => 'Kho số',
        'format' => 'boolean',
      ),
      5 => 
      array (
        'key' => 'duration',
        'label' => 'Sửa giờ',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      7 => 
      array (
        'key' => 'actions',
        'label' => 'Thêm',
        'format' => 'text',
      ),
    ),
    'source' => 'operation_categories',
    'editable' => true,
    'slug' => '1-8-1-quan-ly-danh-muc-tac-nghiep',
    'component' => 'Page_1_8_1',
    'resource_key' => '1.8.1',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Tên tác nghiệp',
        'type' => 'text',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'sort_order',
        'label' => 'STT',
        'type' => 'number',
      ),
      2 => 
      array (
        'key' => 'is_start',
        'label' => 'Khởi đầu',
        'type' => 'checkbox',
      ),
      3 => 
      array (
        'key' => 'is_pool',
        'label' => 'Kho số',
        'type' => 'checkbox',
      ),
      4 => 
      array (
        'key' => 'duration_minutes',
        'label' => 'Thời lượng (phút)',
        'type' => 'number',
      ),
      5 => 
      array (
        'key' => 'is_active',
        'label' => 'Đang sử dụng',
        'type' => 'checkbox',
        'default' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'is_start',
        'format' => 'boolean',
      ),
      4 => 
      array (
        'key' => 'duration',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '1.8.2' => 
  array (
    'title' => 'Thiết lập tác nghiệp',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => 'Id',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'condition',
        'label' => 'Nếu',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'result',
        'label' => 'Kết quả',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'next_operation',
        'label' => 'Thì',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'delay',
        'label' => 'Sau bao lâu',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'actions',
        'label' => 'Thêm',
        'format' => 'text',
      ),
    ),
    'source' => 'operation_workflows',
    'editable' => true,
    'slug' => '1-8-2-thiet-lap-tac-nghiep',
    'component' => 'Page_1_8_2',
    'resource_key' => '1.8.2',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'from_operation_category_id',
        'label' => 'Nếu đang ở tác nghiệp',
        'type' => 'select',
        'option_source' => 'operationCategories',
      ),
      1 => 
      array (
        'key' => 'condition_type',
        'label' => 'Điều kiện',
        'type' => 'text',
      ),
      2 => 
      array (
        'key' => 'operation_result',
        'label' => 'Kết quả',
        'type' => 'text',
      ),
      3 => 
      array (
        'key' => 'to_operation_category_id',
        'label' => 'Thì chuyển sang',
        'type' => 'select',
        'option_source' => 'operationCategories',
      ),
      4 => 
      array (
        'key' => 'delay_minutes',
        'label' => 'Sau bao lâu (phút)',
        'type' => 'number',
      ),
      5 => 
      array (
        'key' => 'is_active',
        'label' => 'Đang áp dụng',
        'type' => 'checkbox',
        'default' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'id',
        'label' => 'Id',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'condition',
        'label' => 'Nếu',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'result',
        'label' => 'Kết quả',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'next_operation',
        'label' => 'Thì',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'delay',
        'label' => 'Sau bao lâu',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      7 => 
      array (
        'key' => 'actions',
        'label' => 'Thêm',
        'format' => 'text',
      ),
    ),
  ),
  '1.9' => 
  array (
    'title' => 'Thiết lập chiết khấu, COD',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'order_from',
        'label' => 'Giá trị đơn hàng từ (trở lên)',
        'format' => 'currency',
      ),
      2 => 
      array (
        'key' => 'discount_value',
        'label' => 'Giá trị chiết khấu',
        'format' => 'currency',
      ),
      3 => 
      array (
        'key' => 'calculation_type',
        'label' => 'Tính theo',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      5 => 
      array (
        'key' => 'actions',
        'label' => 'Thêm',
        'format' => 'text',
      ),
    ),
    'source' => 'discount_cod_rules',
    'editable' => true,
    'kind' => 'split',
    'slug' => '1-9-thiet-lap-chiet-khau-cod',
    'component' => 'Page_1_9',
    'resource_key' => '1.9',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'order_from',
        'label' => 'Giá trị đơn hàng từ',
        'type' => 'currency',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'discount_value',
        'label' => 'Giá trị chiết khấu',
        'type' => 'currency',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'calculation_type',
        'label' => 'Tính theo',
        'type' => 'select',
        'options' => 
        array (
          'fixed' => 'Số tiền',
          'percent' => 'Phần trăm',
        ),
      ),
      3 => 
      array (
        'key' => 'cod_from',
        'label' => 'COD từ',
        'type' => 'currency',
      ),
      4 => 
      array (
        'key' => 'cod_to',
        'label' => 'COD đến',
        'type' => 'currency',
      ),
      5 => 
      array (
        'key' => 'is_active',
        'label' => 'Đang áp dụng',
        'type' => 'checkbox',
        'default' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'order_from',
        'format' => 'currency',
        'align' => 'right',
      ),
      3 => 
      array (
        'key' => 'discount_value',
        'format' => 'currency',
        'align' => 'right',
      ),
      4 => 
      array (
        'key' => 'calculation_type',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '1.10' => 
  array (
    'title' => 'Import contact',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'filename',
        'label' => 'Tên file',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'format' => 'status',
      ),
      2 => 
      array (
        'key' => 'total_rows',
        'label' => 'Tổng dòng',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'valid_rows',
        'label' => 'Hợp lệ',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'invalid_rows',
        'label' => 'Không hợp lệ',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'created_at',
        'label' => 'Ngày import',
        'format' => 'datetime',
      ),
    ),
    'source' => 'lead_imports',
    'kind' => 'import',
    'slug' => '1-10-import-contact',
    'component' => 'Page_1_10',
    'grid_enabled' => false,
    'import_url' => '/admin/leads/import',
    'template_url' => '/admin/leads/import-template',
  ),
  '1.11' => 
  array (
    'title' => 'Cấu hình Facebook của đơn vị',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'fanpage',
        'label' => 'Fanpage',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'fb_creator',
        'label' => 'FB Creator',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'pushsale_user',
        'label' => 'Pushsale User',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'facebook_page_mappings',
    'editable' => true,
    'slug' => '1-11-cau-hinh-facebook-cua-don-vi',
    'component' => 'Page_1_11',
    'grid_enabled' => true,
    'kind' => 'table',
    'resource_key' => '1.11',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'fanpage',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'fb_creator',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'pushsale_user',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '1.13.1' => 
  array (
    'title' => 'Quản lý số blacklist',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'phone',
        'label' => 'Số blacklist',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'reason',
        'label' => 'Lý do',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'order_code',
        'label' => 'Đơn hàng',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'creation_type',
        'label' => 'Kiểu tạo',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'creator',
        'label' => 'Người tạo',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'phone_blacklists',
    'editable' => true,
    'slug' => '1-13-1-quan-ly-so-blacklist',
    'component' => 'Page_1_13_1',
    'resource_key' => '1.13.1',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'phone',
        'label' => 'Số blacklist',
        'type' => 'tel',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'reason',
        'label' => 'Lý do',
        'type' => 'textarea',
      ),
      2 => 
      array (
        'key' => 'order_id',
        'label' => 'Đơn hàng',
        'type' => 'select',
        'option_source' => 'orders',
      ),
      3 => 
      array (
        'key' => 'creation_type',
        'label' => 'Kiểu tạo',
        'type' => 'select',
        'options' => 
        array (
          'manual' => 'Thủ công',
          'automatic' => 'Tự động',
        ),
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'phone',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'reason',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'order_code',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'creation_type',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'creator',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      8 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '2.3' => 
  array (
    'title' => 'Hồ sơ khách hàng',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'order_code',
        'label' => 'Mã đơn',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'source',
        'label' => 'Nguồn dữ liệu / Ngày data về',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'customer',
        'label' => 'Họ tên / Số điện thoại',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'address',
        'label' => 'Địa chỉ',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'message',
        'label' => 'Tin nhắn',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'sale',
        'label' => 'Sale / Ngày nhận data',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'operation',
        'label' => 'Tác nghiệp / Ngày chốt đơn',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'result',
        'label' => 'Kết quả / Ngày sale tác nghiệp',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'products',
        'label' => 'Sản phẩm - Số lượng - Đơn giá',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'money',
        'label' => 'Thành tiền / CK/VAT / Phí VC/Tổng tiền',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'deposit',
        'label' => 'Khách đặt cọc',
        'format' => 'currency',
      ),
      11 => 
      array (
        'key' => 'shipping',
        'label' => 'Kho / PTGH / Mã giao vận',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'delivery',
        'label' => 'Trạng thái giao hàng / Ngày muốn nhận hàng',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'internal_note',
        'label' => 'ĐSNB',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'actions',
        'label' => 'Thao tác',
        'format' => 'text',
      ),
    ),
    'source' => 'customer_orders',
    'kind' => 'customer_profile',
    'upsell' => true,
    'slug' => '2-3-ho-so-khach-hang',
    'component' => 'Page_2_3',
    'template_alias' => '4.2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'order_code',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'customer',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'address',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'message',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'operation',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'result',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'products',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'money',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'deposit',
        'format' => 'currency',
        'align' => 'right',
      ),
      12 => 
      array (
        'key' => 'shipping',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'delivery',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'internal_note',
        'format' => 'text',
      ),
      15 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '2.4.1' => 
  array (
    'title' => 'Kết nối landing',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'marketer',
        'label' => 'Marketing',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'label' => 'Tên nguồn kết nối / Url nguồn dữ liệu',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'channel',
        'label' => 'Loại kết nối / Kênh quảng cáo',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'sale_priority',
        'label' => 'Ưu tiên sale',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'allocation',
        'label' => 'Cấu hình chia số',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'webhook_url',
        'label' => 'URL nhận dữ liệu',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'manual_import',
        'label' => 'Nhập TC',
        'format' => 'boolean',
      ),
      9 => 
      array (
        'key' => 'approved',
        'label' => 'Duyệt',
        'format' => 'boolean',
      ),
      10 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      11 => 
      array (
        'key' => 'actions',
        'label' => 'Thêm',
        'format' => 'text',
      ),
    ),
    'source' => 'landing_connections',
    'template_alias' => '2.4.1',
    'slug' => '2-4-1-ket-noi-du-lieu',
    'component' => 'Marketing/LandingConnectionsPage',
    'resource_key' => '2.4.1',
    'editable' => true,
    'grid_enabled' => true,
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'marketer',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'format' => 'multiline',
      ),
      3 => 
      array (
        'key' => 'channel',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'sale_priority',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'allocation',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'webhook_url',
        'format' => 'copy',
      ),
      8 => 
      array (
        'key' => 'manual_import',
        'format' => 'boolean',
      ),
      9 => 
      array (
        'key' => 'approved',
        'format' => 'boolean',
      ),
      10 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      11 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '2.4.2' => 
  array (
    'title' => 'Kết nối dữ liệu',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'marketer',
        'label' => 'Marketing',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'label' => 'Tên nguồn kết nối / Url nguồn dữ liệu',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'channel',
        'label' => 'Loại kết nối / Kênh quảng cáo',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'sale_priority',
        'label' => 'Ưu tiên sale',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'allocation',
        'label' => 'Cấu hình chia số',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'webhook_url',
        'label' => 'Url kết nối V2',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'manual_import',
        'label' => 'Nhập TC',
        'format' => 'boolean',
      ),
      9 => 
      array (
        'key' => 'approved',
        'label' => 'Duyệt',
        'format' => 'boolean',
      ),
      10 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
      11 => 
      array (
        'key' => 'actions',
        'label' => 'Thêm',
        'format' => 'text',
      ),
    ),
    'source' => 'marketing_sources',
    'slug' => '2-4-2-ket-noi-du-lieu',
    'component' => 'Page_2_4_2',
    'resource_key' => '2.4.2',
    'editable' => true,
    'grid_enabled' => true,
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'marketer',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'format' => 'multiline',
      ),
      3 => 
      array (
        'key' => 'channel',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'sale_priority',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'allocation',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'webhook_url',
        'format' => 'copy',
      ),
      8 => 
      array (
        'key' => 'manual_import',
        'format' => 'boolean',
      ),
      9 => 
      array (
        'key' => 'approved',
        'format' => 'boolean',
      ),
      10 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      11 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '2.6.1' => 
  array (
    'title' => 'Import contact',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'filename',
        'label' => 'Tên file',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'format' => 'status',
      ),
      2 => 
      array (
        'key' => 'total_rows',
        'label' => 'Tổng dòng',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'valid_rows',
        'label' => 'Hợp lệ',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'invalid_rows',
        'label' => 'Không hợp lệ',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'created_at',
        'label' => 'Ngày import',
        'format' => 'datetime',
      ),
    ),
    'source' => 'lead_imports',
    'kind' => 'import',
    'template_alias' => '1.10',
    'slug' => '2-6-1-import-contact',
    'component' => 'Page_2_6_1',
    'grid_enabled' => false,
    'import_url' => '/admin/leads/import',
    'template_url' => '/admin/leads/import-template',
  ),
  '2.6.2' => 
  array (
    'title' => 'Nhập data thủ công',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'customer_name',
        'label' => 'Họ tên',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'customer_phone',
        'label' => 'Số điện thoại',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'message',
        'label' => 'Tin nhắn',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'created_at',
        'label' => 'Ngày tạo',
        'format' => 'datetime',
      ),
    ),
    'source' => 'manual_lead_ingestions',
    'kind' => 'form',
    'upsell' => true,
    'slug' => '2-6-2-nhap-data-thu-cong',
    'component' => 'Page_2_6_2',
    'form_fields' => array (
      0 => array ('key' => 'marketing_source_id', 'label' => 'Nguồn dữ liệu', 'type' => 'select', 'option_source' => 'sources'),
      1 => array ('key' => 'product_ids', 'label' => 'Sản phẩm', 'type' => 'multiselect', 'option_source' => 'products'),
    ),
  ),
  '2.6.3' => 
  array (
    'title' => 'Kết nối các đơn vị đối tác',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'marketer',
        'label' => 'Marketing',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'label' => 'Tên nguồn kết nối',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'url',
        'label' => 'Đường link',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'channel',
        'label' => 'Loại kết nối / Kênh quảng cáo',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'sale_priority',
        'label' => 'Ưu tiên sale',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'token',
        'label' => 'Token kết nối',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'webhook_url',
        'label' => 'Url kết nối',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'manual_import',
        'label' => 'Nhập TC',
        'format' => 'boolean',
      ),
      10 => 
      array (
        'key' => 'approved',
        'label' => 'Duyệt',
        'format' => 'boolean',
      ),
      11 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'partner_connections',
    'editable' => true,
    'slug' => '2-6-3-ket-noi-cac-don-vi-doi-tac',
    'component' => 'Page_2_6_3',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'marketer',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'url',
        'format' => 'copy',
      ),
      4 => 
      array (
        'key' => 'channel',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'sale_priority',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'token',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'webhook_url',
        'format' => 'copy',
      ),
      9 => 
      array (
        'key' => 'manual_import',
        'format' => 'boolean',
      ),
      10 => 
      array (
        'key' => 'approved',
        'format' => 'boolean',
      ),
      11 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      12 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
    'resource_key' => '2.6.3',
    'grid_enabled' => true,
  ),
  '2.6.4' => 
  array (
    'title' => 'Kho số seeding (tối đa 1000)',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'phone',
        'label' => 'Số seeding',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'creator',
        'label' => 'Người tạo',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'seeding_phone_numbers',
    'editable' => true,
    'slug' => '2-6-4-kho-so-seeding-toi-da-1000',
    'component' => 'Page_2_6_4',
    'resource_key' => '2.6.4',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'phone',
        'label' => 'Số seeding',
        'type' => 'tel',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'is_active',
        'label' => 'Đang sử dụng',
        'type' => 'checkbox',
        'default' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'phone',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'creator',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      5 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '3.1' => 
  array (
    'title' => 'Quản lý khách hàng',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'order_code',
        'label' => 'Mã đơn',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'source',
        'label' => 'Nguồn dữ liệu / Ngày data về',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'customer',
        'label' => 'Họ tên / Số điện thoại',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'address',
        'label' => 'Địa chỉ',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'message',
        'label' => 'Tin nhắn',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'sale',
        'label' => 'Sale / Ngày nhận data',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'operation',
        'label' => 'Tác nghiệp / Ngày chốt đơn',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'result',
        'label' => 'Kết quả / Ngày sale tác nghiệp',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'products',
        'label' => 'Sản phẩm - Số lượng - Đơn giá',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'money',
        'label' => 'Thành tiền / CK/VAT / Phí VC/Tổng tiền',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'deposit',
        'label' => 'Khách đặt cọc',
        'format' => 'currency',
      ),
      11 => 
      array (
        'key' => 'shipping',
        'label' => 'Kho / PTGH / Mã giao vận',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'delivery',
        'label' => 'Trạng thái giao hàng / Ngày muốn nhận hàng',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'internal_note',
        'label' => 'ĐSNB',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'actions',
        'label' => 'Thao tác',
        'format' => 'text',
      ),
    ),
    'source' => 'customer_orders',
    'kind' => 'customer_profile',
    'upsell' => true,
    'slug' => '3-1-quan-ly-khach-hang',
    'component' => 'Page_3_1',
    'template_alias' => '4.2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'order_code',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'customer',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'address',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'message',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'operation',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'result',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'products',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'money',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'deposit',
        'format' => 'currency',
        'align' => 'right',
      ),
      12 => 
      array (
        'key' => 'shipping',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'delivery',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'internal_note',
        'format' => 'text',
      ),
      15 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '3.2' => 
  array (
    'title' => 'Quản lý chiến dịch chăm sóc',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'company',
        'label' => 'Đơn vị',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'label' => 'Tên chiến dịch',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'customer_condition',
        'label' => 'Điều kiện khách hàng',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'repeat_days',
        'label' => 'Số ngày lặp lại',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'starts_at',
        'label' => 'Ngày bắt đầu',
        'format' => 'date',
      ),
      6 => 
      array (
        'key' => 'ends_at',
        'label' => 'Ngày kết thúc',
        'format' => 'date',
      ),
      7 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'format' => 'status',
      ),
      8 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'care_campaigns',
    'editable' => true,
    'slug' => '3-2-quan-ly-chien-dich-cham-soc',
    'component' => 'Page_3_2',
    'resource_key' => '3.2',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Tên chiến dịch',
        'type' => 'text',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'customer_condition',
        'label' => 'Điều kiện khách hàng',
        'type' => 'json',
      ),
      2 => 
      array (
        'key' => 'repeat_days',
        'label' => 'Số ngày lặp lại',
        'type' => 'number',
      ),
      3 => 
      array (
        'key' => 'starts_at',
        'label' => 'Ngày bắt đầu',
        'type' => 'date',
      ),
      4 => 
      array (
        'key' => 'ends_at',
        'label' => 'Ngày kết thúc',
        'type' => 'date',
      ),
      5 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'type' => 'select',
        'options' => 
        array (
          'draft' => 'Nháp',
          'active' => 'Đang chạy',
          'paused' => 'Tạm dừng',
          'completed' => 'Hoàn thành',
        ),
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'company',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'customer_condition',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'repeat_days',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'starts_at',
        'format' => 'date',
      ),
      6 => 
      array (
        'key' => 'ends_at',
        'format' => 'date',
      ),
      7 => 
      array (
        'key' => 'status',
        'format' => 'status',
      ),
      8 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      9 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '3.3.1' => 
  array (
    'title' => 'Thống kê khách hàng đa chiều',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'dimension',
        'label' => 'Chỉ số',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'quantity',
        'label' => 'Số lượng',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'ratio',
        'label' => 'Tỉ trọng',
        'format' => 'percent',
      ),
    ),
    'source' => 'customer_multidimensional',
    'kind' => 'report',
    'slug' => '3-3-1-thong-ke-khach-hang-da-chieu',
    'component' => 'Page_3_3_1',
  ),
  '3.3.2' => 
  array (
    'title' => 'Thống kê khách hàng chi trả',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'customer_type',
        'label' => 'Loại khách',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'delivery_status',
        'label' => 'Trạng thái giao hàng',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'customer_count',
        'label' => 'Số lượng khách',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'ratio',
        'label' => 'Phần trăm',
        'format' => 'percent',
      ),
      5 => 
      array (
        'key' => 'description',
        'label' => 'Mô tả',
        'format' => 'text',
      ),
    ),
    'source' => 'customer_spending',
    'kind' => 'report',
    'slug' => '3-3-2-thong-ke-khach-hang-chi-tra',
    'component' => 'Page_3_3_2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'customer_type',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'customer_count',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'ratio',
        'format' => 'percent',
      ),
    ),
  ),
  '4.2' => 
  array (
    'title' => 'Hồ sơ khách hàng',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'order_code',
        'label' => 'Mã đơn',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'source',
        'label' => 'Nguồn dữ liệu / Ngày data về',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'customer',
        'label' => 'Họ tên / Số điện thoại',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'address',
        'label' => 'Địa chỉ',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'message',
        'label' => 'Tin nhắn',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'sale',
        'label' => 'Sale / Ngày nhận data',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'operation',
        'label' => 'Tác nghiệp / Ngày chốt đơn',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'result',
        'label' => 'Kết quả / Ngày sale tác nghiệp',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'products',
        'label' => 'Sản phẩm - Số lượng - Đơn giá',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'money',
        'label' => 'Thành tiền / CK/VAT / Phí VC/Tổng tiền',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'deposit',
        'label' => 'Khách đặt cọc',
        'format' => 'currency',
      ),
      11 => 
      array (
        'key' => 'shipping',
        'label' => 'Kho / PTGH / Mã giao vận',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'delivery',
        'label' => 'Trạng thái giao hàng / Ngày muốn nhận hàng',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'internal_note',
        'label' => 'ĐSNB',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'actions',
        'label' => 'Thao tác',
        'format' => 'text',
      ),
    ),
    'source' => 'customer_orders',
    'kind' => 'customer_profile',
    'upsell' => true,
    'slug' => '4-2-ho-so-khach-hang',
    'component' => 'Page_4_2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'order_code',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'source',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'customer',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'address',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'message',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'operation',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'result',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'products',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'money',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'deposit',
        'format' => 'currency',
        'align' => 'right',
      ),
      12 => 
      array (
        'key' => 'shipping',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'delivery',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'internal_note',
        'format' => 'text',
      ),
      15 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '4.3' => 
  array (
    'title' => 'Bảng xếp hạng Sales',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'label' => 'SALE',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'new_customers',
        'label' => 'KHÁCH HÀNG MỚI',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'old_customers',
        'label' => 'KHÁCH HÀNG CŨ',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'total',
        'label' => 'TỔNG CHUNG',
        'format' => 'text',
      ),
    ),
    'source' => 'sales_ranking',
    'kind' => 'ranking',
    'slug' => '4-3-bang-xep-hang-sales',
    'component' => 'Page_4_3',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'new_contacts',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'new_closed',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'new_rate',
        'format' => 'percent',
      ),
      5 => 
      array (
        'key' => 'new_products',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'new_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      7 => 
      array (
        'key' => 'old_contacts',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'old_closed',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'old_rate',
        'format' => 'percent',
      ),
      10 => 
      array (
        'key' => 'old_products',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'old_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      12 => 
      array (
        'key' => 'provisional_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      13 => 
      array (
        'key' => 'discount',
        'format' => 'currency',
        'align' => 'right',
      ),
      14 => 
      array (
        'key' => 'cod_collected',
        'format' => 'currency',
        'align' => 'right',
      ),
      15 => 
      array (
        'key' => 'cod_service_fee',
        'format' => 'currency',
        'align' => 'right',
      ),
      16 => 
      array (
        'key' => 'revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
    ),
  ),
  '4.6.1' => 
  array (
    'title' => 'Báo cáo tỉ lệ chốt đơn theo tác nghiệp',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'label' => 'SALE',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'total_contacts',
        'label' => 'Tổng contact',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'total_closed',
        'label' => 'Tổng chốt đơn',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'total_rate',
        'label' => 'Tổng tỷ lệ',
        'format' => 'percent',
      ),
      5 => 
      array (
        'key' => 'revenue',
        'label' => 'Tổng doanh số',
        'format' => 'currency',
      ),
      6 => 
      array (
        'key' => 'call_1',
        'label' => 'Gọi lần 1',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'call_2',
        'label' => 'Gọi lần 2',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'call_3',
        'label' => 'Gọi lần 3',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'call_4',
        'label' => 'Gọi lần 4',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'call_5',
        'label' => 'Gọi lần 5',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'call_6',
        'label' => 'Gọi lần 6',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'care_1',
        'label' => 'Chăm sóc lần 1',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'care_2',
        'label' => 'Chăm sóc lần 2',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'care_3',
        'label' => 'Chăm sóc lần 3',
        'format' => 'text',
      ),
      15 => 
      array (
        'key' => 'skipped',
        'label' => 'Bỏ qua',
        'format' => 'text',
      ),
    ),
    'source' => 'sale_operation_rate',
    'kind' => 'report',
    'slug' => '4-6-1-bao-cao-ti-le-chot-don-theo-tac-nghiep',
    'component' => 'Page_4_6_1',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'total_contacts',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'total_closed',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'total_rate',
        'format' => 'percent',
      ),
      5 => 
      array (
        'key' => 'revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'call_1_contacts',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'call_1_closed',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'call_1_rate',
        'format' => 'percent',
      ),
      9 => 
      array (
        'key' => 'call_1_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      10 => 
      array (
        'key' => 'call_2_contacts',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'call_2_closed',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'call_2_rate',
        'format' => 'percent',
      ),
      13 => 
      array (
        'key' => 'call_2_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      14 => 
      array (
        'key' => 'call_3_contacts',
        'format' => 'number',
      ),
      15 => 
      array (
        'key' => 'call_3_closed',
        'format' => 'number',
      ),
      16 => 
      array (
        'key' => 'call_3_rate',
        'format' => 'percent',
      ),
      17 => 
      array (
        'key' => 'call_3_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      18 => 
      array (
        'key' => 'call_4_contacts',
        'format' => 'number',
      ),
      19 => 
      array (
        'key' => 'call_4_closed',
        'format' => 'number',
      ),
      20 => 
      array (
        'key' => 'call_4_rate',
        'format' => 'percent',
      ),
      21 => 
      array (
        'key' => 'call_4_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      22 => 
      array (
        'key' => 'call_5_contacts',
        'format' => 'number',
      ),
      23 => 
      array (
        'key' => 'call_5_closed',
        'format' => 'number',
      ),
      24 => 
      array (
        'key' => 'call_5_rate',
        'format' => 'percent',
      ),
      25 => 
      array (
        'key' => 'call_5_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      26 => 
      array (
        'key' => 'call_6_contacts',
        'format' => 'number',
      ),
      27 => 
      array (
        'key' => 'call_6_closed',
        'format' => 'number',
      ),
      28 => 
      array (
        'key' => 'call_6_rate',
        'format' => 'percent',
      ),
      29 => 
      array (
        'key' => 'call_6_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      30 => 
      array (
        'key' => 'care_1_contacts',
        'format' => 'number',
      ),
      31 => 
      array (
        'key' => 'care_1_closed',
        'format' => 'number',
      ),
      32 => 
      array (
        'key' => 'care_1_rate',
        'format' => 'percent',
      ),
      33 => 
      array (
        'key' => 'care_1_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      34 => 
      array (
        'key' => 'care_2_contacts',
        'format' => 'number',
      ),
      35 => 
      array (
        'key' => 'care_2_closed',
        'format' => 'number',
      ),
      36 => 
      array (
        'key' => 'care_2_rate',
        'format' => 'percent',
      ),
      37 => 
      array (
        'key' => 'care_2_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      38 => 
      array (
        'key' => 'care_3_contacts',
        'format' => 'number',
      ),
      39 => 
      array (
        'key' => 'care_3_closed',
        'format' => 'number',
      ),
      40 => 
      array (
        'key' => 'care_3_rate',
        'format' => 'percent',
      ),
      41 => 
      array (
        'key' => 'care_3_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      42 => 
      array (
        'key' => 'skipped_contacts',
        'format' => 'number',
      ),
      43 => 
      array (
        'key' => 'skipped_closed',
        'format' => 'number',
      ),
      44 => 
      array (
        'key' => 'skipped_rate',
        'format' => 'percent',
      ),
      45 => 
      array (
        'key' => 'skipped_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
    ),
  ),
  '4.6.2' => 
  array (
    'title' => 'Báo cáo công việc sale',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'label' => 'SALE',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'total_contacts',
        'label' => 'Tổng contact',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'untouched',
        'label' => 'Tổng contact chưa tác nghiệp',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'call_1',
        'label' => 'Gọi lần 1',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'call_2',
        'label' => 'Gọi lần 2',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'call_3',
        'label' => 'Gọi lần 3',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'call_4',
        'label' => 'Gọi lần 4',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'call_5',
        'label' => 'Gọi lần 5',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'call_6',
        'label' => 'Gọi lần 6',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'care_1',
        'label' => 'Chăm sóc lần 1',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'care_2',
        'label' => 'Chăm sóc lần 2',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'care_3',
        'label' => 'Chăm sóc lần 3',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'skipped',
        'label' => 'Bỏ qua',
        'format' => 'text',
      ),
    ),
    'source' => 'sale_work',
    'kind' => 'report',
    'slug' => '4-6-2-bao-cao-cong-viec-sale',
    'component' => 'Page_4_6_2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'total_contacts',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'untouched',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'call_1_contacts',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'call_1_untouched',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'call_2_contacts',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'call_2_untouched',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'call_3_contacts',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'call_3_untouched',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'call_4_contacts',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'call_4_untouched',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'call_5_contacts',
        'format' => 'number',
      ),
      13 => 
      array (
        'key' => 'call_5_untouched',
        'format' => 'number',
      ),
      14 => 
      array (
        'key' => 'call_6_contacts',
        'format' => 'number',
      ),
      15 => 
      array (
        'key' => 'call_6_untouched',
        'format' => 'number',
      ),
      16 => 
      array (
        'key' => 'care_1_contacts',
        'format' => 'number',
      ),
      17 => 
      array (
        'key' => 'care_1_untouched',
        'format' => 'number',
      ),
      18 => 
      array (
        'key' => 'care_2_contacts',
        'format' => 'number',
      ),
      19 => 
      array (
        'key' => 'care_2_untouched',
        'format' => 'number',
      ),
      20 => 
      array (
        'key' => 'care_3_contacts',
        'format' => 'number',
      ),
      21 => 
      array (
        'key' => 'care_3_untouched',
        'format' => 'number',
      ),
      22 => 
      array (
        'key' => 'skipped_contacts',
        'format' => 'number',
      ),
      23 => 
      array (
        'key' => 'skipped_untouched',
        'format' => 'number',
      ),
    ),
  ),
  '4.6.3' => 
  array (
    'title' => 'Báo cáo nhóm sale',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'team',
        'label' => 'Nhóm sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'total_contacts',
        'label' => 'Tổng contact',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'closed',
        'label' => 'Chốt đơn',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'rate',
        'label' => 'Tỷ lệ chốt',
        'format' => 'percent',
      ),
      5 => 
      array (
        'key' => 'revenue',
        'label' => 'Doanh số',
        'format' => 'currency',
      ),
      6 => 
      array (
        'key' => 'delivered',
        'label' => 'Đã giao',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'delivered_revenue',
        'label' => 'Doanh số đã giao',
        'format' => 'currency',
      ),
    ),
    'source' => 'sale_team',
    'kind' => 'report',
    'slug' => '4-6-3-bao-cao-nhom-sale',
    'component' => 'Page_4_6_3',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'current_contacts',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'current_closed',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'current_rate',
        'format' => 'percent',
      ),
      5 => 
      array (
        'key' => 'current_products',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'current_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      7 => 
      array (
        'key' => 'previous_contacts',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'previous_closed',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'previous_rate',
        'format' => 'percent',
      ),
      10 => 
      array (
        'key' => 'previous_products',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'previous_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      12 => 
      array (
        'key' => 'provisional_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      13 => 
      array (
        'key' => 'cod_fee',
        'format' => 'currency',
        'align' => 'right',
      ),
      14 => 
      array (
        'key' => 'cod_support',
        'format' => 'currency',
        'align' => 'right',
      ),
      15 => 
      array (
        'key' => 'discount',
        'format' => 'currency',
        'align' => 'right',
      ),
      16 => 
      array (
        'key' => 'deposit',
        'format' => 'currency',
        'align' => 'right',
      ),
      17 => 
      array (
        'key' => 'after_discount_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      18 => 
      array (
        'key' => 'kpi_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      19 => 
      array (
        'key' => 'kpi_rate',
        'format' => 'percent',
      ),
    ),
  ),
  '4.6.4' => 
  array (
    'title' => 'Báo cáo data sale',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'label' => 'Sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'new_contacts',
        'label' => 'Contact mới',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'old_contacts',
        'label' => 'Contact cũ',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'operated',
        'label' => 'Đã tác nghiệp',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'untouched',
        'label' => 'Chưa tác nghiệp',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'closed',
        'label' => 'Chốt đơn',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'rate',
        'label' => 'Tỷ lệ',
        'format' => 'percent',
      ),
      8 => 
      array (
        'key' => 'revenue',
        'label' => 'Doanh số',
        'format' => 'currency',
      ),
    ),
    'source' => 'sale_data',
    'kind' => 'report',
    'slug' => '4-6-4-bao-cao-data-sale',
    'component' => 'Page_4_6_4',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'received',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'duplicate',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'unique',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'new_rate',
        'format' => 'percent',
      ),
      6 => 
      array (
        'key' => 'new_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      7 => 
      array (
        'key' => 'old_rate',
        'format' => 'percent',
      ),
      8 => 
      array (
        'key' => 'old_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      9 => 
      array (
        'key' => 'care_rate',
        'format' => 'percent',
      ),
      10 => 
      array (
        'key' => 'care_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      11 => 
      array (
        'key' => 'receive_data',
        'format' => 'boolean',
      ),
    ),
  ),
  '4.6.5' => 
  array (
    'title' => 'Báo cáo tối ưu Sale',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'label' => 'Sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'contacts',
        'label' => 'Contact nhận',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'operation_rate',
        'label' => 'Tỷ lệ tác nghiệp',
        'format' => 'percent',
      ),
      4 => 
      array (
        'key' => 'closing_rate',
        'label' => 'Tỷ lệ chốt',
        'format' => 'percent',
      ),
      5 => 
      array (
        'key' => 'revenue',
        'label' => 'Doanh số',
        'format' => 'currency',
      ),
      6 => 
      array (
        'key' => 'score',
        'label' => 'Điểm tối ưu',
        'format' => 'number',
      ),
    ),
    'source' => 'sale_optimization',
    'kind' => 'report',
    'slug' => '4-6-5-bao-cao-toi-uu-sale',
    'component' => 'Page_4_6_5',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'receive_data',
        'format' => 'boolean',
      ),
      3 => 
      array (
        'key' => 'provisional_revenue',
        'format' => 'currency',
      ),
      4 => 
      array (
        'key' => 'success_revenue',
        'format' => 'currency',
      ),
      5 => 
      array (
        'key' => 'contacts',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'allocated_total',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'allocated_duplicate',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'allocated_unique',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'pool_total',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'pool_duplicate',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'pool_unique',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'pool_closed',
        'format' => 'number',
      ),
      13 => 
      array (
        'key' => 'pool_revenue',
        'format' => 'currency',
      ),
      14 => 
      array (
        'key' => 'answered_call_ratio',
        'format' => 'percent',
      ),
      15 => 
      array (
        'key' => 'call_duration',
        'format' => 'number',
      ),
      16 => 
      array (
        'key' => 'avg_call_duration',
        'format' => 'number',
      ),
      17 => 
      array (
        'key' => 'close_per_answered',
        'format' => 'percent',
      ),
      18 => 
      array (
        'key' => 'closed',
        'format' => 'number',
      ),
      19 => 
      array (
        'key' => 'closing_rate',
        'format' => 'percent',
      ),
      20 => 
      array (
        'key' => 'avg_order_value',
        'format' => 'currency',
      ),
      21 => 
      array (
        'key' => 'products_per_order',
        'format' => 'number',
      ),
      22 => 
      array (
        'key' => 'untouched',
        'format' => 'number',
      ),
      23 => 
      array (
        'key' => 'revenue_per_contact',
        'format' => 'currency',
      ),
      24 => 
      array (
        'key' => 'cancelled_revenue',
        'format' => 'currency',
      ),
      25 => 
      array (
        'key' => 'returned_revenue',
        'format' => 'currency',
      ),
      26 => 
      array (
        'key' => 'overdue_orders',
        'format' => 'number',
      ),
    ),
  ),
  '5.1' => 
  array (
    'title' => 'Tác nghiệp vận đơn',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'order_info',
        'label' => 'Sale / Ngày data về / Mã đơn',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'shipping',
        'label' => 'Kho / PTGH / Mã giao vận',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'care',
        'label' => 'Ngày cập nhật care đơn / Care đơn / Ghi chú kế toán',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'delivery',
        'label' => 'Ngày cập nhật / Trạng thái giao hàng / Ngày đăng đơn',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'customer',
        'label' => 'Họ tên / Số điện thoại / Ngày muốn nhận hàng',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'address',
        'label' => 'Địa chỉ / Ghi chú giao hàng / Hóa đơn điện tử',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'products',
        'label' => 'Sản phẩm - Số lượng - Đơn giá',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'money',
        'label' => 'Thành tiền / CK / VAT SP / Phí VC / Tổng tiền',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'deposit',
        'label' => 'Đặt cọc',
        'format' => 'currency',
      ),
      10 => 
      array (
        'key' => 'collect',
        'label' => 'Tiền thu của khách',
        'format' => 'currency',
      ),
      11 => 
      array (
        'key' => 'carrier_fee',
        'label' => 'Giá dịch vụ VC',
        'format' => 'currency',
      ),
      12 => 
      array (
        'key' => 'shipping_support',
        'label' => 'Phí VC hỗ trợ khách',
        'format' => 'currency',
      ),
      13 => 
      array (
        'key' => 'internal_note',
        'label' => 'ĐSNB',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'actions',
        'label' => 'Thao tác',
        'format' => 'text',
      ),
    ),
    'source' => 'warehouse_orders',
    'kind' => 'warehouse_operations',
    'upsell' => true,
    'slug' => '5-1-tac-nghiep-van-don',
    'component' => 'Page_5_1',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'order_info',
        'format' => 'multiline',
      ),
      3 => 
      array (
        'key' => 'shipping',
        'format' => 'multiline',
      ),
      4 => 
      array (
        'key' => 'care',
        'format' => 'multiline',
      ),
      5 => 
      array (
        'key' => 'delivery',
        'format' => 'multiline',
      ),
      6 => 
      array (
        'key' => 'customer',
        'format' => 'multiline',
      ),
      7 => 
      array (
        'key' => 'address',
        'format' => 'multiline',
      ),
      8 => 
      array (
        'key' => 'products',
        'format' => 'multiline',
      ),
      9 => 
      array (
        'key' => 'money',
        'format' => 'multiline',
      ),
      10 => 
      array (
        'key' => 'deposit',
        'format' => 'currency',
      ),
      11 => 
      array (
        'key' => 'collect',
        'format' => 'currency',
      ),
      12 => 
      array (
        'key' => 'carrier_fee',
        'format' => 'currency',
      ),
      13 => 
      array (
        'key' => 'shipping_support',
        'format' => 'currency',
      ),
      14 => 
      array (
        'key' => 'internal_note',
        'format' => 'text',
      ),
      15 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '5.2.1' => 
  array (
    'title' => 'Danh sách kho',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'name',
        'label' => 'Tên kho',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'phone',
        'label' => 'Số điện thoại',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'province',
        'label' => 'Tỉnh/TP',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'district',
        'label' => 'Quận/Huyện',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'ward',
        'label' => 'Phường/Xã',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'address',
        'label' => 'Địa chỉ',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'manager',
        'label' => 'Quản kho',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'vtp_code',
        'label' => 'Mã VTP',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'ghn_code',
        'label' => 'Mã GHN',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'warehouses',
    'dialogs' => 
    array (
      0 => '5.2.1-create-dialog',
    ),
    'slug' => '5-2-1-danh-sach-kho',
    'component' => 'Page_5_2_1',
    'editable' => true,
    'resource_key' => '5.2.1',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Tên kho',
        'type' => 'text',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'code',
        'label' => 'Mã kho',
        'type' => 'text',
      ),
      2 => 
      array (
        'key' => 'phone',
        'label' => 'Số điện thoại',
        'type' => 'tel',
      ),
      3 => 
      array (
        'key' => 'pick_province',
        'label' => 'Tỉnh/TP',
        'type' => 'text',
      ),
      4 => 
      array (
        'key' => 'pick_district',
        'label' => 'Quận/Huyện',
        'type' => 'text',
      ),
      5 => 
      array (
        'key' => 'pick_ward',
        'label' => 'Phường/Xã',
        'type' => 'text',
      ),
      6 => 
      array (
        'key' => 'address',
        'label' => 'Địa chỉ lấy hàng',
        'type' => 'textarea',
      ),
      7 => 
      array (
        'key' => 'manager_user_id',
        'label' => 'Quản lý kho',
        'type' => 'select',
        'option_source' => 'warehouseUsers',
      ),
      8 => 
      array (
        'key' => 'vtp_code',
        'label' => 'Mã Viettel Post',
        'type' => 'text',
      ),
      9 => 
      array (
        'key' => 'ghtk_pick_address_id',
        'label' => 'Mã địa chỉ GHTK',
        'type' => 'text',
      ),
    ),
    'dialog_resources' => 
    array (
      '5.2.1-create-dialog' => '5.2.1',
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'phone',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'province',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'district',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'ward',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'address',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'manager',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'vtp_code',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'ghn_code',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      11 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '5.2.2' => 
  array (
    'title' => 'Danh sách sản phẩm kho',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'warehouse',
        'label' => 'Kho',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'uom',
        'label' => 'Đơn vị tính',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'batch_code',
        'label' => 'Mã lô',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'expiry_date',
        'label' => 'Ngày hết hạn',
        'format' => 'date',
      ),
      6 => 
      array (
        'key' => 'location',
        'label' => 'Vị trí',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'stock',
        'label' => 'Tồn kho',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'pending',
        'label' => 'Chờ xuất bán hàng',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'low_stock',
        'label' => 'SL sắp hết hàng',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'discontinued',
        'label' => 'Ngừng KD',
        'format' => 'boolean',
      ),
      11 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'inventory',
    'slug' => '5-2-2-danh-sach-san-pham-kho',
    'component' => 'Page_5_2_2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'warehouse',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'uom',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'batch_code',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'expiry_date',
        'format' => 'date',
      ),
      7 => 
      array (
        'key' => 'location',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'stock',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'pending',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'low_stock',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'discontinued',
        'format' => 'boolean',
      ),
      12 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      13 => 
      array (
        'key' => 'actions',
        'format' => 'actions',
      ),
    ),
  ),
  '5.3.1' => 
  array (
    'title' => 'Phiếu nhập / xuất kho',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'sku',
        'label' => 'Mã sản phẩm',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'uom',
        'label' => 'Đv tính',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'document_quantity',
        'label' => 'SL chứng từ',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'quantity',
        'label' => 'Số lượng',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'unit_cost',
        'label' => 'Giá nhập',
        'format' => 'currency',
      ),
      7 => 
      array (
        'key' => 'total',
        'label' => 'Thành tiền',
        'format' => 'currency',
      ),
      8 => 
      array (
        'key' => 'batch_code',
        'label' => 'Lô',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'expiry_date',
        'label' => 'Ngày hết hạn',
        'format' => 'date',
      ),
      10 => 
      array (
        'key' => 'location',
        'label' => 'Mã vị trí',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'format' => 'text',
      ),
    ),
    'source' => 'warehouse_voucher_lines',
    'kind' => 'warehouse_voucher',
    'editable' => true,
    'slug' => '5-3-1-phieu-nhap-xuat-kho',
    'component' => 'Page_5_3_1',
    'resource_key' => '5.3.1',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'warehouse_id',
        'label' => 'Kho',
        'type' => 'select',
        'option_source' => 'warehouses',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'code',
        'label' => 'Mã phiếu',
        'type' => 'text',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'type',
        'label' => 'Loại phiếu',
        'type' => 'select',
        'options' => 
        array (
          'inbound' => 'Nhập kho',
          'outbound' => 'Xuất kho',
        ),
      ),
      3 => 
      array (
        'key' => 'document_date',
        'label' => 'Ngày chứng từ',
        'type' => 'date',
      ),
      4 => 
      array (
        'key' => 'product_id',
        'label' => 'Sản phẩm',
        'type' => 'select',
        'option_source' => 'products',
        'required' => true,
      ),
      5 => 
      array (
        'key' => 'document_quantity',
        'label' => 'SL chứng từ',
        'type' => 'number',
      ),
      6 => 
      array (
        'key' => 'quantity',
        'label' => 'Số lượng',
        'type' => 'number',
        'required' => true,
      ),
      7 => 
      array (
        'key' => 'unit_cost',
        'label' => 'Giá nhập',
        'type' => 'currency',
      ),
      8 => 
      array (
        'key' => 'batch_code',
        'label' => 'Lô',
        'type' => 'text',
      ),
      9 => 
      array (
        'key' => 'expiry_date',
        'label' => 'Ngày hết hạn',
        'type' => 'date',
      ),
      10 => 
      array (
        'key' => 'location_code',
        'label' => 'Mã vị trí',
        'type' => 'text',
      ),
      11 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'type' => 'textarea',
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
        'align' => 'center',
      ),
      1 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'sku',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'uom',
        'format' => 'text',
        'align' => 'center',
      ),
      4 => 
      array (
        'key' => 'document_quantity',
        'format' => 'number',
        'align' => 'right',
      ),
      5 => 
      array (
        'key' => 'quantity',
        'format' => 'number',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'unit_cost',
        'format' => 'currency',
        'align' => 'right',
      ),
      7 => 
      array (
        'key' => 'total',
        'format' => 'currency',
        'align' => 'right',
      ),
      8 => 
      array (
        'key' => 'batch_code',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'expiry_date',
        'format' => 'date',
        'align' => 'center',
      ),
      10 => 
      array (
        'key' => 'location',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'note',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'actions',
        'format' => 'text',
        'align' => 'center',
      ),
    ),
  ),
  '5.3.2' => 
  array (
    'title' => 'Danh sách phiếu xuất/nhập kho',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'warehouse',
        'label' => 'Kho',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'type',
        'label' => 'Loại phiếu',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'voucher_code',
        'label' => 'Mã phiếu',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'performed_at',
        'label' => 'Ngày thực hiện',
        'format' => 'datetime',
      ),
      5 => 
      array (
        'key' => 'total_quantity',
        'label' => 'Tổng số lượng',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'total_value',
        'label' => 'Tổng giá trị',
        'format' => 'currency',
      ),
      7 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'format' => 'status',
      ),
      8 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'internal_voucher',
        'label' => 'Phiếu XNNB',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'warehouse_vouchers',
    'editable' => false,
    'slug' => '5-3-2-danh-sach-phieu-xuat-nhap-kho',
    'component' => 'Page_5_3_2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'format' => 'text',
        'align' => 'center',
      ),
      1 => 
      array (
        'key' => 'warehouse',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'type',
        'format' => 'text',
        'align' => 'center',
      ),
      3 => 
      array (
        'key' => 'voucher_code',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'performed_at',
        'format' => 'date',
        'align' => 'center',
      ),
      5 => 
      array (
        'key' => 'total_quantity',
        'format' => 'number',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'total_value',
        'format' => 'currency',
        'align' => 'right',
      ),
      7 => 
      array (
        'key' => 'status',
        'format' => 'status',
        'align' => 'center',
      ),
      8 => 
      array (
        'key' => 'note',
        'format' => 'text',
      ),
      9 => 
      array (
        'key' => 'internal_voucher',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
        'align' => 'center',
      ),
    ),
  ),
  '5.3.3' => 
  array (
    'title' => 'Lịch sử nhập / xuất kho (Thẻ kho)',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'warehouse',
        'label' => 'Kho',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'type',
        'label' => 'Nghiệp vụ',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'quantity',
        'label' => 'Số lượng nhập/xuất',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'pending',
        'label' => 'Số lượng chờ xuất',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'reference',
        'label' => 'Mã đơn/Mã phiếu',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'created_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'movements',
    'slug' => '5-3-3-lich-su-nhap-xuat-kho-the-kho',
    'component' => 'Page_5_3_3',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
        'align' => 'center',
      ),
      1 => 
      array (
        'key' => 'warehouse',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'type',
        'format' => 'text',
        'align' => 'center',
      ),
      4 => 
      array (
        'key' => 'quantity',
        'format' => 'number',
        'align' => 'right',
      ),
      5 => 
      array (
        'key' => 'pending',
        'format' => 'number',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'reference',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'note',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'created_at',
        'format' => 'datetime',
        'align' => 'center',
      ),
    ),
  ),
  '5.4' => 
  array (
    'title' => 'Danh sách biên bản',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'manager',
        'label' => 'Tên quản lý',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'label' => 'Tên biên bản',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'document_date',
        'label' => 'Ngày biên bản',
        'format' => 'date',
      ),
      4 => 
      array (
        'key' => 'carrier',
        'label' => 'Đơn vị giao hàng',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'order_count',
        'label' => 'Số đơn',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'product_count',
        'label' => 'Số sản phẩm',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'format' => 'status',
      ),
      8 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'warehouse_incidents',
    'editable' => true,
    'slug' => '5-4-danh-sach-bien-ban',
    'component' => 'Page_5_4',
    'resource_key' => '5.4',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'manager_user_id',
        'label' => 'Tên quản lý',
        'type' => 'select',
        'option_source' => 'warehouseUsers',
      ),
      1 => 
      array (
        'key' => 'name',
        'label' => 'Tên biên bản',
        'type' => 'text',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'document_date',
        'label' => 'Ngày biên bản',
        'type' => 'date',
        'required' => true,
      ),
      3 => 
      array (
        'key' => 'carrier',
        'label' => 'Đơn vị giao hàng',
        'type' => 'select',
        'option_source' => 'shippingProviders',
        'required' => true,
      ),
      4 => 
      array (
        'key' => 'sender_name',
        'label' => 'Bên giao',
        'type' => 'text',
        'required' => true,
      ),
      5 => 
      array (
        'key' => 'receiver_name',
        'label' => 'Bên nhận',
        'type' => 'text',
        'required' => true,
      ),
      6 => 
      array (
        'key' => 'order_count',
        'label' => 'Số đơn',
        'type' => 'number',
      ),
      7 => 
      array (
        'key' => 'product_count',
        'label' => 'Số sản phẩm',
        'type' => 'number',
      ),
      8 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái',
        'type' => 'select',
        'options' => 
        array (
          'updating' => 'Đang cập nhật',
          'closed' => 'Đã chốt',
        ),
      ),
      9 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'type' => 'textarea',
        'required' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'manager',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'document_date',
        'format' => 'date',
      ),
      4 => 
      array (
        'key' => 'carrier',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'order_count',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'product_count',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'status',
        'format' => 'status',
      ),
      8 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      9 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '5.5.1' => 
  array (
    'title' => 'Bảng tổng hợp sản phẩm nhập, xuất theo ngày',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'warehouse',
        'label' => 'Kho',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'batch_code',
        'label' => 'Mã lô',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'opening',
        'label' => 'Đầu kỳ',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'intake',
        'label' => 'Nhập kho',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'export',
        'label' => 'Xuất kho',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'pending',
        'label' => 'Chờ xuất',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'closing',
        'label' => 'Cuối kỳ',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'available',
        'label' => 'Tồn chưa lên đơn',
        'format' => 'number',
      ),
    ),
    'source' => 'inventory_daily',
    'kind' => 'report',
    'slug' => '5-5-1-bang-tong-hop-san-pham-nhap-xuat-theo-ngay',
    'component' => 'Page_5_5_1',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'warehouse',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'batch_code',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'opening',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'intake',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'internal_intake',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'returns',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'export',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'internal_export',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'sold_export',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'destroyed',
        'format' => 'number',
      ),
      13 => 
      array (
        'key' => 'closing',
        'format' => 'number',
      ),
      14 => 
      array (
        'key' => 'available',
        'format' => 'number',
      ),
      15 => 
      array (
        'key' => 'avg_closed_daily',
        'format' => 'number',
      ),
      16 => 
      array (
        'key' => 'avg_sold_daily',
        'format' => 'number',
      ),
      17 => 
      array (
        'key' => 'days_remaining',
        'format' => 'number',
      ),
      18 => 
      array (
        'key' => 'pending_opening',
        'format' => 'number',
      ),
      19 => 
      array (
        'key' => 'pending',
        'format' => 'number',
      ),
      20 => 
      array (
        'key' => 'pending_sold',
        'format' => 'number',
      ),
      21 => 
      array (
        'key' => 'pending_closing',
        'format' => 'number',
      ),
    ),
  ),
  '5.5.2' => 
  array (
    'title' => 'Bảng tổng hợp chờ xuất theo ngày',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'warehouse',
        'label' => 'Kho',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'batch_code',
        'label' => 'Mã lô',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'opening',
        'label' => 'Đầu kỳ',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'pending',
        'label' => 'Chờ xuất',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'sold_export',
        'label' => 'Xuất bán hàng',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'closing',
        'label' => 'Cuối kỳ',
        'format' => 'number',
      ),
    ),
    'source' => 'inventory_pending',
    'kind' => 'report',
    'slug' => '5-5-2-bang-tong-hop-cho-xuat-theo-ngay',
    'component' => 'Page_5_5_2',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'warehouse',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'batch_code',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'opening',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'pending',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'sold_export',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'closing',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '5.5.4' => 
  array (
    'title' => 'Báo cáo tổng hợp phát sinh kho',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'warehouse',
        'label' => 'Kho',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'product',
        'label' => 'Sản phẩm',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'total_quantity',
        'label' => 'Tổng số lượng',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'total_pending',
        'label' => 'Tổng số lượng chờ xuất',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'quantity',
        'label' => 'Số lượng',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'pending',
        'label' => 'Số lượng chờ xuất',
        'format' => 'number',
      ),
    ),
    'source' => 'inventory_summary',
    'kind' => 'report',
    'slug' => '5-5-4-bao-cao-tong-hop-phat-sinh-kho',
    'component' => 'Page_5_5_4',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'product',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'total_quantity',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'total_pending',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'quantity',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'pending',
        'format' => 'number',
      ),
    ),
  ),
  '5.5.5' => 
  array (
    'title' => 'Báo cáo care đơn',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'care_user',
        'label' => 'TK vận đơn',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'received',
        'label' => 'Đã nhận',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'care_actions',
        'label' => 'TN care đơn',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'caring',
        'label' => 'Đang care',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'uncared',
        'label' => 'Chưa care',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'success',
        'label' => 'Care thành công',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'returned',
        'label' => 'Hoàn đơn',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'success_rate',
        'label' => 'Tỉ lệ thành công',
        'format' => 'percent',
      ),
      9 => 
      array (
        'key' => 'auto_success',
        'label' => 'Care thành công (Auto)',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'auto_return',
        'label' => 'Hoàn đơn (Auto)',
        'format' => 'number',
      ),
    ),
    'source' => 'care_report',
    'kind' => 'report',
    'slug' => '5-5-5-bao-cao-care-don',
    'component' => 'Page_5_5_5',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'care_user',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'today_received',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'today_actions',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'received',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'caring',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'uncared',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'care_actions',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'success',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'returned',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'success_rate',
        'format' => 'percent',
      ),
      11 => 
      array (
        'key' => 'auto_success',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'auto_return',
        'format' => 'number',
      ),
    ),
  ),
  '5.5.6' => 
  array (
    'title' => 'Báo cáo sửa số điện thoại giao hàng',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'label' => 'Sales',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'team',
        'label' => 'Tên nhóm',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'quantity',
        'label' => 'Số lượng',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'export',
        'label' => 'Xuất Excel',
        'format' => 'text',
      ),
    ),
    'source' => 'phone_corrections',
    'kind' => 'report',
    'slug' => '5-5-6-bao-cao-sua-so-dien-thoai-giao-hang',
    'component' => 'Page_5_5_6',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'old_phone',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'new_phone',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'editor',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'export',
        'format' => 'text',
      ),
    ),
  ),
  '5.5.7' => 
  array (
    'title' => 'Tổng hợp trạng thái giao hàng theo vận đơn',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'care_user',
        'label' => 'TK vận đơn',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'pending',
        'label' => 'Chờ vận đơn',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'shipping',
        'label' => 'Đang giao',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'delivered',
        'label' => 'Đã giao',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'returned',
        'label' => 'Hoàn đơn',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'cancelled',
        'label' => 'Hủy',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'total',
        'label' => 'Tổng',
        'format' => 'text',
      ),
    ),
    'source' => 'delivery_by_care',
    'kind' => 'report',
    'slug' => '5-5-7-tong-hop-trang-thai-giao-hang-theo-van-don',
    'component' => 'Page_5_5_7',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'care_user',
        'format' => 'text',
      ),
    ),
  ),
  '5.5.8' => 
  array (
    'title' => 'Báo cáo tác nghiệp care đơn',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'order_code',
        'label' => 'Mã đơn',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'care_user',
        'label' => 'Vận đơn',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'care_status',
        'label' => 'Trạng thái care',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'operated_at',
        'label' => 'Ngày tác nghiệp',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'previous_status',
        'label' => 'Trạng thái care cũ',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'export',
        'label' => 'Xuất Excel',
        'format' => 'text',
      ),
    ),
    'source' => 'care_operations',
    'kind' => 'report',
    'slug' => '5-5-8-bao-cao-tac-nghiep-care-don',
    'component' => 'Page_5_5_8',
  ),
  '5.8.2' => 
  array (
    'title' => 'Phân bổ data care đơn',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'care_user',
        'label' => 'User care đơn',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'account',
        'label' => 'Tài khoản',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'contacts',
        'label' => 'Số contact',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'receive_data',
        'label' => 'Nhận data',
        'format' => 'boolean',
      ),
      4 => 
      array (
        'key' => 'active',
        'label' => 'Đang sử dụng',
        'format' => 'boolean',
      ),
    ),
    'source' => 'care_distribution_rules',
    'editable' => true,
    'slug' => '5-8-2-phan-bo-data-care-don',
    'component' => 'Page_5_8_2',
    'resource_key' => '1.2.6',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'care_user_id',
        'label' => 'User care đơn',
        'type' => 'select',
        'option_source' => 'careUsers',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'quota',
        'label' => 'Định mức',
        'type' => 'number',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'receive_data',
        'label' => 'Nhận data',
        'type' => 'checkbox',
        'default' => true,
      ),
      3 => 
      array (
        'key' => 'sale_team_ids',
        'label' => 'Nhóm Sales',
        'type' => 'multiselect',
        'option_source' => 'saleTeams',
      ),
      4 => 
      array (
        'key' => 'warehouse_team_id',
        'label' => 'Nhóm vận đơn',
        'type' => 'select',
        'option_source' => 'warehouseTeams',
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'care_user',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'account',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'contacts',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'receive_data',
        'format' => 'boolean',
      ),
      5 => 
      array (
        'key' => 'active',
        'format' => 'boolean',
      ),
      6 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '6.2.1' => 
  array (
    'title' => 'Quản lý chi phí đơn vị',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'year',
        'label' => 'Năm',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'month',
        'label' => 'Tháng',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'group',
        'label' => 'Danh mục nhóm chi phí',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'category',
        'label' => 'Danh mục chi phí',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'unit',
        'label' => 'Đơn vị tính',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'unit_price',
        'label' => 'Đơn giá',
        'format' => 'currency',
      ),
      8 => 
      array (
        'key' => 'quantity',
        'label' => 'Số lượng',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'total',
        'label' => 'Thành tiền',
        'format' => 'currency',
      ),
      10 => 
      array (
        'key' => 'invoice',
        'label' => 'Hóa đơn',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'expenses',
    'editable' => true,
    'slug' => '6-2-1-quan-ly-chi-phi-don-vi',
    'component' => 'Page_6_2_1',
    'resource_key' => '6.2.1',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'type' => 'text',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'year',
        'label' => 'Năm',
        'type' => 'number',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'month',
        'label' => 'Tháng',
        'type' => 'number',
        'required' => true,
      ),
      3 => 
      array (
        'key' => 'expense_group_id',
        'label' => 'Danh mục nhóm chi phí',
        'type' => 'select',
        'option_source' => 'expenseGroups',
      ),
      4 => 
      array (
        'key' => 'expense_category_id',
        'label' => 'Danh mục chi phí',
        'type' => 'select',
        'option_source' => 'expenseCategories',
      ),
      5 => 
      array (
        'key' => 'expense_unit_id',
        'label' => 'Đơn vị tính',
        'type' => 'select',
        'option_source' => 'expenseUnits',
      ),
      6 => 
      array (
        'key' => 'unit_price',
        'label' => 'Đơn giá',
        'type' => 'currency',
      ),
      7 => 
      array (
        'key' => 'quantity',
        'label' => 'Số lượng',
        'type' => 'number',
      ),
      8 => 
      array (
        'key' => 'invoice_number',
        'label' => 'Hóa đơn',
        'type' => 'text',
      ),
      9 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'type' => 'textarea',
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'year',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'month',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'group',
        'format' => 'text',
      ),
      6 => 
      array (
        'key' => 'category',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'unit',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'unit_price',
        'format' => 'currency',
        'align' => 'right',
      ),
      9 => 
      array (
        'key' => 'quantity',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'total',
        'format' => 'currency',
        'align' => 'right',
      ),
      11 => 
      array (
        'key' => 'invoice',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'note',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      14 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '6.2.2' => 
  array (
    'title' => 'Danh mục chi phí',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'group',
        'label' => 'Nhóm chi phí',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'expense_categories',
    'editable' => true,
    'slug' => '6-2-2-danh-muc-chi-phi',
    'component' => 'Page_6_2_2',
    'resource_key' => '6.2.2',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'expense_group_id',
        'label' => 'Nhóm chi phí',
        'type' => 'select',
        'option_source' => 'expenseGroups',
      ),
      1 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'type' => 'text',
        'required' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'group',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      5 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '6.2.3' => 
  array (
    'title' => 'Danh mục nhóm chi phí',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'expense_groups',
    'editable' => true,
    'slug' => '6-2-3-danh-muc-nhom-chi-phi',
    'component' => 'Page_6_2_3',
    'resource_key' => '6.2.3',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'type' => 'text',
        'required' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      4 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '6.2.4' => 
  array (
    'title' => 'Danh mục đơn vị tính',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật',
        'format' => 'datetime',
      ),
    ),
    'source' => 'expense_units',
    'editable' => true,
    'slug' => '6-2-4-danh-muc-don-vi-tinh',
    'component' => 'Page_6_2_4',
    'resource_key' => '6.2.4',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'name',
        'label' => 'Tên',
        'type' => 'text',
        'required' => true,
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'name',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
      4 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '6.3.5' => 
  array (
    'title' => 'Tổng kết kế hoạch tháng',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'account',
        'label' => 'Tài khoản',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'role',
        'label' => 'Chức vụ',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'kpi',
        'label' => 'Tên KPI',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'budget',
        'label' => 'Ngân sách/tháng',
        'format' => 'currency',
      ),
      5 => 
      array (
        'key' => 'clicks',
        'label' => 'Số click/tháng',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'contacts',
        'label' => 'Số contact/tháng',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'revenue_target',
        'label' => 'Doanh số/tháng',
        'format' => 'currency',
      ),
      8 => 
      array (
        'key' => 'actual_revenue',
        'label' => 'Doanh số thực tế/tháng',
        'format' => 'currency',
      ),
      9 => 
      array (
        'key' => 'working_days',
        'label' => 'Số ngày làm việc/tháng',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'actual_days',
        'label' => 'Số ngày làm việc thực tế',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'base_salary',
        'label' => 'Lương cứng',
        'format' => 'currency',
      ),
      12 => 
      array (
        'key' => 'bonus',
        'label' => 'Tiền thưởng (% Doanh số)',
        'format' => 'currency',
      ),
      13 => 
      array (
        'key' => 'income',
        'label' => 'Tổng thu nhập',
        'format' => 'currency',
      ),
      14 => 
      array (
        'key' => 'locked',
        'label' => 'Chốt dữ liệu',
        'format' => 'boolean',
      ),
      15 => 
      array (
        'key' => 'updated_at',
        'label' => 'Cập nhật thực tế',
        'format' => 'datetime',
      ),
    ),
    'source' => 'monthly_plan',
    'editable' => true,
    'kind' => 'report',
    'slug' => '6-3-5-tong-ket-ke-hoach-thang',
    'component' => 'Page_6_3_5',
    'resource_key' => '6.3.5',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'user_id',
        'label' => 'Tài khoản',
        'type' => 'select',
        'option_source' => 'users',
        'required' => true,
      ),
      1 => 
      array (
        'key' => 'year',
        'label' => 'Năm',
        'type' => 'number',
        'required' => true,
      ),
      2 => 
      array (
        'key' => 'month',
        'label' => 'Tháng',
        'type' => 'number',
        'required' => true,
      ),
      3 => 
      array (
        'key' => 'kpi_name',
        'label' => 'Tên KPI',
        'type' => 'text',
      ),
      4 => 
      array (
        'key' => 'budget',
        'label' => 'Ngân sách / tháng',
        'type' => 'currency',
      ),
      5 => 
      array (
        'key' => 'clicks_target',
        'label' => 'Số click / tháng',
        'type' => 'number',
      ),
      6 => 
      array (
        'key' => 'contacts_target',
        'label' => 'Số contact / tháng',
        'type' => 'number',
      ),
      7 => 
      array (
        'key' => 'revenue_target',
        'label' => 'Doanh số / tháng',
        'type' => 'currency',
      ),
      8 => 
      array (
        'key' => 'bonus_percent',
        'label' => 'Tiền thưởng (%)',
        'type' => 'number',
      ),
      9 => 
      array (
        'key' => 'base_salary',
        'label' => 'Lương cứng',
        'type' => 'currency',
      ),
      10 => 
      array (
        'key' => 'working_days',
        'label' => 'Số ngày làm việc',
        'type' => 'number',
      ),
      11 => 
      array (
        'key' => 'actual_days',
        'label' => 'Số ngày thực tế',
        'type' => 'number',
      ),
      12 => 
      array (
        'key' => 'locked',
        'label' => 'Chốt dữ liệu',
        'type' => 'checkbox',
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'account',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'role',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'kpi',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'budget',
        'format' => 'currency',
        'align' => 'right',
      ),
      5 => 
      array (
        'key' => 'clicks',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'contacts',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'revenue_target',
        'format' => 'currency',
        'align' => 'right',
      ),
      8 => 
      array (
        'key' => 'new_contacts_target',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'old_contacts_target',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'new_closed_target',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'old_closed_target',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'actual_revenue',
        'format' => 'currency',
        'align' => 'right',
      ),
      13 => 
      array (
        'key' => 'working_days',
        'format' => 'number',
      ),
      14 => 
      array (
        'key' => 'actual_days',
        'format' => 'number',
      ),
      15 => 
      array (
        'key' => 'base_salary',
        'format' => 'currency',
        'align' => 'right',
      ),
      16 => 
      array (
        'key' => 'bonus',
        'format' => 'currency',
        'align' => 'right',
      ),
      17 => 
      array (
        'key' => 'income',
        'format' => 'currency',
        'align' => 'right',
      ),
      18 => 
      array (
        'key' => 'locked',
        'format' => 'boolean',
      ),
      19 => 
      array (
        'key' => 'updated_at',
        'format' => 'datetime',
      ),
    ),
  ),
  '6.4' => 
  array (
    'title' => 'Danh sách xử lý xuất hóa đơn điện tử',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'id',
        'label' => '#',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'code_type',
        'label' => 'Loại mã đơn',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'order_code',
        'label' => 'Mã đơn',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'process_type',
        'label' => 'Loại xử lý',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'processed_at',
        'label' => 'Ngày xử lý',
        'format' => 'datetime',
      ),
      5 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái xử lý',
        'format' => 'status',
      ),
      6 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'duration_ms',
        'label' => 'Thời gian xử lý(ms)',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'attempts',
        'label' => 'Số lần xử lý',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'completed',
        'label' => 'Trạng thái hoàn thành',
        'format' => 'boolean',
      ),
      10 => 
      array (
        'key' => 'batch_id',
        'label' => 'BatchId',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'created_at',
        'label' => 'Ngày tạo',
        'format' => 'datetime',
      ),
    ),
    'source' => 'electronic_invoice_jobs',
    'editable' => true,
    'slug' => '6-4-danh-sach-xu-ly-xuat-hoa-don-dien-tu',
    'component' => 'Page_6_4',
    'resource_key' => '6.4',
    'form_fields' => 
    array (
      0 => 
      array (
        'key' => 'order_id',
        'label' => 'Mã đơn',
        'type' => 'select',
        'option_source' => 'orders',
      ),
      1 => 
      array (
        'key' => 'code_type',
        'label' => 'Loại mã đơn',
        'type' => 'text',
      ),
      2 => 
      array (
        'key' => 'process_type',
        'label' => 'Loại xử lý',
        'type' => 'text',
      ),
      3 => 
      array (
        'key' => 'processed_at',
        'label' => 'Ngày xử lý',
        'type' => 'datetime-local',
      ),
      4 => 
      array (
        'key' => 'status',
        'label' => 'Trạng thái xử lý',
        'type' => 'select',
        'options' => 
        array (
          'pending' => 'Chờ xử lý',
          'processing' => 'Đang xử lý',
          'success' => 'Thành công',
          'failed' => 'Thất bại',
        ),
      ),
      5 => 
      array (
        'key' => 'note',
        'label' => 'Ghi chú',
        'type' => 'textarea',
      ),
      6 => 
      array (
        'key' => 'duration_ms',
        'label' => 'Thời gian xử lý (ms)',
        'type' => 'number',
      ),
      7 => 
      array (
        'key' => 'attempts',
        'label' => 'Số lần xử lý',
        'type' => 'number',
      ),
      8 => 
      array (
        'key' => 'completed',
        'label' => 'Hoàn thành',
        'type' => 'checkbox',
      ),
      9 => 
      array (
        'key' => 'batch_id',
        'label' => 'BatchId',
        'type' => 'text',
      ),
    ),
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'select',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'id',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'code_type',
        'format' => 'text',
      ),
      3 => 
      array (
        'key' => 'order_code',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'process_type',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'processed_at',
        'format' => 'datetime',
      ),
      6 => 
      array (
        'key' => 'status',
        'format' => 'status',
      ),
      7 => 
      array (
        'key' => 'note',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'duration_ms',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'attempts',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'completed',
        'format' => 'boolean',
      ),
      11 => 
      array (
        'key' => 'batch_id',
        'format' => 'text',
      ),
      12 => 
      array (
        'key' => 'created_at',
        'format' => 'datetime',
      ),
      13 => 
      array (
        'key' => 'actions',
        'format' => 'text',
      ),
    ),
  ),
  '7.1.1' => 
  array (
    'title' => 'Thiết lập KPI theo tháng',
    'columns' => 
    array (
      0 => array ('key' => 'index', 'label' => 'STT', 'format' => 'number'),
      1 => array ('key' => 'account', 'label' => 'Tài khoản', 'format' => 'text'),
      2 => array ('key' => 'role', 'label' => 'Chức vụ', 'format' => 'text'),
      3 => array ('key' => 'kpi', 'label' => 'Tên KPI', 'format' => 'text'),
      4 => array ('key' => 'budget', 'label' => 'Ngân sách / tháng', 'format' => 'currency'),
      5 => array ('key' => 'clicks', 'label' => 'Số click / tháng', 'format' => 'number'),
      6 => array ('key' => 'contacts', 'label' => 'Số contact/ tháng', 'format' => 'number'),
      7 => array ('key' => 'revenue_target', 'label' => 'Doanh số / tháng', 'format' => 'currency'),
      8 => array ('key' => 'bonus_percent', 'label' => 'Tiền thưởng (% Doanh số)', 'format' => 'number'),
      9 => array ('key' => 'base_salary', 'label' => 'Lương cứng', 'format' => 'currency'),
      10 => array ('key' => 'income', 'label' => 'Tổng thu nhập', 'format' => 'currency'),
      11 => array ('key' => 'locked', 'label' => 'Chốt dữ liệu', 'format' => 'boolean'),
      12 => array ('key' => 'updated_at', 'label' => 'Cập nhật', 'format' => 'datetime'),
    ),
    'source' => 'monthly_plan',
    'editable' => true,
    'kind' => 'table',
    'slug' => '7-1-1-ke-hoach-kinh-doanh-thang',
    'component' => 'Page_7_1_1',
    'resource_key' => '7.1.1',
    'template_alias' => '7.1.1',
    'form_fields' => 
    array (
      0 => array ('key' => 'user_id', 'label' => 'Tài khoản', 'type' => 'select', 'option_source' => 'users', 'required' => true),
      1 => array ('key' => 'year', 'label' => 'Năm', 'type' => 'number', 'required' => true),
      2 => array ('key' => 'month', 'label' => 'Tháng', 'type' => 'number', 'required' => true),
      3 => array ('key' => 'kpi_name', 'label' => 'Tên KPI', 'type' => 'text'),
      4 => array ('key' => 'budget', 'label' => 'Ngân sách / tháng', 'type' => 'currency'),
      5 => array ('key' => 'clicks_target', 'label' => 'Số click / tháng', 'type' => 'number'),
      6 => array ('key' => 'contacts_target', 'label' => 'Số contact / tháng', 'type' => 'number'),
      7 => array ('key' => 'revenue_target', 'label' => 'Doanh số / tháng', 'type' => 'currency'),
      8 => array ('key' => 'bonus_percent', 'label' => 'Tiền thưởng (%)', 'type' => 'number'),
      9 => array ('key' => 'base_salary', 'label' => 'Lương cứng', 'type' => 'currency'),
      10 => array ('key' => 'working_days', 'label' => 'Số ngày làm việc', 'type' => 'number'),
      11 => array ('key' => 'actual_days', 'label' => 'Số ngày thực tế', 'type' => 'number'),
      12 => array ('key' => 'locked', 'label' => 'Chốt dữ liệu', 'type' => 'checkbox'),
    ),
  ),
  '7.1.2' => 
  array (
    'title' => 'Lập kế hoạch kinh doanh',
    'columns' => array (),
    'source' => 'annual_business_plan',
    'editable' => true,
    'kind' => 'report',
    'slug' => '7-1-2-lap-ke-hoach-kinh-doanh-nam',
    'component' => 'Page_7_1_2',
    'resource_key' => '7.1.2',
    'template_alias' => '7.1.2',
  ),

  '7.1.3' => 
  array (
    'title' => '(Unit admin) Danh mục KPI',
    'columns' => 
    array (
      0 => array ('key' => 'id', 'label' => 'Id', 'format' => 'number'),
      1 => array ('key' => 'kpi_name', 'label' => 'Tên KPI', 'format' => 'text'),
      2 => array ('key' => 'position_label', 'label' => 'Chức vụ', 'format' => 'text'),
      3 => array ('key' => 'daily_budget', 'label' => 'Ngân sách / ngày', 'format' => 'currency'),
      4 => array ('key' => 'daily_clicks', 'label' => 'Số click / ngày', 'format' => 'number'),
      5 => array ('key' => 'daily_contacts', 'label' => 'Số contact/ ngày', 'format' => 'number'),
      6 => array ('key' => 'daily_revenue', 'label' => 'Doanh số / ngày', 'format' => 'currency'),
      7 => array ('key' => 'daily_new_contacts', 'label' => 'Số contact mới / ngày', 'format' => 'number'),
      8 => array ('key' => 'daily_new_closed', 'label' => 'Chốt đơn mới / ngày', 'format' => 'number'),
      9 => array ('key' => 'daily_old_contacts', 'label' => 'Số contact cũ / ngày', 'format' => 'number'),
      10 => array ('key' => 'daily_old_closed', 'label' => 'Chốt đơn cũ / ngày', 'format' => 'number'),
      11 => array ('key' => 'updated_at', 'label' => 'Cập nhật', 'format' => 'datetime'),
    ),
    'source' => 'kpi_catalog',
    'editable' => true,
    'kind' => 'table',
    'slug' => '7-1-3-danh-muc-kpi',
    'component' => 'Page_7_1_3',
    'resource_key' => '7.1.3',
    'template_alias' => '7.1.3',
    'form_fields' => 
    array (
      0 => array ('key' => 'position_key', 'label' => 'Chức vụ', 'type' => 'select', 'options' => array ('marketing' => 'Marketing', 'sales' => 'Sale'), 'required' => true),
      1 => array ('key' => 'kpi_name', 'label' => 'Tên KPI', 'type' => 'text', 'required' => true),
      2 => array ('key' => 'daily_budget', 'label' => 'Ngân sách / ngày', 'type' => 'currency'),
      3 => array ('key' => 'daily_clicks', 'label' => 'Số click / ngày', 'type' => 'number'),
      4 => array ('key' => 'daily_contacts', 'label' => 'Số contact / ngày', 'type' => 'number'),
      5 => array ('key' => 'daily_revenue', 'label' => 'Doanh số / ngày', 'type' => 'currency'),
      6 => array ('key' => 'daily_new_contacts', 'label' => 'Số contact mới / ngày', 'type' => 'number'),
      7 => array ('key' => 'daily_new_closed', 'label' => 'Chốt đơn mới / ngày', 'type' => 'number'),
      8 => array ('key' => 'daily_old_contacts', 'label' => 'Số contact cũ / ngày', 'type' => 'number'),
      9 => array ('key' => 'daily_old_closed', 'label' => 'Chốt đơn cũ / ngày', 'type' => 'number'),
      10 => array ('key' => 'is_active', 'label' => 'Đang áp dụng', 'type' => 'checkbox'),
      11 => array ('key' => 'sort_order', 'label' => 'Thứ tự', 'type' => 'number'),
    ),
  ),
  '7.1.4' => 
  array (
    'title' => '(UnitAdmin) Thiết lập tiền thưởng theo doanh số',
    'columns' => 
    array (
      0 => array ('key' => 'position_label', 'label' => 'Chức vụ', 'format' => 'text'),
      1 => array ('key' => 'revenue_from', 'label' => 'Doanh số tháng từ', 'format' => 'currency'),
      2 => array ('key' => 'revenue_to', 'label' => 'Doanh số tháng đến nhỏ hơn', 'format' => 'currency'),
      3 => array ('key' => 'bonus_percent', 'label' => '% thưởng theo doanh số', 'format' => 'number'),
      4 => array ('key' => 'bonus_amount', 'label' => 'Tiền thưởng', 'format' => 'currency'),
      5 => array ('key' => 'locked', 'label' => 'Chốt dữ liệu', 'format' => 'boolean'),
      6 => array ('key' => 'updated_at', 'label' => 'Cập nhật', 'format' => 'datetime'),
    ),
    'source' => 'revenue_bonus_rules',
    'editable' => true,
    'kind' => 'table',
    'slug' => '7-1-4-khai-bao-thuong',
    'component' => 'Page_7_1_4',
    'resource_key' => '7.1.4',
    'template_alias' => '7.1.4',
  ),

  '8.5.4' => 
  array (
    'title' => 'Biểu đồ xu hướng',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'period',
        'label' => 'Thời gian',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'value',
        'label' => 'Giá trị',
        'format' => 'currency',
      ),
      2 => 
      array (
        'key' => 'comparison',
        'label' => 'So sánh',
        'format' => 'currency',
      ),
      3 => 
      array (
        'key' => 'change',
        'label' => 'Tăng/giảm',
        'format' => 'percent',
      ),
    ),
    'source' => 'trend',
    'kind' => 'trend',
    'slug' => '8-5-4-bieu-do-xu-huong',
    'component' => 'Page_8_5_4',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'period',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'day_6_value',
        'format' => 'currency',
        'align' => 'right',
      ),
      2 => 
      array (
        'key' => 'day_5_value',
        'format' => 'currency',
        'align' => 'right',
      ),
      3 => 
      array (
        'key' => 'day_4_value',
        'format' => 'currency',
        'align' => 'right',
      ),
      4 => 
      array (
        'key' => 'day_3_value',
        'format' => 'currency',
        'align' => 'right',
      ),
      5 => 
      array (
        'key' => 'day_2_value',
        'format' => 'currency',
        'align' => 'right',
      ),
      6 => 
      array (
        'key' => 'day_1_value',
        'format' => 'currency',
        'align' => 'right',
      ),
      7 => 
      array (
        'key' => 'day_0_value',
        'format' => 'currency',
        'align' => 'right',
      ),
    ),
  ),
  '8.5.5' => 
  array (
    'title' => 'Bảng tổng hợp kết quả chia data trong ngày',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'day',
        'label' => 'Day',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'label' => 'Sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'new_contacts',
        'label' => 'Contact mới',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'duplicate_contacts',
        'label' => 'Contact trùng',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'old_contacts',
        'label' => 'Contact cũ',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'care',
        'label' => 'CSKH',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'manual',
        'label' => 'Thủ công',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'team',
        'label' => 'Team',
        'format' => 'text',
      ),
    ),
    'source' => 'allocation_summary',
    'kind' => 'report',
    'slug' => '8-5-5-bang-tong-hop-ket-qua-chia-data-trong-ngay',
    'component' => 'Page_8_5_5',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'day',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'sale',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'wave_1_quantity',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'wave_1_date',
        'format' => 'text',
      ),
      4 => 
      array (
        'key' => 'wave_1_note',
        'format' => 'text',
      ),
      5 => 
      array (
        'key' => 'wave_2_quantity',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'wave_2_date',
        'format' => 'text',
      ),
      7 => 
      array (
        'key' => 'wave_2_note',
        'format' => 'text',
      ),
      8 => 
      array (
        'key' => 'wave_3_quantity',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'wave_3_date',
        'format' => 'text',
      ),
      10 => 
      array (
        'key' => 'wave_3_note',
        'format' => 'text',
      ),
      11 => 
      array (
        'key' => 'wave_4_quantity',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'wave_4_date',
        'format' => 'text',
      ),
      13 => 
      array (
        'key' => 'wave_4_note',
        'format' => 'text',
      ),
      14 => 
      array (
        'key' => 'wave_5_quantity',
        'format' => 'number',
      ),
      15 => 
      array (
        'key' => 'wave_5_date',
        'format' => 'text',
      ),
      16 => 
      array (
        'key' => 'wave_5_note',
        'format' => 'text',
      ),
      17 => 
      array (
        'key' => 'wave_6_quantity',
        'format' => 'number',
      ),
      18 => 
      array (
        'key' => 'wave_6_date',
        'format' => 'text',
      ),
      19 => 
      array (
        'key' => 'wave_6_note',
        'format' => 'text',
      ),
    ),
  ),
  '8.5.9' => 
  array (
    'title' => 'Power dashboard',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'account',
        'label' => 'Tài khoản',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'contacts',
        'label' => 'Số contact',
        'format' => 'number',
      ),
      2 => 
      array (
        'key' => 'closed',
        'label' => 'Số đơn chốt',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'rate',
        'label' => 'Tỷ lệ chốt',
        'format' => 'percent',
      ),
      4 => 
      array (
        'key' => 'cost_per_contact',
        'label' => 'Giá contact',
        'format' => 'currency',
      ),
      5 => 
      array (
        'key' => 'budget_ratio',
        'label' => 'Ngân sách/DS',
        'format' => 'percent',
      ),
      6 => 
      array (
        'key' => 'revenue',
        'label' => 'Doanh số',
        'format' => 'currency',
      ),
    ),
    'source' => 'power_dashboard',
    'kind' => 'power_dashboard',
    'slug' => '8-5-9-power-dashboard',
    'component' => 'Page_8_5_9',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'section',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'metric',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'total',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'average',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'day_11_value',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'day_11_previous',
        'format' => 'percent',
      ),
      6 => 
      array (
        'key' => 'day_11_average',
        'format' => 'percent',
      ),
      7 => 
      array (
        'key' => 'day_10_value',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'day_10_previous',
        'format' => 'percent',
      ),
      9 => 
      array (
        'key' => 'day_10_average',
        'format' => 'percent',
      ),
      10 => 
      array (
        'key' => 'day_9_value',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'day_9_previous',
        'format' => 'percent',
      ),
      12 => 
      array (
        'key' => 'day_9_average',
        'format' => 'percent',
      ),
      13 => 
      array (
        'key' => 'day_8_value',
        'format' => 'number',
      ),
      14 => 
      array (
        'key' => 'day_8_previous',
        'format' => 'percent',
      ),
      15 => 
      array (
        'key' => 'day_8_average',
        'format' => 'percent',
      ),
      16 => 
      array (
        'key' => 'day_7_value',
        'format' => 'number',
      ),
      17 => 
      array (
        'key' => 'day_7_previous',
        'format' => 'percent',
      ),
      18 => 
      array (
        'key' => 'day_7_average',
        'format' => 'percent',
      ),
      19 => 
      array (
        'key' => 'day_6_value',
        'format' => 'number',
      ),
      20 => 
      array (
        'key' => 'day_6_previous',
        'format' => 'percent',
      ),
      21 => 
      array (
        'key' => 'day_6_average',
        'format' => 'percent',
      ),
      22 => 
      array (
        'key' => 'day_5_value',
        'format' => 'number',
      ),
      23 => 
      array (
        'key' => 'day_5_previous',
        'format' => 'percent',
      ),
      24 => 
      array (
        'key' => 'day_5_average',
        'format' => 'percent',
      ),
      25 => 
      array (
        'key' => 'day_4_value',
        'format' => 'number',
      ),
      26 => 
      array (
        'key' => 'day_4_previous',
        'format' => 'percent',
      ),
      27 => 
      array (
        'key' => 'day_4_average',
        'format' => 'percent',
      ),
      28 => 
      array (
        'key' => 'day_3_value',
        'format' => 'number',
      ),
      29 => 
      array (
        'key' => 'day_3_previous',
        'format' => 'percent',
      ),
      30 => 
      array (
        'key' => 'day_3_average',
        'format' => 'percent',
      ),
      31 => 
      array (
        'key' => 'day_2_value',
        'format' => 'number',
      ),
      32 => 
      array (
        'key' => 'day_2_previous',
        'format' => 'percent',
      ),
      33 => 
      array (
        'key' => 'day_2_average',
        'format' => 'percent',
      ),
      34 => 
      array (
        'key' => 'day_1_value',
        'format' => 'number',
      ),
      35 => 
      array (
        'key' => 'day_1_previous',
        'format' => 'percent',
      ),
      36 => 
      array (
        'key' => 'day_1_average',
        'format' => 'percent',
      ),
      37 => 
      array (
        'key' => 'day_0_value',
        'format' => 'number',
      ),
      38 => 
      array (
        'key' => 'day_0_previous',
        'format' => 'percent',
      ),
      39 => 
      array (
        'key' => 'day_0_average',
        'format' => 'percent',
      ),
    ),
  ),
  '8.5.10' => 
  array (
    'title' => 'Thống kê mua lại',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'metric',
        'label' => 'Chỉ số',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'purchase_1',
        'label' => 'Mua lần 1',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'purchase_2',
        'label' => 'Mua lần 2',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'purchase_3',
        'label' => 'Mua lần 3',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'purchase_n',
        'label' => 'Mua lần n',
        'format' => 'number',
      ),
    ),
    'source' => 'repurchase',
    'kind' => 'report',
    'slug' => '8-5-10-thong-ke-mua-lai',
    'component' => 'Page_8_5_10',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'metric',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'purchase_1',
        'format' => 'number',
      ),
    ),
  ),
  '8.5.11' => 
  array (
    'title' => 'Thống kê mua lại theo số sản phẩm',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'purchase_no',
        'label' => 'Lần mua',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'product_1',
        'label' => 'Mua 1 SP',
        'format' => 'number',
      ),
      2 => 
      array (
        'key' => 'product_2',
        'label' => 'Mua 2 SP',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'product_3',
        'label' => 'Mua 3 SP',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'product_4',
        'label' => 'Mua 4 SP',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'product_5',
        'label' => 'Mua 5 SP',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'product_6',
        'label' => 'Mua 6 SP',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'product_7',
        'label' => 'Mua 7 SP',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'product_8',
        'label' => 'Mua 8 SP',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'product_9',
        'label' => 'Mua 9 SP',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'product_10',
        'label' => 'Mua 10 SP',
        'format' => 'number',
      ),
      11 => 
      array (
        'key' => 'product_11',
        'label' => 'Mua 11 SP',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'product_12',
        'label' => 'Mua 12 SP',
        'format' => 'number',
      ),
      13 => 
      array (
        'key' => 'product_13',
        'label' => 'Mua 13 SP',
        'format' => 'number',
      ),
      14 => 
      array (
        'key' => 'product_14',
        'label' => 'Mua 14 SP',
        'format' => 'number',
      ),
      15 => 
      array (
        'key' => 'product_15',
        'label' => 'Mua 15 SP',
        'format' => 'number',
      ),
      16 => 
      array (
        'key' => 'product_16',
        'label' => 'Mua 16 SP',
        'format' => 'number',
      ),
      17 => 
      array (
        'key' => 'product_17',
        'label' => 'Mua 17 SP',
        'format' => 'number',
      ),
      18 => 
      array (
        'key' => 'product_18',
        'label' => 'Mua 18 SP',
        'format' => 'number',
      ),
      19 => 
      array (
        'key' => 'product_19',
        'label' => 'Mua 19 SP',
        'format' => 'number',
      ),
      20 => 
      array (
        'key' => 'product_20',
        'label' => 'Mua 20 SP',
        'format' => 'number',
      ),
      21 => 
      array (
        'key' => 'product_21',
        'label' => 'Mua 21 SP',
        'format' => 'number',
      ),
      22 => 
      array (
        'key' => 'product_22',
        'label' => 'Mua 22 SP',
        'format' => 'number',
      ),
      23 => 
      array (
        'key' => 'product_23',
        'label' => 'Mua 23 SP',
        'format' => 'number',
      ),
      24 => 
      array (
        'key' => 'product_24',
        'label' => 'Mua 24 SP',
        'format' => 'number',
      ),
      25 => 
      array (
        'key' => 'product_25',
        'label' => 'Mua 25 SP',
        'format' => 'number',
      ),
      26 => 
      array (
        'key' => 'product_26',
        'label' => 'Mua 26 SP',
        'format' => 'number',
      ),
      27 => 
      array (
        'key' => 'product_27',
        'label' => 'Mua 27 SP',
        'format' => 'number',
      ),
      28 => 
      array (
        'key' => 'product_28',
        'label' => 'Mua 28 SP',
        'format' => 'number',
      ),
      29 => 
      array (
        'key' => 'product_29',
        'label' => 'Mua 29 SP',
        'format' => 'number',
      ),
      30 => 
      array (
        'key' => 'product_30',
        'label' => 'Mua 30 SP',
        'format' => 'number',
      ),
    ),
    'source' => 'repurchase_products',
    'kind' => 'report',
    'slug' => '8-5-11-thong-ke-mua-lai-theo-so-san-pham',
    'component' => 'Page_8_5_11',
  ),
  '8.5.15' => 
  array (
    'title' => 'Bảng tổng hợp chia data trong ngày v2',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'day',
        'label' => 'Day',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'user',
        'label' => 'User',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'receive',
        'label' => 'Nhận số',
        'format' => 'boolean',
      ),
      3 => 
      array (
        'key' => 'quota',
        'label' => 'Định mức',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'wave',
        'label' => 'Wave',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'new_contacts',
        'label' => 'Số mới',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'duplicate_new',
        'label' => 'Số mới trùng',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'old_contacts',
        'label' => 'Số khách cũ',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'duplicate_old',
        'label' => 'Số khách cũ trùng',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'care',
        'label' => 'Số CSKH',
        'format' => 'number',
      ),
    ),
    'source' => 'allocation_v2',
    'kind' => 'report',
    'slug' => '8-5-15-bang-tong-hop-chia-data-trong-ngay-v2',
    'component' => 'Page_8_5_15',
  ),
  '8.5.16' => 
  array (
    'title' => 'Báo cáo care đơn',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'label' => 'STT',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'care_user',
        'label' => 'TK vận đơn',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'received',
        'label' => 'Đã nhận',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'care_actions',
        'label' => 'TN care đơn',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'caring',
        'label' => 'Đang care',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'uncared',
        'label' => 'Chưa care',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'success',
        'label' => 'Care thành công',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'returned',
        'label' => 'Hoàn đơn',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'success_rate',
        'label' => 'Tỉ lệ thành công',
        'format' => 'percent',
      ),
      9 => 
      array (
        'key' => 'auto_success',
        'label' => 'Care thành công (Auto)',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'auto_return',
        'label' => 'Hoàn đơn (Auto)',
        'format' => 'number',
      ),
    ),
    'source' => 'care_report',
    'kind' => 'report',
    'template_alias' => '5.5.5',
    'slug' => '8-5-16-bao-cao-care-don',
    'component' => 'Page_8_5_16',
    'display_columns' => 
    array (
      0 => 
      array (
        'key' => 'index',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'care_user',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'today_received',
        'format' => 'number',
      ),
      3 => 
      array (
        'key' => 'today_actions',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'received',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'caring',
        'format' => 'number',
      ),
      6 => 
      array (
        'key' => 'uncared',
        'format' => 'number',
      ),
      7 => 
      array (
        'key' => 'care_actions',
        'format' => 'number',
      ),
      8 => 
      array (
        'key' => 'success',
        'format' => 'number',
      ),
      9 => 
      array (
        'key' => 'returned',
        'format' => 'number',
      ),
      10 => 
      array (
        'key' => 'success_rate',
        'format' => 'percent',
      ),
      11 => 
      array (
        'key' => 'auto_success',
        'format' => 'number',
      ),
      12 => 
      array (
        'key' => 'auto_return',
        'format' => 'number',
      ),
    ),
  ),
  '8.5.17' => 
  array (
    'title' => 'Bảng tổng hợp chia số care đơn trong ngày',
    'columns' => 
    array (
      0 => 
      array (
        'key' => 'day',
        'label' => 'Day',
        'format' => 'text',
      ),
      1 => 
      array (
        'key' => 'user',
        'label' => 'User',
        'format' => 'text',
      ),
      2 => 
      array (
        'key' => 'receive',
        'label' => 'Nhận số',
        'format' => 'boolean',
      ),
      3 => 
      array (
        'key' => 'quota',
        'label' => 'Định mức',
        'format' => 'number',
      ),
      4 => 
      array (
        'key' => 'wave',
        'label' => 'Wave',
        'format' => 'number',
      ),
      5 => 
      array (
        'key' => 'new_contacts',
        'label' => 'Số mới',
        'format' => 'number',
      ),
    ),
    'source' => 'care_allocation_daily',
    'kind' => 'report',
    'slug' => '8-5-17-bang-tong-hop-chia-so-care-don-trong-ngay',
    'component' => 'Page_8_5_17',
  ),

  '1.14.1' => 
  array (
    'title' => 'Quản lý cấu hình hóa đơn',
    'columns' => 
    array (
      array ('key' => 'index', 'label' => 'STT', 'format' => 'number'),
      array ('key' => 'account', 'label' => 'Tài khoản', 'format' => 'text'),
      array ('key' => 'tax_code', 'label' => 'Mã số thuế', 'format' => 'text'),
      array ('key' => 'invoice_template_code', 'label' => 'Ký hiệu mẫu hóa đơn', 'format' => 'text'),
      array ('key' => 'invoice_series', 'label' => 'Dãy ký hiệu hóa đơn', 'format' => 'text'),
      array ('key' => 'business_name', 'label' => 'Tên đăng ký kinh doanh', 'format' => 'text'),
      array ('key' => 'phone', 'label' => 'Số điện thoại', 'format' => 'text'),
      array ('key' => 'email', 'label' => 'Email', 'format' => 'text'),
      array ('key' => 'is_active', 'label' => 'Sử dụng', 'format' => 'boolean'),
      array ('key' => 'updated_at', 'label' => 'Cập nhật', 'format' => 'datetime'),
      array ('key' => 'actions', 'label' => 'Thao tác', 'format' => 'actions'),
    ),
    'source' => 'electronic_invoice_configs',
    'editable' => true,
    'slug' => '1-14-1-danh-sach-cau-hinh-hoa-don',
    'component' => 'Page_1_14_1',
    'resource_key' => '1.14.1',
  ),
);
