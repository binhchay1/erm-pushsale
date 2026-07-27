<?php

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Pushsale\CareDistributionRule;
use App\Models\Pushsale\CompanySubscriptionHistory;
use App\Models\Pushsale\CustomerCareCampaign;
use App\Models\Pushsale\DiscountCodRule;
use App\Models\Pushsale\ElectronicInvoiceJob;
use App\Models\Pushsale\ElectronicInvoiceConfig;
use App\Models\Pushsale\Expense;
use App\Models\Pushsale\ExpenseCategory;
use App\Models\Pushsale\ExpenseGroup;
use App\Models\Pushsale\ExpenseUnit;
use App\Models\Pushsale\LeadDistributionRule;
use App\Models\Pushsale\KpiCatalogItem;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\Pushsale\OperationCategory;
use App\Models\Pushsale\OperationWorkflow;
use App\Models\Pushsale\PhoneBlacklist;
use App\Models\Pushsale\ProductAttribute;
use App\Models\Pushsale\ProductAttributeValue;
use App\Models\Pushsale\ProductCategory;
use App\Models\Pushsale\ReportAccessRule;
use App\Models\Pushsale\SeedingPhoneNumber;
use App\Models\Pushsale\WarehouseIncidentReport;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Pushsale\WorkShift;

$commonAudit = ['created_by_user_id', 'updated_by_user_id'];
return [
    '1.1.2' => [
        'model' => CompanySubscriptionHistory::class,
        'fields' => [
            ['key' => 'payment_code', 'label' => 'Mã thanh toán', 'type' => 'text', 'required' => true],
            ['key' => 'contract_type', 'label' => 'Loại hợp đồng', 'type' => 'select', 'options' => ['Mới', 'Gia hạn', 'Nâng cấp']],
            ['key' => 'description', 'label' => 'Mô tả', 'type' => 'textarea'],
            ['key' => 'amount', 'label' => 'Giá trị', 'type' => 'currency', 'required' => true],
            ['key' => 'paid_at', 'label' => 'Ngày thanh toán', 'type' => 'datetime-local'],
            ['key' => 'duration_months', 'label' => 'Thời gian sử dụng (tháng)', 'type' => 'number'],
            ['key' => 'expires_at', 'label' => 'Thời gian hết hạn', 'type' => 'datetime-local'],
        ],
        'rules' => ['payment_code' => ['required','string','max:255'], 'contract_type' => ['nullable','string','max:100'], 'description' => ['nullable','string'], 'amount' => ['required','integer','min:0'], 'paid_at' => ['nullable','date'], 'duration_months' => ['nullable','integer','min:0'], 'expires_at' => ['nullable','date']],
    ],
    '1.2.3' => [
        'model' => WorkShift::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Ca làm việc', 'type' => 'text', 'required' => true],
            ['key' => 'from_hour', 'label' => 'Từ (h)', 'type' => 'time', 'required' => true],
            ['key' => 'to_hour', 'label' => 'Đến (h)', 'type' => 'time', 'required' => true],
            ['key' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea'],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => ['name' => ['required','string','max:255'], 'from_hour' => ['required','date_format:H:i'], 'to_hour' => ['required','date_format:H:i'], 'note' => ['nullable','string'], 'is_active' => ['boolean']],
    ],
    '1.2.4' => [
        'model' => LeadDistributionRule::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên cấu hình', 'type' => 'text', 'required' => true],
            ['key' => 'number_type', 'label' => 'Kiểu số', 'type' => 'select', 'options' => ['new' => 'Số mới', 'old' => 'Khách cũ', 'care' => 'CSKH'], 'required' => true],
            ['key' => 'recipient_type', 'label' => 'Người nhận', 'type' => 'select', 'options' => ['sales' => 'Sales', 'care' => 'CSKH', 'both' => 'Sales + CSKH'], 'required' => true],
            ['key' => 'allocation_method', 'label' => 'Cách chia', 'type' => 'select', 'options' => ['round_robin' => 'Luân phiên', 'quota' => 'Theo định mức', 'manual' => 'Thủ công'], 'required' => true],
            ['key' => 'product_ids', 'label' => 'Sản phẩm', 'type' => 'multiselect', 'option_source' => 'products'],
            ['key' => 'sale_user_ids', 'label' => 'Sales', 'type' => 'multiselect', 'option_source' => 'sales'],
            ['key' => 'care_user_ids', 'label' => 'CSKH', 'type' => 'multiselect', 'option_source' => 'careUsers'],
            ['key' => 'is_active', 'label' => 'Đang áp dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => ['name' => ['required','string','max:255'], 'number_type' => ['required','string','max:50'], 'recipient_type' => ['required','string','max:50'], 'allocation_method' => ['required','string','max:50'], 'product_ids' => ['nullable','array'], 'product_ids.*' => ['integer'], 'sale_user_ids' => ['nullable','array'], 'sale_user_ids.*' => ['integer'], 'care_user_ids' => ['nullable','array'], 'care_user_ids.*' => ['integer'], 'is_active' => ['boolean']],
    ],
    '1.2.5' => [
        'model' => ReportAccessRule::class,
        'fields' => [
            ['key' => 'user_id', 'label' => 'Tài khoản', 'type' => 'select', 'option_source' => 'users', 'required' => true],
            ['key' => 'team_ids', 'label' => 'Nhóm được xem báo cáo', 'type' => 'multiselect', 'option_source' => 'teams'],
            ['key' => 'team_type', 'label' => 'Kiểu nhóm', 'type' => 'select', 'options' => ['sale' => 'Sales', 'marketing' => 'Marketing', 'warehouse' => 'Kho', 'all' => 'Tất cả']],
            ['key' => 'is_active', 'label' => 'Đang áp dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => ['user_id' => ['required','integer','exists:users,id'], 'team_ids' => ['nullable','array'], 'team_ids.*' => ['integer','exists:teams,id'], 'team_type' => ['nullable','string','max:50'], 'is_active' => ['boolean']],
    ],
    '1.2.6' => [
        'model' => CareDistributionRule::class,
        'fields' => [
            ['key' => 'care_user_id', 'label' => 'User care đơn', 'type' => 'select', 'option_source' => 'careUsers', 'required' => true],
            ['key' => 'quota', 'label' => 'Định mức', 'type' => 'number', 'required' => true],
            ['key' => 'receive_data', 'label' => 'Nhận data', 'type' => 'checkbox', 'default' => true],
            ['key' => 'sale_team_ids', 'label' => 'Nhóm Sales', 'type' => 'multiselect', 'option_source' => 'saleTeams'],
            ['key' => 'warehouse_team_id', 'label' => 'Nhóm vận đơn', 'type' => 'select', 'option_source' => 'warehouseTeams'],
        ],
        'rules' => ['care_user_id' => ['required','integer','exists:users,id'], 'quota' => ['required','integer','min:0'], 'receive_data' => ['boolean'], 'sale_team_ids' => ['nullable','array'], 'sale_team_ids.*' => ['integer','exists:teams,id'], 'warehouse_team_id' => ['nullable','integer','exists:teams,id']],
    ],
    '1.8.1' => [
        'model' => OperationCategory::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên tác nghiệp', 'type' => 'text', 'required' => true],
            ['key' => 'sort_order', 'label' => 'STT', 'type' => 'number'],
            ['key' => 'is_start', 'label' => 'Khởi đầu', 'type' => 'checkbox'],
            ['key' => 'is_pool', 'label' => 'Kho số', 'type' => 'checkbox'],
            ['key' => 'duration_minutes', 'label' => 'Thời lượng (phút)', 'type' => 'number'],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => ['name' => ['required','string','max:255'], 'sort_order' => ['nullable','integer','min:0'], 'is_start' => ['boolean'], 'is_pool' => ['boolean'], 'duration_minutes' => ['nullable','integer','min:0'], 'is_active' => ['boolean']],
    ],
    '1.8.2' => [
        'model' => OperationWorkflow::class,
        'fields' => [
            ['key' => 'from_operation_category_id', 'label' => 'Nếu đang ở tác nghiệp', 'type' => 'select', 'option_source' => 'operationCategories'],
            ['key' => 'condition_type', 'label' => 'Điều kiện', 'type' => 'text'],
            ['key' => 'operation_result', 'label' => 'Kết quả', 'type' => 'text'],
            ['key' => 'to_operation_category_id', 'label' => 'Thì chuyển sang', 'type' => 'select', 'option_source' => 'operationCategories'],
            ['key' => 'delay_minutes', 'label' => 'Sau bao lâu (phút)', 'type' => 'number'],
            ['key' => 'is_active', 'label' => 'Đang áp dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => ['from_operation_category_id' => ['nullable','integer','exists:operation_categories,id'], 'condition_type' => ['nullable','string','max:255'], 'operation_result' => ['nullable','string','max:255'], 'to_operation_category_id' => ['nullable','integer','exists:operation_categories,id'], 'delay_minutes' => ['nullable','integer','min:0'], 'is_active' => ['boolean']],
    ],
    '1.9' => [
        'model' => DiscountCodRule::class,
        'fields' => [
            ['key' => 'rule_type', 'label' => 'Loại cấu hình', 'type' => 'select', 'options' => ['discount' => 'Chiết khấu', 'cod' => 'Phí COD thu của khách'], 'default' => 'discount'],
            ['key' => 'order_from', 'label' => 'Giá trị đơn hàng từ', 'type' => 'currency', 'required' => true],
            ['key' => 'discount_value', 'label' => 'Giá trị chiết khấu / COD', 'type' => 'currency', 'required' => true],
            ['key' => 'calculation_type', 'label' => 'Tính theo', 'type' => 'select', 'options' => ['fixed' => 'Số tiền', 'percent' => 'Phần trăm']],
            ['key' => 'cod_from', 'label' => 'COD từ', 'type' => 'currency'],
            ['key' => 'cod_to', 'label' => 'COD đến', 'type' => 'currency'],
            ['key' => 'is_active', 'label' => 'Đang áp dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => ['rule_type' => ['required','in:discount,cod'], 'order_from' => ['required','integer','min:0'], 'discount_value' => ['required','integer','min:0'], 'calculation_type' => ['required','in:fixed,percent'], 'cod_from' => ['nullable','integer','min:0'], 'cod_to' => ['nullable','integer','min:0'], 'is_active' => ['boolean']],
    ],
    '1.13.1' => [
        'model' => PhoneBlacklist::class,
        'fields' => [
            ['key' => 'phone', 'label' => 'Số blacklist', 'type' => 'tel', 'required' => true],
            ['key' => 'reason', 'label' => 'Lý do', 'type' => 'textarea'],
            ['key' => 'order_id', 'label' => 'Đơn hàng', 'type' => 'select', 'option_source' => 'orders'],
            ['key' => 'creation_type', 'label' => 'Kiểu tạo', 'type' => 'select', 'options' => ['manual' => 'Thủ công', 'warehouse' => 'Kho cảnh báo', 'automatic' => 'Tự động']],
        ],
        'rules' => [
            'phone' => ['required', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'creation_type' => ['required', 'in:manual,warehouse,automatic'],
        ],
        'messages' => [
            'phone.required' => 'Số blacklist bắt buộc.',
            'creation_type.in' => 'Kiểu tạo không hợp lệ.',
            'order_id.exists' => 'Đơn hàng không tồn tại.',
        ],
    ],
    '2.6.4' => [
        'model' => SeedingPhoneNumber::class,
        'fields' => [
            ['key' => 'phone', 'label' => 'Số seeding', 'type' => 'tel', 'required' => true],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => ['phone' => ['required','string','max:32'], 'is_active' => ['boolean']],
    ],
    '3.2' => [
        'model' => CustomerCareCampaign::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên chiến dịch', 'type' => 'text', 'required' => true],
            ['key' => 'customer_condition', 'label' => 'Điều kiện khách hàng', 'type' => 'json'],
            ['key' => 'repeat_days', 'label' => 'Số ngày lặp lại', 'type' => 'number'],
            ['key' => 'starts_at', 'label' => 'Ngày bắt đầu', 'type' => 'date'],
            ['key' => 'ends_at', 'label' => 'Ngày kết thúc', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['draft' => 'Nháp', 'active' => 'Đang chạy', 'paused' => 'Tạm dừng', 'completed' => 'Hoàn thành']],
        ],
        'rules' => ['name' => ['required','string','max:255'], 'customer_condition' => ['nullable'], 'repeat_days' => ['nullable','integer','min:0'], 'starts_at' => ['nullable','date'], 'ends_at' => ['nullable','date','after_or_equal:starts_at'], 'status' => ['required','in:draft,active,paused,completed']],
    ],

    '5.2.1' => [
        'model' => Warehouse::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên kho', 'type' => 'text', 'required' => true],
            ['key' => 'code', 'label' => 'Mã kho', 'type' => 'text'],
            ['key' => 'phone', 'label' => 'Số điện thoại', 'type' => 'tel'],
            ['key' => 'pick_province', 'label' => 'Tỉnh/TP', 'type' => 'text'],
            ['key' => 'pick_district', 'label' => 'Quận/Huyện', 'type' => 'text'],
            ['key' => 'pick_ward', 'label' => 'Phường/Xã', 'type' => 'text'],
            ['key' => 'address', 'label' => 'Địa chỉ lấy hàng', 'type' => 'textarea'],
            ['key' => 'manager_user_id', 'label' => 'Quản lý kho', 'type' => 'select', 'option_source' => 'warehouseUsers'],
            ['key' => 'vtp_code', 'label' => 'Mã Viettel Post', 'type' => 'text'],
            ['key' => 'ghtk_pick_address_id', 'label' => 'Mã địa chỉ GHTK', 'type' => 'text'],
        ],
        'rules' => [
            'name' => ['required','string','max:255'], 'code' => ['nullable','string','max:100'],
            'phone' => ['nullable','string','max:32'], 'pick_province' => ['nullable','string','max:255'],
            'pick_district' => ['nullable','string','max:255'], 'pick_ward' => ['nullable','string','max:255'],
            'address' => ['nullable','string'], 'manager_user_id' => ['nullable','integer','exists:users,id'],
            'vtp_code' => ['nullable','string','max:255'], 'ghtk_pick_address_id' => ['nullable','string','max:255'],
        ],
    ],
    '5.3.1' => [
        'model' => WarehouseVoucher::class,
        'special' => 'warehouse_voucher',
        'fields' => [
            ['key' => 'warehouse_id', 'label' => 'Kho', 'type' => 'select', 'option_source' => 'warehouses', 'required' => true],
            ['key' => 'code', 'label' => 'Mã phiếu', 'type' => 'text', 'required' => true],
            ['key' => 'type', 'label' => 'Loại phiếu', 'type' => 'select', 'options' => ['inbound' => 'Nhập kho', 'outbound' => 'Xuất kho']],
            ['key' => 'document_date', 'label' => 'Ngày chứng từ', 'type' => 'date'],
            ['key' => 'product_id', 'label' => 'Sản phẩm', 'type' => 'select', 'option_source' => 'products', 'required' => true],
            ['key' => 'document_quantity', 'label' => 'SL chứng từ', 'type' => 'number'],
            ['key' => 'quantity', 'label' => 'Số lượng', 'type' => 'number', 'required' => true],
            ['key' => 'unit_cost', 'label' => 'Giá nhập', 'type' => 'currency'],
            ['key' => 'batch_code', 'label' => 'Lô', 'type' => 'text'],
            ['key' => 'expiry_date', 'label' => 'Ngày hết hạn', 'type' => 'date'],
            ['key' => 'location_code', 'label' => 'Mã vị trí', 'type' => 'text'],
            ['key' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea'],
        ],
        'rules' => ['warehouse_id' => ['required','integer','exists:warehouses,id'], 'code' => ['required','string','max:255'], 'type' => ['required','in:inbound,outbound'], 'document_date' => ['nullable','date'], 'product_id' => ['required','integer','exists:products,id'], 'document_quantity' => ['nullable','integer','min:0'], 'quantity' => ['required','integer'], 'unit_cost' => ['nullable','integer','min:0'], 'batch_code' => ['nullable','string','max:255'], 'expiry_date' => ['nullable','date'], 'location_code' => ['nullable','string','max:255'], 'note' => ['nullable','string']],
    ],
    '5.4' => [
        'model' => WarehouseIncidentReport::class,
        'fields' => [
            ['key' => 'manager_user_id', 'label' => 'Tên quản lý', 'type' => 'select', 'option_source' => 'warehouseUsers'],
            ['key' => 'name', 'label' => 'Tên biên bản', 'type' => 'text', 'required' => true],
            ['key' => 'document_date', 'label' => 'Ngày biên bản', 'type' => 'date'],
            ['key' => 'carrier', 'label' => 'Đơn vị giao hàng', 'type' => 'select', 'option_source' => 'shippingProviders', 'required' => true],
            ['key' => 'sender_name', 'label' => 'Bên giao', 'type' => 'text', 'required' => true],
            ['key' => 'receiver_name', 'label' => 'Bên nhận', 'type' => 'text', 'required' => true],
            ['key' => 'order_count', 'label' => 'Số đơn', 'type' => 'number'],
            ['key' => 'product_count', 'label' => 'Số sản phẩm', 'type' => 'number'],
            ['key' => 'status', 'label' => 'Trạng thái', 'type' => 'select', 'options' => ['updating' => 'Đang cập nhật', 'closed' => 'Đã chốt']],
            ['key' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea', 'required' => true],
        ],
        'rules' => ['manager_user_id' => ['nullable','integer','exists:users,id'], 'name' => ['required','string','max:255'], 'document_date' => ['nullable','date'], 'carrier' => ['required','string','max:255'], 'sender_name' => ['required','string','max:255'], 'receiver_name' => ['required','string','max:255'], 'order_count' => ['nullable','integer','min:0'], 'product_count' => ['nullable','integer','min:0'], 'status' => ['required','in:updating,closed,draft,confirmed'], 'note' => ['required','string','max:2000']],
    ],
    '6.2.1' => [
        'model' => Expense::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true],
            ['key' => 'year', 'label' => 'Năm', 'type' => 'number', 'required' => true],
            ['key' => 'month', 'label' => 'Tháng', 'type' => 'number', 'required' => true],
            ['key' => 'expense_group_id', 'label' => 'Danh mục nhóm chi phí', 'type' => 'select', 'option_source' => 'expenseGroups'],
            ['key' => 'expense_category_id', 'label' => 'Danh mục chi phí', 'type' => 'select', 'option_source' => 'expenseCategories'],
            ['key' => 'expense_unit_id', 'label' => 'Đơn vị tính', 'type' => 'select', 'option_source' => 'expenseUnits'],
            ['key' => 'unit_price', 'label' => 'Đơn giá', 'type' => 'currency'],
            ['key' => 'quantity', 'label' => 'Số lượng', 'type' => 'number'],
            ['key' => 'invoice_number', 'label' => 'Hóa đơn', 'type' => 'text'],
            ['key' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea'],
        ],
        'rules' => ['name' => ['required','string','max:255'], 'year' => ['required','integer','min:2000','max:2100'], 'month' => ['required','integer','between:1,12'], 'expense_group_id' => ['nullable','integer','exists:expense_groups,id'], 'expense_category_id' => ['nullable','integer','exists:expense_categories,id'], 'expense_unit_id' => ['nullable','integer','exists:expense_units,id'], 'unit_price' => ['nullable','integer','min:0'], 'quantity' => ['nullable','numeric','min:0'], 'invoice_number' => ['nullable','string','max:255'], 'note' => ['nullable','string']],
    ],
    '6.2.2' => [
        'model' => ExpenseCategory::class,
        'fields' => [['key' => 'expense_group_id', 'label' => 'Nhóm chi phí', 'type' => 'select', 'option_source' => 'expenseGroups'], ['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true]],
        'rules' => ['expense_group_id' => ['nullable','integer','exists:expense_groups,id'], 'name' => ['required','string','max:255']],
    ],
    '6.2.3' => [
        'model' => ExpenseGroup::class,
        'fields' => [['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true]],
        'rules' => ['name' => ['required','string','max:255']],
    ],
    '6.2.4' => [
        'model' => ExpenseUnit::class,
        'fields' => [['key' => 'name', 'label' => 'Tên', 'type' => 'text', 'required' => true]],
        'rules' => ['name' => ['required','string','max:255']],
    ],
    '6.3.5' => [
        'model' => MonthlyKpiPlan::class,
        'fields' => [
            ['key' => 'user_id', 'label' => 'Tài khoản', 'type' => 'select', 'option_source' => 'users', 'required' => true],
            ['key' => 'year', 'label' => 'Năm', 'type' => 'number', 'required' => true],
            ['key' => 'month', 'label' => 'Tháng', 'type' => 'number', 'required' => true],
            ['key' => 'kpi_name', 'label' => 'Tên KPI', 'type' => 'text'],
            ['key' => 'budget', 'label' => 'Ngân sách / tháng', 'type' => 'currency'],
            ['key' => 'clicks_target', 'label' => 'Số click / tháng', 'type' => 'number'],
            ['key' => 'contacts_target', 'label' => 'Số contact / tháng', 'type' => 'number'],
            ['key' => 'revenue_target', 'label' => 'Doanh số / tháng', 'type' => 'currency'],
            ['key' => 'bonus_percent', 'label' => 'Tiền thưởng (%)', 'type' => 'number'],
            ['key' => 'base_salary', 'label' => 'Lương cứng', 'type' => 'currency'],
            ['key' => 'working_days', 'label' => 'Số ngày làm việc', 'type' => 'number'],
            ['key' => 'actual_days', 'label' => 'Số ngày thực tế', 'type' => 'number'],
            ['key' => 'locked', 'label' => 'Chốt dữ liệu', 'type' => 'checkbox'],
        ],
        'rules' => ['user_id' => ['required','integer','exists:users,id'], 'year' => ['required','integer','min:2000','max:2100'], 'month' => ['required','integer','between:1,12'], 'kpi_name' => ['nullable','string','max:255'], 'budget' => ['nullable','integer','min:0'], 'clicks_target' => ['nullable','integer','min:0'], 'contacts_target' => ['nullable','integer','min:0'], 'revenue_target' => ['nullable','integer','min:0'], 'bonus_percent' => ['nullable','numeric','min:0'], 'base_salary' => ['nullable','integer','min:0'], 'working_days' => ['nullable','integer','min:0'], 'actual_days' => ['nullable','integer','min:0'], 'locked' => ['boolean']],
    ],
    '7.1.1' => [
        'model' => MonthlyKpiPlan::class,
        'fields' => [
            ['key' => 'user_id', 'label' => 'Tài khoản', 'type' => 'select', 'option_source' => 'users', 'required' => true],
            ['key' => 'year', 'label' => 'Năm', 'type' => 'number', 'required' => true],
            ['key' => 'month', 'label' => 'Tháng', 'type' => 'number', 'required' => true],
            ['key' => 'kpi_name', 'label' => 'Tên KPI', 'type' => 'text'],
            ['key' => 'budget', 'label' => 'Ngân sách / tháng', 'type' => 'currency'],
            ['key' => 'clicks_target', 'label' => 'Số click / tháng', 'type' => 'number'],
            ['key' => 'contacts_target', 'label' => 'Số contact / tháng', 'type' => 'number'],
            ['key' => 'revenue_target', 'label' => 'Doanh số / tháng', 'type' => 'currency'],
            ['key' => 'bonus_percent', 'label' => 'Tiền thưởng (%)', 'type' => 'number'],
            ['key' => 'base_salary', 'label' => 'Lương cứng', 'type' => 'currency'],
            ['key' => 'working_days', 'label' => 'Số ngày làm việc', 'type' => 'number'],
            ['key' => 'actual_days', 'label' => 'Số ngày thực tế', 'type' => 'number'],
            ['key' => 'locked', 'label' => 'Chốt dữ liệu', 'type' => 'checkbox'],
        ],
        'rules' => ['user_id' => ['required','integer','exists:users,id'], 'year' => ['required','integer','min:2000','max:2100'], 'month' => ['required','integer','between:1,12'], 'kpi_name' => ['nullable','string','max:255'], 'budget' => ['nullable','integer','min:0'], 'clicks_target' => ['nullable','integer','min:0'], 'contacts_target' => ['nullable','integer','min:0'], 'revenue_target' => ['nullable','integer','min:0'], 'bonus_percent' => ['nullable','numeric','min:0'], 'base_salary' => ['nullable','integer','min:0'], 'working_days' => ['nullable','integer','min:0'], 'actual_days' => ['nullable','integer','min:0'], 'locked' => ['boolean']],
    ],

    '7.1.3' => [
        'model' => KpiCatalogItem::class,
        'fields' => [
            ['key' => 'position_key', 'label' => 'Chức vụ', 'type' => 'select', 'options' => ['marketing' => 'Marketing', 'sales' => 'Sale'], 'required' => true],
            ['key' => 'kpi_name', 'label' => 'Tên KPI', 'type' => 'text', 'required' => true],
            ['key' => 'daily_budget', 'label' => 'Ngân sách / ngày', 'type' => 'currency'],
            ['key' => 'daily_clicks', 'label' => 'Số click / ngày', 'type' => 'number'],
            ['key' => 'daily_contacts', 'label' => 'Số contact / ngày', 'type' => 'number'],
            ['key' => 'daily_revenue', 'label' => 'Doanh số / ngày', 'type' => 'currency'],
            ['key' => 'daily_new_contacts', 'label' => 'Số contact mới / ngày', 'type' => 'number'],
            ['key' => 'daily_new_closed', 'label' => 'Chốt đơn mới / ngày', 'type' => 'number'],
            ['key' => 'daily_old_contacts', 'label' => 'Số contact cũ / ngày', 'type' => 'number'],
            ['key' => 'daily_old_closed', 'label' => 'Chốt đơn cũ / ngày', 'type' => 'number'],
            ['key' => 'is_active', 'label' => 'Đang áp dụng', 'type' => 'checkbox', 'default' => true],
            ['key' => 'sort_order', 'label' => 'Thứ tự', 'type' => 'number'],
        ],
        'rules' => [
            'position_key' => ['required','in:marketing,sales'],
            'kpi_name' => ['required','string','max:255'],
            'daily_budget' => ['nullable','integer','min:0'],
            'daily_clicks' => ['nullable','integer','min:0'],
            'daily_contacts' => ['nullable','integer','min:0'],
            'daily_revenue' => ['nullable','integer','min:0'],
            'daily_new_contacts' => ['nullable','integer','min:0'],
            'daily_new_closed' => ['nullable','integer','min:0'],
            'daily_old_contacts' => ['nullable','integer','min:0'],
            'daily_old_closed' => ['nullable','integer','min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable','integer','min:0'],
        ],
    ],
    '6.4' => [
        'model' => ElectronicInvoiceJob::class,
        'fields' => [
            ['key' => 'order_id', 'label' => 'Mã đơn', 'type' => 'select', 'option_source' => 'orders'],
            ['key' => 'code_type', 'label' => 'Loại mã đơn', 'type' => 'text'],
            ['key' => 'process_type', 'label' => 'Loại xử lý', 'type' => 'text'],
            ['key' => 'processed_at', 'label' => 'Ngày xử lý', 'type' => 'datetime-local'],
            ['key' => 'status', 'label' => 'Trạng thái xử lý', 'type' => 'select', 'options' => ['pending' => 'Chờ xử lý', 'processing' => 'Đang xử lý', 'success' => 'Thành công', 'failed' => 'Thất bại']],
            ['key' => 'note', 'label' => 'Ghi chú', 'type' => 'textarea'],
            ['key' => 'duration_ms', 'label' => 'Thời gian xử lý (ms)', 'type' => 'number'],
            ['key' => 'attempts', 'label' => 'Số lần xử lý', 'type' => 'number'],
            ['key' => 'completed', 'label' => 'Hoàn thành', 'type' => 'checkbox'],
            ['key' => 'batch_id', 'label' => 'BatchId', 'type' => 'text'],
        ],
        'rules' => ['order_id' => ['nullable','integer','exists:orders,id'], 'code_type' => ['nullable','string','max:100'], 'process_type' => ['nullable','string','max:100'], 'processed_at' => ['nullable','date'], 'status' => ['required','in:pending,processing,success,failed'], 'note' => ['nullable','string'], 'duration_ms' => ['nullable','integer','min:0'], 'attempts' => ['nullable','integer','min:0'], 'completed' => ['boolean'], 'batch_id' => ['nullable','string','max:255']],
    ],


    '1.11' => [
        'model' => \App\Models\Pushsale\FacebookPageMapping::class,
        'fields' => [
            ['key' => 'page_id', 'label' => 'PageID', 'type' => 'text', 'required' => true],
            ['key' => 'page_name', 'label' => 'Fanpage', 'type' => 'text', 'required' => true],
            ['key' => 'creator_name', 'label' => 'FB Creator', 'type' => 'text'],
            ['key' => 'marketer_user_id', 'label' => 'Marketing', 'type' => 'select', 'option_source' => 'marketers'],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => [
            'page_id' => ['required','string','max:255'],
            'page_name' => ['required','string','max:255'],
            'creator_name' => ['nullable','string','max:255'],
            'marketer_user_id' => ['nullable','integer','exists:users,id'],
            'is_active' => ['boolean'],
        ],
    ],
    '2.4.1' => [
        // Trang này dùng LandingConnectionsController để quản lý đồng thời nguồn, gói sản phẩm
        // và danh sách Sale. Cấu hình tối thiểu dưới đây chỉ là metadata dự phòng.
        'model' => \App\Models\LandingConnection::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên kết nối Landing', 'type' => 'text', 'required' => true],
            ['key' => 'marketer_user_id', 'label' => 'Marketing', 'type' => 'select', 'option_source' => 'marketers'],
            ['key' => 'connection_type', 'label' => 'Loại kết nối', 'type' => 'select', 'options' => ['landing' => 'Landing', 'website' => 'Website', 'facebook' => 'Facebook']],
            ['key' => 'ad_channel', 'label' => 'Kênh quảng cáo', 'type' => 'text'],
            ['key' => 'allocation_method', 'label' => 'Cấu hình chia số', 'type' => 'select', 'options' => ['inherit' => 'Theo cấu hình chung', 'round_robin' => 'Luân phiên', 'priority' => 'Theo ưu tiên', 'manual' => 'Thủ công']],
            ['key' => 'success_url', 'label' => 'URL sau khi hoàn tất', 'type' => 'text'],
            ['key' => 'manual_import', 'label' => 'Cho phép nhập thủ công', 'type' => 'checkbox'],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true],
            ['key' => 'is_approved', 'label' => 'Đã duyệt', 'type' => 'checkbox'],
        ],
        'rules' => [
            'name' => ['required','string','max:255'],
            'marketer_user_id' => ['required','integer','exists:users,id'],
            'connection_type' => ['required','string','max:24'],
            'ad_channel' => ['nullable','string','max:64'],
            'allocation_method' => ['required','string','max:24'],
            'success_url' => ['nullable','url:http,https','max:2048'],
            'manual_import' => ['boolean'],
            'is_active' => ['boolean'],
            'is_approved' => ['boolean'],
        ],
    ],
    '2.4.2' => [
        'model' => \App\Models\MarketingSource::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên website / nguồn kết nối', 'type' => 'text', 'required' => true],
            ['key' => 'marketer_user_id', 'label' => 'Marketing', 'type' => 'select', 'option_source' => 'marketers'],
            ['key' => 'product_id', 'label' => 'Sản phẩm', 'type' => 'select', 'option_source' => 'products'],
            ['key' => 'utm_source', 'label' => 'Tên miền / UTM Source', 'type' => 'text'],
            ['key' => 'utm_campaign', 'label' => 'UTM Campaign', 'type' => 'text'],
            ['key' => 'lead_allocation', 'label' => 'Cấu hình chia số', 'type' => 'select', 'options' => ['round_robin' => 'Luân phiên', 'priority' => 'Theo ưu tiên', 'manual' => 'Thủ công']],
            ['key' => 'js_tracking_enabled', 'label' => 'Bật tracking JS', 'type' => 'checkbox', 'default' => true],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true],
            ['key' => 'is_approved', 'label' => 'Đã duyệt', 'type' => 'checkbox'],
        ],
        'rules' => [
            'name' => ['required','string','max:255'],
            'marketer_user_id' => ['nullable','integer','exists:users,id'],
            'product_id' => ['nullable','integer','exists:products,id'],
            'utm_source' => ['nullable','string','max:255'],
            'utm_campaign' => ['nullable','string','max:255'],
            'lead_allocation' => ['nullable','string','max:50'],
            'js_tracking_enabled' => ['boolean'],
            'is_active' => ['boolean'],
            'is_approved' => ['boolean'],
        ],
        'defaults' => ['ad_channel' => 'website'],
    ],
    '2.6.3' => [
        'model' => \App\Models\Pushsale\PartnerConnection::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên đơn vị đối tác', 'type' => 'text', 'required' => true],
            ['key' => 'partner_type', 'label' => 'Loại kết nối', 'type' => 'select', 'options' => ['api' => 'API', 'webhook' => 'Webhook', 'landing' => 'Landing page']],
            ['key' => 'endpoint_url', 'label' => 'Đường link', 'type' => 'text'],
            ['key' => 'access_token', 'label' => 'Token kết nối', 'type' => 'textarea'],
            ['key' => 'marketing_source_id', 'label' => 'Nguồn dữ liệu', 'type' => 'select', 'option_source' => 'sources'],
            ['key' => 'marketer_user_id', 'label' => 'Marketing', 'type' => 'select', 'option_source' => 'marketers'],
            ['key' => 'product_id', 'label' => 'Sản phẩm', 'type' => 'select', 'option_source' => 'products'],
            ['key' => 'ad_channel', 'label' => 'Kênh quảng cáo', 'type' => 'text'],
            ['key' => 'sale_priority', 'label' => 'Ưu tiên sale', 'type' => 'select', 'options' => ['round_robin' => 'Luân phiên', 'priority' => 'Theo ưu tiên', 'manual' => 'Thủ công']],
            ['key' => 'manual_import', 'label' => 'Nhập thủ công', 'type' => 'checkbox'],
            ['key' => 'is_approved', 'label' => 'Đã duyệt', 'type' => 'checkbox'],
            ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => [
            'name' => ['required','string','max:255'],
            'partner_type' => ['required','in:api,webhook,landing'],
            'endpoint_url' => ['nullable','url','max:2000'],
            'access_token' => ['nullable','string','max:10000'],
            'marketing_source_id' => ['nullable','integer','exists:marketing_sources,id'],
            'marketer_user_id' => ['nullable','integer','exists:users,id'],
            'product_id' => ['nullable','integer','exists:products,id'],
            'ad_channel' => ['nullable','string','max:100'],
            'sale_priority' => ['nullable','string','max:50'],
            'manual_import' => ['boolean'],
            'is_approved' => ['boolean'],
            'is_active' => ['boolean'],
        ],
    ],

    // Dialog phụ của trang sản phẩm là tài nguyên nghiệp vụ riêng, không phải module JSON dùng chung.
    '1.3.1:product' => [
        'model' => Product::class,
        'fields' => [
            ['key' => 'name', 'label' => 'Tên sản phẩm', 'type' => 'text', 'required' => true],
            ['key' => 'sku', 'label' => 'Mã sản phẩm', 'type' => 'text'],
            ['key' => 'unit', 'label' => 'Đơn vị tính', 'type' => 'text'],
            ['key' => 'cost_price', 'label' => 'Giá vốn', 'type' => 'currency'],
            ['key' => 'unit_price', 'label' => 'Đơn giá', 'type' => 'currency', 'required' => true],
            ['key' => 'vat_percent', 'label' => 'VAT (%)', 'type' => 'number'],
            ['key' => 'vat_code', 'label' => 'Mã VAT', 'type' => 'text'],
            ['key' => 'weight_grams', 'label' => 'Khối lượng (gram)', 'type' => 'number'],
            ['key' => 'category_ids', 'label' => 'Phân loại', 'type' => 'multiselect', 'option_source' => 'productCategories'],
            ['key' => 'attribute_value_ids', 'label' => 'Thuộc tính', 'type' => 'multiselect', 'option_source' => 'productAttributeValues'],
            ['key' => 'available_marketing', 'label' => 'Marketing', 'type' => 'checkbox', 'default' => true],
            ['key' => 'available_sale', 'label' => 'Sale', 'type' => 'checkbox', 'default' => true],
            ['key' => 'available_care', 'label' => 'Care', 'type' => 'checkbox', 'default' => true],
            ['key' => 'is_active', 'label' => 'Đang kinh doanh', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => ['name' => ['required','string','max:255'], 'sku' => ['nullable','string','max:255'], 'unit' => ['nullable','string','max:50'], 'cost_price' => ['nullable','integer','min:0'], 'unit_price' => ['required','integer','min:0'], 'vat_percent' => ['nullable','numeric','min:0','max:100'], 'vat_code' => ['nullable','string','max:100'], 'weight_grams' => ['nullable','integer','min:0'], 'category_ids' => ['nullable','array'], 'category_ids.*' => ['integer','exists:product_categories,id'], 'attribute_value_ids' => ['nullable','array'], 'attribute_value_ids.*' => ['integer','exists:product_attribute_values,id'], 'available_marketing' => ['boolean'], 'available_sale' => ['boolean'], 'available_care' => ['boolean'], 'is_active' => ['boolean']],
        'defaults' => ['type' => 'product'],
    ],
    '1.3.1:category' => [
        'model' => ProductCategory::class,
        'fields' => [['key' => 'name', 'label' => 'Tên phân loại', 'type' => 'text', 'required' => true], ['key' => 'sort_order', 'label' => 'STT', 'type' => 'number'], ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true]],
        'rules' => ['name' => ['required','string','max:255'], 'sort_order' => ['nullable','integer','min:0'], 'is_active' => ['boolean']],
    ],
    '1.3.1:attribute' => [
        'model' => ProductAttribute::class,
        'fields' => [['key' => 'name', 'label' => 'Tên thuộc tính', 'type' => 'text', 'required' => true], ['key' => 'is_active', 'label' => 'Đang sử dụng', 'type' => 'checkbox', 'default' => true]],
        'rules' => ['name' => ['required','string','max:255'], 'is_active' => ['boolean']],
    ],
    '1.3.1:attribute-value' => [
        'model' => ProductAttributeValue::class,
        'fields' => [['key' => 'product_attribute_id', 'label' => 'Thuộc tính', 'type' => 'select', 'option_source' => 'productAttributes', 'required' => true], ['key' => 'name', 'label' => 'Tên giá trị', 'type' => 'text', 'required' => true], ['key' => 'sort_order', 'label' => 'STT', 'type' => 'number']],
        'rules' => ['product_attribute_id' => ['required','integer','exists:product_attributes,id'], 'name' => ['required','string','max:255'], 'sort_order' => ['nullable','integer','min:0']],
    ],
    '1.3.2' => [
        'model' => Product::class,
        'special' => 'combo',
        'fields' => [
            ['key' => 'name', 'label' => 'Tên combo', 'type' => 'text', 'required' => true],
            ['key' => 'sku', 'label' => 'Mã combo', 'type' => 'text'],
            ['key' => 'unit_price', 'label' => 'Giá combo', 'type' => 'currency', 'required' => true],
            ['key' => 'component_product_ids', 'label' => 'Sản phẩm trong combo', 'type' => 'multiselect', 'option_source' => 'products'],
            ['key' => 'component_items', 'label' => 'Chi tiết sản phẩm trong combo', 'type' => 'combo-items'],
            ['key' => 'is_active', 'label' => 'Đang áp dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => [
            'name' => ['required','string','max:255'],
            'sku' => ['required','string','max:255'],
            'unit_price' => ['required','integer','min:0'],
            'component_product_ids' => ['nullable','array'],
            'component_product_ids.*' => ['integer','exists:products,id'],
            'component_items' => ['nullable','array'],
            'component_items.*.product_id' => ['required_with:component_items','integer','exists:products,id'],
            'component_items.*.quantity' => ['nullable','integer','min:1'],
            'component_items.*.unit_price' => ['nullable','integer','min:0'],
            'is_active' => ['boolean'],
        ],
        'defaults' => ['type' => 'combo'],
    ],

    '1.14.1' => [
        'model' => ElectronicInvoiceConfig::class,
        'fields' => [
            ['key' => 'account', 'label' => 'Tài khoản', 'type' => 'text', 'required' => true],
            ['key' => 'password', 'label' => 'Mật khẩu', 'type' => 'text'],
            ['key' => 'invoice_type_code', 'label' => 'Mã loại hóa đơn', 'type' => 'text'],
            ['key' => 'tax_code', 'label' => 'Mã số thuế', 'type' => 'text', 'required' => true],
            ['key' => 'invoice_template_code', 'label' => 'Mã mẫu hóa đơn', 'type' => 'text'],
            ['key' => 'invoice_series', 'label' => 'Ký hiệu hóa đơn', 'type' => 'text'],
            ['key' => 'business_name', 'label' => 'Tên doanh nghiệp', 'type' => 'text'],
            ['key' => 'address', 'label' => 'Địa chỉ', 'type' => 'textarea'],
            ['key' => 'phone', 'label' => 'Điện thoại', 'type' => 'text'],
            ['key' => 'fax', 'label' => 'Số fax', 'type' => 'text'],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email'],
            ['key' => 'bank_name', 'label' => 'Tên ngân hàng', 'type' => 'text'],
            ['key' => 'bank_account', 'label' => 'Tài khoản ngân hàng', 'type' => 'text'],
            ['key' => 'is_active', 'label' => 'Sử dụng', 'type' => 'checkbox', 'default' => true],
        ],
        'rules' => [
            'account' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'invoice_type_code' => ['required', 'string', 'max:100'],
            'tax_code' => ['required', 'string', 'max:50'],
            'invoice_template_code' => ['required', 'string', 'max:100'],
            'invoice_series' => ['required', 'string', 'max:100'],
            'business_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ],
        'messages' => [
            'account.required' => 'Tài khoản bắt buộc.',
            'tax_code.required' => 'Mã số thuế bắt buộc.',
            'email.email' => 'Email không hợp lệ.',
            'phone.required' => 'Điện thoại bắt buộc.',
            'business_name.required' => 'Tên doanh nghiệp bắt buộc.',
            'address.required' => 'Địa chỉ bắt buộc.',
        ],
    ],
];
