<?php

return [
    [
        'title' => '1. Quản trị đơn vị',
        'icon' => 'cog',
        'children' => [
            [
                'title' => '1.1 Thông tin đơn vị',
                'children' => [
                    [
                        'title' => '1.1.1 Thông tin',
                        'url' => '/settings',
                    ],
                    [
                        'title' => '1.1.2 Lịch sử đăng ký gói dịch vụ',
                        'disabled' => true,
                    ],
                ],
            ],
            [
                'title' => '1.2 Nhân sự',
                'children' => [
                    [
                        'title' => '1. Danh sách nhân viên',
                        'url' => '/admin/users',
                        'area' => 'hr',
                    ],
                    [
                        'title' => '2. Quản lý đội nhóm',
                        'url' => '/admin/teams',
                        'area' => 'hr',
                    ],
                    [
                        'title' => '3. Ca làm việc',
                        'disabled' => true,
                    ],
                    [
                        'title' => '4. Cấu hình chia số',
                        'url' => '/admin/leads',
                        'area' => 'leads',
                    ],
                    [
                        'title' => '5. Cấu hình tài khoản xem báo cáo',
                        'url' => '/settings',
                    ],
                    [
                        'title' => '6. Cấu hình chia số care đơn',
                        'url' => '/admin/leads',
                        'area' => 'leads',
                    ],
                ],
            ],
            [
                'title' => '1.3 Sản phẩm',
                'children' => [
                    [
                        'title' => '1. Danh sách sản phẩm',
                        'url' => '/admin/products',
                        'area' => 'products',
                    ],
                    [
                        'title' => '2. Quản lý combo',
                        'url' => '/admin/products',
                        'area' => 'products',
                    ],
                ],
            ],
            [
                'title' => '1.4 Kết nối giao hàng',
                'url' => '/admin/shipping-partners',
                'area' => 'shipping',
            ],
            [
                'title' => '1.5 Phân bổ data',
                'url' => '/admin/leads',
                'area' => 'leads',
            ],
            [
                'title' => '1.6 Cấu hình chức năng',
                'url' => '/settings',
            ],
            [
                'title' => '1.7 Bảo mật',
                'children' => [
                    [
                        'title' => '1. Lịch sử đăng nhập',
                        'url' => '/admin/activity-logs',
                    ],
                    [
                        'title' => '2. Quản lý đăng nhập',
                        'url' => '/admin/users',
                        'area' => 'hr',
                    ],
                    [
                        'title' => '3. Lịch sử lọc data chốt đơn',
                        'url' => '/admin/activity-logs',
                    ],
                ],
            ],
            [
                'title' => '1.8 Thiết lập quy trình sale',
                'children' => [
                    [
                        'title' => '1. Khai báo danh mục tác nghiệp',
                        'url' => '/settings',
                    ],
                    [
                        'title' => '2. Thiết lập luồng tác nghiệp',
                        'url' => '/settings',
                    ],
                ],
            ],
            [
                'title' => '1.9 Thiết lập chiết khấu, COD',
                'url' => '/settings',
            ],
            [
                'title' => '1.10 Import excel',
                'url' => '/admin/leads',
                'area' => 'leads',
            ],
            [
                'title' => '1.11 Cấu hình Facebook đơn vị',
                'url' => '/admin/integrations',
                'area' => 'connections',
            ],
            [
                'title' => '1.13 Tiện ích',
                'children' => [
                    [
                        'title' => '1. Quản lý số blacklist',
                        'disabled' => true,
                    ],
                ],
            ],
            [
                'title' => '1.14 Quản lý hóa đơn điện tử',
                'children' => [
                    [
                        'title' => '1. Danh sách cấu hình',
                        'url' => '/settings',
                    ],
                ],
            ],
            [
                'title' => '1.15 Thương mại điện tử',
                'children' => [
                    [
                        'title' => '1. Danh sách kết nối cửa hàng',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                    [
                        'title' => '2. Danh sách kết nối sản phẩm',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                    [
                        'title' => '3. Danh sách đơn hàng lỗi',
                        'url' => '/admin/orders/failed',
                    ],
                ],
            ],
        ],
    ],
    [
        'title' => '2. Marketing',
        'icon' => 'trophy',
        'children' => [
            [
                'title' => '2.1 Marketing dashboard',
                'url' => '/admin/marketing/dashboard',
                'area' => 'marketing',
            ],
            [
                'title' => '2.2 Bảng xếp hạng',
                'url' => '/admin/rankings',
            ],
            [
                'title' => '2.3 Hồ sơ khách hàng',
                'url' => '/admin/customers',
                'area' => 'customers',
            ],
            [
                'title' => '2.4 Kết nối landing - website',
                'children' => [
                    [
                        'title' => '1. Kết nối landing',
                        'url' => '/admin/landing-approvals',
                    ],
                    [
                        'title' => '2. Kết nối website',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                ],
            ],
            [
                'title' => '2.5 Kết nối facebook',
                'area' => 'connections',
                'children' => [
                    [
                        'title' => '1. Tạo nguồn dữ liệu',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                    [
                        'title' => '2. Kết nối Fanpage Facebook',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                    [
                        'title' => '3. Danh sách bài post Facebook',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                ],
            ],
            [
                'title' => '2.6 Tiện ích',
                'children' => [
                    [
                        'title' => '1. Import excel',
                        'url' => '/admin/leads',
                        'area' => 'leads',
                    ],
                    [
                        'title' => '2. Nhập data thủ công',
                        'url' => '/admin/leads',
                        'area' => 'leads',
                    ],
                    [
                        'title' => '3. Kết nối phần mềm thứ 3',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                    [
                        'title' => '4. Quản lý số seeding',
                        'disabled' => true,
                    ],
                ],
            ],
            [
                'title' => '2.7 Báo cáo',
                'children' => [
                    [
                        'title' => '1. Báo cáo doanh số marketing',
                        'url' => '/admin/reports/extra/marketing-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Báo cáo doanh số',
                        'url' => '/admin/marketing/revenue',
                        'area' => 'marketing',
                    ],
                    [
                        'title' => '3. Báo cáo doanh số V2',
                        'url' => '/admin/marketing/revenue',
                        'area' => 'marketing',
                    ],
                    [
                        'title' => '4. CEO Dashboard V2',
                        'url' => '/admin/reports/ceo',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '5. Báo cáo công việc',
                        'url' => '/admin/reports/extra/marketing-3',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '6. Báo cáo kinh doanh hệ thống',
                        'url' => '/admin/reports/extra/kho-2',
                        'area' => 'reports',
                    ],
                ],
            ],
            [
                'title' => '2.8. Marketing Leader',
                'children' => [
                    [
                        'title' => '1. Thống kê trưởng nhóm',
                        'url' => '/admin/reports/team-leaders',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2 . Báo cáo công việc',
                        'url' => '/admin/reports/extra/marketing-3',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Báo cáo up sale',
                        'url' => '/admin/reports/extra/marketing-4',
                        'area' => 'reports',
                    ],
                ],
            ],
            [
                'title' => '2.9 Thương mại điện tử',
                'children' => [
                    [
                        'title' => '1. Danh sách kết nối cửa hàng',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                    [
                        'title' => '2. Danh sách kết nối sản phẩm',
                        'url' => '/admin/integrations',
                        'area' => 'connections',
                    ],
                    [
                        'title' => '3. Danh sách đơn hàng lỗi',
                        'url' => '/admin/orders/failed',
                    ],
                ],
            ],
        ],
    ],
    [
        'title' => '3. Khách hàng 360',
        'area' => 'customers',
        'icon' => 'user',
        'children' => [
            [
                'title' => '3.1 Quản lý khách hàng',
                'url' => '/admin/customers',
                'area' => 'customers',
            ],
            [
                'title' => '3.2 Chiến dịch chăm sóc',
                'disabled' => true,
            ],
            [
                'title' => '3.3 Báo cáo',
                'children' => [
                    [
                        'title' => '1. Thống kê khách hàng đa chiều',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Thống kê khách hàng chi trả',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                ],
            ],
        ],
    ],
    [
        'title' => '4. Telesale',
        'icon' => 'tty',
        'children' => [
            [
                'title' => '4.1 Tác nghiệp telesale',
                'url' => '/admin/sales/workspace',
                'area' => 'reports',
            ],
            [
                'title' => '4.2 Hồ sơ khách hàng',
                'url' => '/admin/customers',
                'area' => 'customers',
            ],
            [
                'title' => '4.3 Bảng xếp hạng',
                'url' => '/admin/rankings',
            ],
            [
                'title' => '4.4 Kho số thả nổi',
                'disabled' => true,
            ],
            [
                'title' => '4.5 Báo cáo',
                'children' => [
                    [
                        'title' => '1. Sale KPI',
                        'url' => '/admin/reports/extra/sale-4',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Bảng tổng hợp chốt đơn',
                        'url' => '/admin/reports/extra/sale-2',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Báo cáo công việc sale',
                        'url' => '/admin/reports/extra/sale-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '4. Báo cáo doanh số sale',
                        'url' => '/admin/sales/revenue',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '5. Báo cáo doanh số',
                        'url' => '/admin/sales/revenue',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '6. Báo cáo doanh số V2',
                        'url' => '/admin/sales/revenue',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '7. CEO dashboard V2',
                        'url' => '/admin/reports/ceo',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '8. Báo cáo lịch hẹn telesales',
                        'url' => '/admin/reports/extra/sale-5',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '9. Báo cáo kinh doanh hệ thống',
                        'url' => '/admin/reports/extra/kho-2',
                        'area' => 'reports',
                    ],
                ],
            ],
            [
                'title' => '4.6 Báo cáo Leader',
                'children' => [
                    [
                        'title' => '1. Thống kê tỉ lệ chốt đơn',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Thống kê công việc sale',
                        'url' => '/admin/sales/performance',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Thống kê nhóm',
                        'url' => '/admin/reports/team-leaders',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '4. Báo cáo data sale',
                        'disabled' => true,
                    ],
                    [
                        'title' => '5. Tối ưu sale',
                        'disabled' => true,
                    ],
                ],
            ],
        ],
    ],
    [
        'title' => '5. Kho',
        'icon' => 'cubes',
        'children' => [
            [
                'title' => '5.1 Đăng đơn',
                'url' => '/admin/warehouse/operations',
                'area' => 'warehouse',
            ],
            [
                'title' => '5.2 Quản lý kho',
                'area' => 'warehouse',
                'children' => [
                    [
                        'title' => '1. Danh sách kho',
                        'url' => '/admin/warehouse/inventory',
                        'area' => 'warehouse',
                    ],
                    [
                        'title' => '2. Danh sách sản phẩm kho',
                        'url' => '/admin/warehouse/inventory',
                        'area' => 'warehouse',
                    ],
                ],
            ],
            [
                'title' => '5.3 Nhập, xuất kho',
                'children' => [
                    [
                        'title' => '1. Phiếu xuất / nhập kho',
                        'disabled' => true,
                    ],
                    [
                        'title' => '2. Danh sách phiếu xuất / nhập kho',
                        'disabled' => true,
                    ],
                    [
                        'title' => '3. Lịch sử nhập, xuất kho',
                        'url' => '/admin/warehouse/movements',
                        'area' => 'warehouse',
                    ],
                ],
            ],
            [
                'title' => '5.4 Quản lý biên bản',
                'disabled' => true,
            ],
            [
                'title' => '5.5 Báo cáo',
                'children' => [
                    [
                        'title' => '1. Báo cáo nhập, xuất theo ngày',
                        'url' => '/admin/reports/extra/kho-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Bảng tổng hợp chờ xuất theo ngày',
                        'url' => '/admin/reports/extra/kho-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Báo cáo kinh doanh hệ thống',
                        'url' => '/admin/reports/extra/kho-2',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '4. Báo cáo tổng hợp phát sinh kho',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '5. Báo cáo care đơn',
                        'disabled' => true,
                    ],
                    [
                        'title' => '6. Báo cáo sửa số giao hàng',
                        'disabled' => true,
                    ],
                    [
                        'title' => '7. Tổng hợp trạng thái giao hàng theo TK vận đơn',
                        'disabled' => true,
                    ],
                    [
                        'title' => '8. Báo cáo care đơn tác nghiệp',
                        'disabled' => true,
                    ],
                ],
            ],
            [
                'title' => '5.7 Thương mại điện tử',
                'children' => [
                    [
                        'title' => '1. Danh sách đơn hàng lỗi',
                        'url' => '/admin/orders/failed',
                    ],
                ],
            ],
            [
                'title' => '5.8 Cấu hình care đơn',
                'children' => [
                    [
                        'title' => '1. Cấu hình chia số care đơn',
                        'url' => '/admin/leads',
                        'area' => 'leads',
                    ],
                    [
                        'title' => '2. Phân bổ data care đơn',
                        'disabled' => true,
                    ],
                ],
            ],
        ],
    ],
    [
        'title' => '6. Kế toán',
        'icon' => 'calculator',
        'children' => [
            [
                'title' => '6.1 Đối soát đơn',
                'url' => '/admin/accounting',
                'area' => 'accounting',
            ],
            [
                'title' => '6.2 Quản lý chi phí đơn vị',
                'children' => [
                    [
                        'title' => '1. Nhập chi phí',
                        'disabled' => true,
                    ],
                    [
                        'title' => '2. Khai báo danh mục chi phí',
                        'url' => '/settings',
                    ],
                    [
                        'title' => '3. Khai báo nhóm chi phí',
                        'url' => '/settings',
                    ],
                    [
                        'title' => '4. Khai báo đơn vị tính',
                        'url' => '/settings',
                    ],
                ],
            ],
            [
                'title' => '6.3 Báo cáo',
                'children' => [
                    [
                        'title' => '1. CEO dashboard',
                        'url' => '/admin/reports/ceo',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Báo cáo doanh số',
                        'url' => '/admin/reports/extra/kho-2',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Báo cáo doanh số V2',
                        'url' => '/admin/reports/extra/kho-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '4. Báo cáo kinh doanh',
                        'url' => '/admin/reports/extra/kho-2',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '5. Tổng kết lương tháng',
                        'disabled' => true,
                    ],
                    [
                        'title' => '6. Báo cáo doanh số chi tiết',
                        'url' => '/admin/sales/revenue',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '7. CEO dashboard V2',
                        'url' => '/admin/reports/ceo',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '8. Báo cáo kinh doanh hệ thống',
                        'url' => '/admin/reports/extra/kho-2',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '9. Báo cáo tỉ lệ chốt đơn sản phẩm',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                ],
            ],
            [
                'title' => '6.4 Danh sách xử lý HĐĐT',
                'disabled' => true,
            ],
        ],
    ],
    [
        'title' => '7. CEO',
        'icon' => 'user-secret',
        'children' => [
            [
                'title' => '7.1 Kế hoạch kinh doanh',
                'children' => [
                    [
                        'title' => '1. Kế hoạch kinh doanh tháng',
                        'url' => '/settings',
                    ],
                    [
                        'title' => '2. Lập kế hoạch kinh doanh năm',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Danh mục KPI',
                        'url' => '/settings',
                    ],
                    [
                        'title' => '4. Khai báo thưởng',
                        'url' => '/settings',
                    ],
                ],
            ],
        ],
    ],
    [
        'title' => '8. Báo cáo thống kê',
        'icon' => 'dashboard',
        'children' => [
            [
                'title' => '8.1 Marketing',
                'children' => [
                    [
                        'title' => '1. Biểu đồ thống kê theo khung giờ',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Báo cáo doanh số marketing',
                        'url' => '/admin/reports/extra/marketing-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Báo cáo up sale',
                        'url' => '/admin/reports/extra/marketing-4',
                        'area' => 'reports',
                    ],
                ],
            ],
            [
                'title' => '8.2 Sale',
                'children' => [
                    [
                        'title' => '1. Báo cáo công việc sale',
                        'url' => '/admin/reports/extra/sale-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Báo cáo doanh số sale',
                        'url' => '/admin/sales/revenue',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Báo cáo lịch hẹn telesales',
                        'url' => '/admin/reports/extra/sale-5',
                        'area' => 'reports',
                    ],
                ],
            ],
            [
                'title' => '8.3 Kho',
                'children' => [
                    [
                        'title' => '1. Báo cáo nhập, xuất theo ngày',
                        'url' => '/admin/reports/extra/kho-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. Bảng tổng hợp chờ xuất theo ngày',
                        'url' => '/admin/reports/extra/kho-1',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Báo cáo giá vốn sản phẩm',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '4. Báo cáo kinh doanh hệ thống',
                        'url' => '/admin/reports/extra/kho-2',
                        'area' => 'reports',
                    ],
                ],
            ],
            [
                'title' => '8.4 Kế toán',
                'children' => [
                    [
                        'title' => '1. Báo cáo kinh doanh',
                        'url' => '/admin/reports/extra/kho-2',
                        'area' => 'reports',
                    ],
                ],
            ],
            [
                'title' => '8.5 Quản trị',
                'children' => [
                    [
                        'title' => '1. CEO dashboard',
                        'url' => '/admin/reports/ceo',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '2. CEO dashboard V2',
                        'url' => '/admin/reports/ceo',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '3. Phong thần bảng',
                        'url' => '/admin/rankings',
                    ],
                    [
                        'title' => '4. Biểu đồ xu hướng',
                        'disabled' => true,
                    ],
                    [
                        'title' => '5. Bảng tổng hợp chia data',
                        'disabled' => true,
                    ],
                    [
                        'title' => '6. Báo cáo biểu đồ',
                        'disabled' => true,
                    ],
                    [
                        'title' => '7. Báo cáo doanh số',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '8. Báo cáo doanh số V2',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '9. Power dashboard',
                        'disabled' => true,
                    ],
                    [
                        'title' => '10. Thống kê khách hàng mua lại',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '11. Thống kê KH mua lại theo số sp',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '12. Thống kê KH mua lại theo sản phẩm',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '13. Báo cáo thao tác nhập số',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '14. Báo cáo tỉ lệ chốt đơn sản phẩm',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                    [
                        'title' => '15. Bảng tổng hợp chia data V2',
                        'disabled' => true,
                    ],
                    [
                        'title' => '16. Báo cáo care đơn',
                        'disabled' => true,
                    ],
                    [
                        'title' => '17. Báo cáo chia số care đơn',
                        'disabled' => true,
                    ],
                ],
            ],
            [
                'title' => '8.6 Số liệu nâng cao',
                'disabled' => true,
            ],
            [
                'title' => '8.7 Khách hàng 360',
                'area' => 'customers',
                'children' => [
                    [
                        'title' => '1. Thống kê khách hàng đa chiều',
                        'url' => '/admin/reports/hourly',
                        'area' => 'reports',
                    ],
                ],
            ],
        ],
    ],
    [
        'title' => '9. Dịch vụ trả phí',
        'icon' => 'credit-card',
        'children' => [
            [
                'title' => '9.1 Tổng đài',
                'children' => [
                    [
                        'title' => '3. Tổng đài user',
                        'disabled' => true,
                    ],
                    [
                        'title' => '4. Quản lý lịch sử cuộc gọi',
                        'disabled' => true,
                    ],
                    [
                        'title' => '5. Thống kê',
                        'disabled' => true,
                    ],
                    [
                        'title' => '6. Cài đặt tổng đài',
                        'disabled' => true,
                    ],
                    [
                        'title' => '7. Download App Pushcall',
                        'disabled' => true,
                    ],
                    [
                        'title' => '8. Quản lý sim push call',
                        'disabled' => true,
                    ],
                ],
            ],
            [
                'title' => '9.2 Quản lý notification',
                'children' => [
                    [
                        'title' => '2. Cấu hình thông báo',
                        'url' => '/notifications',
                    ],
                    [
                        'title' => '3. Quản lý tin nhắn mẫu',
                        'url' => '/notifications',
                    ],
                    [
                        'title' => '4. Quản lý SMS',
                        'url' => '/notifications',
                    ],
                ],
            ],
        ],
    ],
];
