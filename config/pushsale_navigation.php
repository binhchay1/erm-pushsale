<?php

return array (
  0 => 
  array (
    'title' => '1. Quản trị đơn vị',
    'icon' => 'cog',
    'children' => 
    array (
      0 => 
      array (
        'title' => '1.1 Thông tin đơn vị',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1.1.1 Thông tin',
            'url' => '/admin/company/profile',
            'code' => '1.1.1',
          ),
          1 => 
          array (
            'title' => '1.1.2 Lịch sử đăng ký gói dịch vụ',
            'url' => '/admin/company/subscription-history',
            'code' => '1.1.2',
          ),
          2 => 
          array (
            'title' => '1.1.3 Quản lý cửa hàng',
            'url' => '/admin/shops',
            'code' => '1.1.3',
          ),
          3 => 
          array (
            'title' => '1.1.4 Tổng quan cửa hàng',
            'url' => '/admin/shops/overview',
            'code' => '1.1.4',
            'area' => 'reports',
          ),
        ),
      ),
      1 => 
      array (
        'title' => '1.2 Nhân sự',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Danh sách nhân viên',
            'url' => '/admin/users',
            'area' => 'hr',
            'code' => '1.2.1',
          ),
          1 => 
          array (
            'title' => '2. Quản lý đội nhóm',
            'url' => '/admin/teams',
            'area' => 'hr',
            'code' => '1.2.2',
          ),
          2 => 
          array (
            'title' => '3. Ca làm việc',
            'url' => '/admin/hr/work-shifts',
            'code' => '1.2.3',
          ),
          3 => 
          array (
            'title' => '4. Cấu hình chia số',
            'url' => '/admin/hr/lead-distribution-rules',
            'area' => 'leads',
            'code' => '1.2.4',
          ),
          4 => 
          array (
            'title' => '5. Cấu hình tài khoản xem báo cáo',
            'url' => '/admin/hr/report-access-rules',
            'code' => '1.2.5',
          ),
          5 => 
          array (
            'title' => '6. Cấu hình chia số care đơn',
            'url' => '/admin/hr/care-distribution-rules',
            'area' => 'leads',
            'code' => '1.2.6',
          ),
        ),
      ),
      2 => 
      array (
        'title' => '1.3 Sản phẩm',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Danh sách sản phẩm',
            'url' => '/admin/products',
            'area' => 'products',
            'code' => '1.3.1',
          ),
          1 => 
          array (
            'title' => '2. Quản lý combo',
            'url' => '/admin/catalog/combos',
            'area' => 'products',
            'code' => '1.3.2',
          ),
        ),
      ),
      3 => 
      array (
        'title' => '1.4 Kết nối giao hàng',
        'url' => '/admin/shipping-partners',
        'area' => 'shipping',
        'code' => '1.4',
      ),
      4 => 
      array (
        'title' => '1.5 Phân bổ data',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Phân bổ data',
            'url' => '/admin/leads',
            'area' => 'leads',
            'code' => '1.5',
          ),
          1 => 
          array (
            'title' => '2. Log data trùng',
            'url' => '/admin/leads/log?bucket=exceptions',
            'area' => 'leads',
            'code' => '1.5.1',
          ),
        ),
      ),
      5 => 
      array (
        'title' => '1.6 Cấu hình chức năng',
        'url' => '/admin/settings/features',
        'code' => '1.6',
      ),
      6 => 
      array (
        'title' => '1.7 Bảo mật',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Lịch sử đăng nhập',
            'url' => '/admin/security/login-history',
            'code' => '1.7.1',
          ),
          1 => 
          array (
            'title' => '2. Quản lý đăng nhập',
            'url' => '/admin/security/login-access',
            'area' => 'hr',
            'code' => '1.7.2',
          ),
          2 => 
          array (
            'title' => '3. Lịch sử lọc data chốt đơn',
            'url' => '/admin/security/lead-filter-history',
            'code' => '1.7.3',
          ),
        ),
      ),
      7 => 
      array (
        'title' => '1.8 Thiết lập quy trình sale',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Khai báo danh mục tác nghiệp',
            'url' => '/admin/sales/operation-categories',
            'code' => '1.8.1',
          ),
          1 => 
          array (
            'title' => '2. Thiết lập luồng tác nghiệp',
            'url' => '/admin/sales/operation-workflows',
            'code' => '1.8.2',
          ),
        ),
      ),
      8 => 
      array (
        'title' => '1.9 Thiết lập chiết khấu, COD',
        'url' => '/admin/sales/discount-cod-rules',
        'code' => '1.9',
      ),
      9 => 
      array (
        'title' => '1.10 Import excel',
        'url' => '/admin/leads/import',
        'area' => 'leads',
        'code' => '1.10',
      ),
      10 => 
      array (
        'title' => '1.11 Cấu hình Facebook đơn vị',
        'url' => '/admin/integrations/facebook-pages',
        'area' => 'connections',
        'code' => '1.11',
      ),
      11 => 
      array (
        'title' => '1.13 Tiện ích',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Quản lý số blacklist',
            'url' => '/admin/security/phone-blacklist',
            'code' => '1.13.1',
          ),
        ),
      ),
      12 => 
      array (
        'title' => '1.14 Quản lý hóa đơn điện tử',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Danh sách cấu hình',
            'url' => '/admin/unit/electronic-invoice-configs',
            'code' => '1.14.1',
          ),
        ),
      ),
      13 => 
      array (
        'title' => '1.15 Thương mại điện tử',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Danh sách kết nối cửa hàng',
            'url' => '/admin/ecommerce/connect-shops',
            'area' => 'ecommerce',
            'code' => '1.15.1',
          ),
          1 => 
          array (
            'title' => '2. Danh sách kết nối sản phẩm',
            'url' => '/admin/ecommerce/connect-products',
            'area' => 'ecommerce',
            'code' => '1.15.2',
          ),
          2 => 
          array (
            'title' => '3. Danh sách đơn hàng lỗi',
            'url' => '/admin/ecommerce/sync-errors',
            'area' => 'ecommerce',
            'code' => '1.15.3',
          ),
        ),
      ),
    ),
  ),
  1 => 
  array (
    'title' => '2. Marketing',
    'icon' => 'trophy',
    'children' => 
    array (
      0 => 
      array (
        'title' => '2.1 Marketing dashboard',
        'url' => '/admin/marketing/dashboard',
        'area' => 'marketing',
        'code' => '2.1',
      ),
      1 => 
      array (
        'title' => '2.2 Bảng xếp hạng',
        'url' => '/admin/rankings',
        'area' => 'marketing',
        'code' => '2.2',
      ),
      2 => 
      array (
        'title' => '2.3 Hồ sơ khách hàng',
        'url' => '/admin/marketing/customers',
        'area' => 'customers',
        'code' => '2.3',
      ),
      3 => 
      array (
        'title' => '2.4 Kết nối landing - website',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Kết nối landing',
            'url' => '/admin/marketing/landing-connections',
            'code' => '2.4.1',
          ),
          1 => 
          array (
            'title' => '2. Kết nối website',
            'url' => '/admin/marketing/website-connections',
            'area' => 'connections',
            'code' => '2.4.2',
          ),
          2 => 
          array (
            'title' => '3. Duyệt kết nối dữ liệu',
            'url' => '/admin/marketing/landing-approvals',
            'area' => 'connections',
            'code' => '2.4.3',
          ),
        ),
      ),
      4 => 
      array (
        'title' => '2.5 Kết nối facebook',
        'area' => 'connections',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Tạo nguồn dữ liệu',
            'url' => '/admin/marketing/website-connections',
            'area' => 'connections',
            'code' => '2.5.1',
          ),
          1 => 
          array (
            'title' => '2. Kết nối Fanpage Facebook',
            'url' => '/admin/marketing/facebook/connect',
            'area' => 'connections',
            'code' => '2.5.2',
          ),
          2 => 
          array (
            'title' => '3. Danh sách bài post Facebook',
            'url' => '/admin/marketing/facebook/posts',
            'area' => 'connections',
            'code' => '2.5.3',
          ),
        ),
      ),
      5 => 
      array (
        'title' => '2.6 Tiện ích',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Import excel',
            'url' => '/admin/marketing/leads/import',
            'area' => 'leads',
            'code' => '2.6.1',
          ),
          1 => 
          array (
            'title' => '2. Nhập data thủ công',
            'url' => '/admin/marketing/leads/manual',
            'area' => 'leads',
            'code' => '2.6.2',
          ),
          2 => 
          array (
            'title' => '3. Kết nối phần mềm thứ 3',
            'url' => '/admin/marketing/partner-connections',
            'area' => 'connections',
            'code' => '2.6.3',
          ),
          3 => 
          array (
            'title' => '4. Quản lý số seeding',
            'url' => '/admin/marketing/seeding-numbers',
            'code' => '2.6.4',
          ),
        ),
      ),
      6 => 
      array (
        'title' => '2.7 Báo cáo',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Báo cáo doanh số marketing',
            'url' => '/admin/marketing/reports/revenue-detail',
            'area' => 'reports',
            'code' => '2.7.1',
          ),
          1 => 
          array (
            'title' => '2. Báo cáo doanh số',
            'url' => '/admin/marketing/reports/revenue',
            'area' => 'reports',
            'code' => '2.7.2',
          ),
          2 => 
          array (
            'title' => '3. Báo cáo doanh số V2',
            'url' => '/admin/marketing/reports/revenue-v2',
            'area' => 'reports',
            'code' => '2.7.3',
          ),
          3 => 
          array (
            'title' => '4. CEO Dashboard V2',
            'url' => '/admin/reports/ceo-dashboard-v2',
            'area' => 'reports',
            'code' => '2.7.4',
          ),
          4 => 
          array (
            'title' => '5. Báo cáo công việc',
            'url' => '/admin/marketing/reports/work',
            'area' => 'reports',
            'code' => '2.7.5',
          ),
          5 => 
          array (
            'title' => '6. Báo cáo kinh doanh hệ thống',
            'url' => '/admin/reports/system-business',
            'area' => 'reports',
            'code' => '2.7.6',
          ),
        ),
      ),
      7 => 
      array (
        'title' => '2.8. Marketing Leader',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Thống kê trưởng nhóm',
            'url' => '/admin/reports/team-leaders',
            'area' => 'reports',
            'code' => '2.8.1',
          ),
          1 => 
          array (
            'title' => '2. Báo cáo công việc',
            'url' => '/admin/marketing/reports/work',
            'area' => 'reports',
            'code' => '2.8.2',
          ),
          2 => 
          array (
            'title' => '3. Báo cáo up sale',
            'url' => '/admin/marketing/reports/upsale',
            'area' => 'reports',
            'code' => '2.8.3',
          ),
        ),
      ),
      8 => 
      array (
        'title' => '2.9 Thương mại điện tử',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Danh sách kết nối cửa hàng',
            'url' => '/admin/ecommerce/connect-shops',
            'area' => 'ecommerce',
            'code' => '2.9.1',
          ),
          1 => 
          array (
            'title' => '2. Danh sách kết nối sản phẩm',
            'url' => '/admin/ecommerce/connect-products',
            'area' => 'ecommerce',
            'code' => '2.9.2',
          ),
          2 => 
          array (
            'title' => '3. Danh sách đơn hàng lỗi',
            'url' => '/admin/ecommerce/sync-errors',
            'area' => 'ecommerce',
            'code' => '2.9.3',
          ),
        ),
      ),
    ),
  ),
  2 => 
  array (
    'title' => '3. Khách hàng 360',
    'area' => 'customers',
    'icon' => 'user',
    'children' => 
    array (
      0 => 
      array (
        'title' => '3.1 Quản lý khách hàng',
        'url' => '/admin/customer-management',
        'area' => 'customers',
        'code' => '3.1',
      ),
      1 => 
      array (
        'title' => '3.2 Chiến dịch chăm sóc',
        'url' => '/admin/customers/care-campaigns',
        'code' => '3.2',
      ),
      2 => 
      array (
        'title' => '3.3 Báo cáo',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Thống kê khách hàng đa chiều',
            'url' => '/admin/customers/reports/multidimensional',
            'area' => 'reports',
            'code' => '3.3.1',
          ),
          1 => 
          array (
            'title' => '2. Thống kê khách hàng chi trả',
            'url' => '/admin/customers/reports/spending',
            'area' => 'reports',
            'code' => '3.3.2',
          ),
        ),
      ),
    ),
  ),
  3 => 
  array (
    'title' => '4. Telesale',
    'icon' => 'tty',
    'children' => 
    array (
      0 => 
      array (
        'title' => '4.1 Tác nghiệp telesale',
        'url' => '/admin/sales/workspace',
        'area' => 'reports',
      ),
      1 => 
      array (
        'title' => '4.2 Hồ sơ khách hàng',
        'url' => '/admin/sales/customers',
        'area' => 'customers',
        'code' => '4.2',
      ),
      2 => 
      array (
        'title' => '4.3 Bảng xếp hạng',
        'url' => '/admin/sales/rankings',
        'code' => '4.3',
      ),
      3 => 
      array (
        'title' => '4.4 Kho số thả nổi',
        'disabled' => true,
      ),
      4 => 
      array (
        'title' => '4.5 Báo cáo',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Sale KPI',
            'url' => '/admin/sales/reports/sale-kpi',
            'area' => 'reports',
            'code' => '4.5.1',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'sales',
            ),
          ),
          1 => 
          array (
            'title' => '2. Bảng tổng hợp chốt đơn',
            'url' => '/admin/sales/reports/closing-summary',
            'area' => 'reports',
            'code' => '4.5.2',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'sales',
              2 => 'accounting',
            ),
          ),
          2 => 
          array (
            'title' => '3. Báo cáo công việc sale',
            'url' => '/admin/sales/reports/work',
            'area' => 'reports',
            'code' => '4.5.3',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'sales',
            ),
          ),
          3 => 
          array (
            'title' => '4. Báo cáo doanh số sale',
            'url' => '/admin/sales/reports/revenue-detail',
            'area' => 'reports',
            'code' => '4.5.4',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'sales',
              2 => 'accounting',
            ),
          ),
          4 => 
          array (
            'title' => '5. Báo cáo doanh số',
            'url' => '/admin/sales/reports/revenue',
            'area' => 'reports',
            'code' => '4.5.5',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'sales',
            ),
          ),
          5 => 
          array (
            'title' => '6. Báo cáo doanh số V2',
            'url' => '/admin/sales/reports/revenue-v2',
            'area' => 'reports',
            'code' => '4.5.6',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'sales',
            ),
          ),
          6 => 
          array (
            'title' => '7. CEO dashboard V2',
            'url' => '/admin/reports/ceo-dashboard-v2',
            'area' => 'reports',
            'code' => '4.5.7',
            'roles' => 
            array (
              0 => 'admin',
            ),
          ),
          7 => 
          array (
            'title' => '8. Báo cáo lịch hẹn telesales',
            'url' => '/admin/sales/reports/appointments',
            'area' => 'reports',
            'code' => '4.5.8',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'sales',
            ),
          ),
          8 => 
          array (
            'title' => '9. Báo cáo kinh doanh hệ thống',
            'url' => '/admin/reports/system-business',
            'area' => 'reports',
            'code' => '4.5.9',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'sales',
              2 => 'marketing',
              3 => 'warehouse',
              4 => 'accounting',
            ),
          ),
        ),
      ),
      5 => 
      array (
        'title' => '4.6 Báo cáo Leader',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Thống kê tỉ lệ chốt đơn',
            'url' => '/admin/sales/reports/operation-conversion',
            'area' => 'reports',
            'code' => '4.6.1',
          ),
          1 => 
          array (
            'title' => '2. Thống kê công việc sale',
            'url' => '/admin/sales/reports/work',
            'area' => 'reports',
            'code' => '4.6.2',
          ),
          2 => 
          array (
            'title' => '3. Thống kê nhóm',
            'url' => '/admin/sales/reports/teams',
            'area' => 'reports',
            'code' => '4.6.3',
          ),
          3 => 
          array (
            'title' => '4. Báo cáo data sale',
            'url' => '/admin/sales/reports/data',
            'area' => 'reports',
            'code' => '4.6.4',
          ),
          4 => 
          array (
            'title' => '5. Tối ưu sale',
            'url' => '/admin/sales/reports/optimization',
            'area' => 'reports',
            'code' => '4.6.5',
          ),
        ),
      ),
    ),
  ),
  4 => 
  array (
    'title' => '5. Kho',
    'icon' => 'cubes',
    'children' => 
    array (
      0 => 
      array (
        'title' => '5.1 Đăng đơn',
        'url' => '/admin/warehouse/operations',
        'area' => 'warehouse',
        'code' => '5.1',
      ),
      1 => 
      array (
        'title' => '5.2 Quản lý kho',
        'area' => 'warehouse',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Danh sách kho',
            'url' => '/admin/warehouses',
            'area' => 'warehouse',
            'code' => '5.2.1',
          ),
          1 => 
          array (
            'title' => '2. Danh sách sản phẩm kho',
            'url' => '/admin/warehouse/inventory',
            'area' => 'warehouse',
            'code' => '5.2.2',
          ),
        ),
      ),
      2 => 
      array (
        'title' => '5.3 Nhập, xuất kho',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Phiếu xuất / nhập kho',
            'url' => '/admin/warehouse/vouchers/entry',
            'code' => '5.3.1',
          ),
          1 => 
          array (
            'title' => '2. Danh sách phiếu xuất / nhập kho',
            'url' => '/admin/warehouse/vouchers',
            'code' => '5.3.2',
          ),
          2 => 
          array (
            'title' => '3. Lịch sử nhập, xuất kho',
            'url' => '/admin/warehouse/movement-history',
            'area' => 'warehouse',
            'code' => '5.3.3',
          ),
        ),
      ),
      3 => 
      array (
        'title' => '5.4 Quản lý biên bản',
        'url' => '/admin/warehouse/incidents',
        'code' => '5.4',
      ),
      4 => 
      array (
        'title' => '5.5 Báo cáo',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Báo cáo nhập, xuất theo ngày',
            'url' => '/admin/warehouse/reports/daily-stock',
            'area' => 'reports',
            'code' => '5.5.1',
          ),
          1 => 
          array (
            'title' => '2. Bảng tổng hợp chờ xuất theo ngày',
            'url' => '/admin/warehouse/reports/pending-export',
            'area' => 'reports',
            'code' => '5.5.2',
          ),
          2 => 
          array (
            'title' => '3. Báo cáo kinh doanh hệ thống',
            'url' => '/admin/reports/system-business',
            'area' => 'reports',
            'code' => '5.5.3',
          ),
          3 => 
          array (
            'title' => '4. Báo cáo tổng hợp phát sinh kho',
            'url' => '/admin/warehouse/reports/movement-summary',
            'area' => 'reports',
            'code' => '5.5.4',
          ),
          4 => 
          array (
            'title' => '5. Báo cáo care đơn',
            'url' => '/admin/warehouse/reports/care-orders',
            'code' => '5.5.5',
          ),
          5 => 
          array (
            'title' => '6. Báo cáo sửa số giao hàng',
            'url' => '/admin/warehouse/reports/phone-corrections',
            'code' => '5.5.6',
          ),
          6 => 
          array (
            'title' => '7. Tổng hợp trạng thái giao hàng theo TK vận đơn',
            'url' => '/admin/warehouse/reports/delivery-status',
            'code' => '5.5.7',
          ),
          7 => 
          array (
            'title' => '8. Báo cáo care đơn tác nghiệp',
            'url' => '/admin/warehouse/reports/care-operations',
            'code' => '5.5.8',
          ),
          8 => 
          array (
            'title' => '9. Báo cáo doanh số theo kho',
            'url' => '/admin/warehouse/reports/revenue',
            'area' => 'reports',
            'code' => '5.5.9',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'warehouse',
            ),
          ),
          9 => 
          array (
            'title' => '10. Báo cáo doanh số V2',
            'url' => '/admin/warehouse/reports/revenue-v2',
            'area' => 'reports',
            'code' => '5.5.10',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'warehouse',
            ),
          ),
        ),
      ),
      5 => 
      array (
        'title' => '5.7 Thương mại điện tử',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Danh sách đơn hàng lỗi',
            'url' => '/admin/orders/failed',
          ),
        ),
      ),
      6 => 
      array (
        'title' => '5.8 Cấu hình care đơn',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Cấu hình chia số care đơn',
            'url' => '/admin/hr/care-distribution-rules',
            'area' => 'care_distribution',
            'code' => '5.8.1',
          ),
          1 => 
          array (
            'title' => '2. Phân bổ data care đơn',
            'url' => '/admin/warehouse/care-distribution',
            'code' => '5.8.2',
          ),
        ),
      ),
    ),
  ),
  5 => 
  array (
    'title' => '6. Kế toán',
    'icon' => 'calculator',
    'children' => 
    array (
      0 => 
      array (
        'title' => '6.1 Đối soát đơn',
        'url' => '/admin/accounting',
        'area' => 'accounting',
      ),
      1 => 
      array (
        'title' => '6.2 Quản lý chi phí đơn vị',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Nhập chi phí',
            'url' => '/admin/accounting/expenses',
            'code' => '6.2.1',
          ),
          1 => 
          array (
            'title' => '2. Khai báo danh mục chi phí',
            'url' => '/admin/accounting/expense-categories',
            'code' => '6.2.2',
          ),
          2 => 
          array (
            'title' => '3. Khai báo nhóm chi phí',
            'url' => '/admin/accounting/expense-groups',
            'code' => '6.2.3',
          ),
          3 => 
          array (
            'title' => '4. Khai báo đơn vị tính',
            'url' => '/admin/accounting/expense-units',
            'code' => '6.2.4',
          ),
        ),
      ),
      2 => 
      array (
        'title' => '6.3 Báo cáo',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. CEO dashboard',
            'url' => '/admin/accounting/reports/ceo-dashboard?menu=8.5.1',
            'code' => '8.5.1',
            'area' => 'reports',
            'code' => '6.3.1',
            'roles' => 
            array (
              0 => 'admin',
            ),
          ),
          1 => 
          array (
            'title' => '2. Báo cáo doanh số theo kho',
            'url' => '/admin/warehouse/reports/revenue',
            'area' => 'reports',
            'code' => '6.3.2',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
              2 => 'warehouse',
            ),
          ),
          2 => 
          array (
            'title' => '3. Báo cáo doanh số V2',
            'url' => '/admin/warehouse/reports/revenue-v2',
            'area' => 'reports',
            'code' => '6.3.3',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
              2 => 'warehouse',
            ),
          ),
          3 => 
          array (
            'title' => '4. Báo cáo kinh doanh',
            'url' => '/admin/reports/system-business',
            'area' => 'reports',
            'code' => '6.3.4',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
              2 => 'warehouse',
            ),
          ),
          4 => 
          array (
            'title' => '5. Tổng kết lương tháng',
            'url' => '/admin/accounting/reports/monthly-plan',
            'code' => '6.3.5',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
            ),
          ),
          5 => 
          array (
            'title' => '6. Báo cáo doanh số chi tiết',
            'url' => '/admin/sales/reports/revenue-detail',
            'area' => 'reports',
            'code' => '6.3.6',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
              2 => 'sales',
            ),
          ),
          6 => 
          array (
            'title' => '7. CEO dashboard V2',
            'url' => '/admin/reports/ceo-dashboard-v2',
            'area' => 'reports',
            'code' => '6.3.7',
            'roles' => 
            array (
              0 => 'admin',
            ),
          ),
          7 => 
          array (
            'title' => '8. Báo cáo kinh doanh hệ thống',
            'url' => '/admin/reports/system-business',
            'area' => 'reports',
            'code' => '6.3.8',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
              2 => 'warehouse',
            ),
          ),
          8 => 
          array (
            'title' => '9. Báo cáo tỉ lệ chốt đơn sản phẩm',
            'url' => '/admin/reports/product-conversion',
            'area' => 'reports',
            'code' => '6.3.9',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
              2 => 'sales',
              3 => 'marketing',
            ),
          ),
          9 => 
          array (
            'title' => '10. Bảng tổng hợp chốt đơn',
            'url' => '/admin/sales/reports/closing-summary',
            'area' => 'reports',
            'code' => '6.3.10',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
            ),
          ),
          10 => 
          array (
            'title' => '11. Báo cáo doanh số marketing',
            'url' => '/admin/marketing/reports/revenue-detail',
            'area' => 'reports',
            'code' => '6.3.11',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
            ),
          ),
          11 => 
          array (
            'title' => '12. Báo cáo up sale',
            'url' => '/admin/marketing/reports/upsale',
            'area' => 'reports',
            'code' => '6.3.12',
            'roles' => 
            array (
              0 => 'admin',
              1 => 'accounting',
            ),
          ),
        ),
      ),
      3 => 
      array (
        'title' => '6.4 Danh sách xử lý HĐĐT',
        'url' => '/admin/accounting/electronic-invoices',
        'code' => '6.4',
      ),
    ),
  ),
  6 => 
  array (
    'title' => '7. CEO',
    'icon' => 'user-secret',
    'children' => 
    array (
      0 => 
      array (
        'title' => '7.1 Kế hoạch kinh doanh',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Kế hoạch kinh doanh tháng',
            'url' => '/admin/ceo/business-plan/monthly',
            'code' => '7.1.1',
          ),
          1 => 
          array (
            'title' => '2. Lập kế hoạch kinh doanh năm',
            'url' => '/admin/ceo/business-plan/yearly',
            'code' => '7.1.2',
          ),
          2 => 
          array (
            'title' => '3. Danh mục KPI',
            'url' => '/admin/ceo/business-plan/kpi-catalog',
            'code' => '7.1.3',
          ),
          3 => 
          array (
            'title' => '4. Khai báo thưởng',
            'url' => '/admin/ceo/business-plan/revenue-bonus',
            'code' => '7.1.4',
          ),
        ),
      ),
    ),
  ),
  7 => 
  array (
    'title' => '8. Báo cáo thống kê',
    'icon' => 'dashboard',
    'children' => 
    array (
      0 => 
      array (
        'title' => '8.1 Marketing',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Biểu đồ thống kê theo khung giờ',
            'url' => '/admin/reports/hourly',
            'area' => 'reports',
            'code' => '8.1.1',
          ),
          1 => 
          array (
            'title' => '2. Báo cáo doanh số marketing',
            'url' => '/bao-cao/bao-cao-doanh-so-chi-tiet-marketing',
            'area' => 'reports',
            'code' => '8.1.2',
          ),
          2 => 
          array (
            'title' => '3. Báo cáo up sale',
            'url' => '/admin/marketing/reports/upsale',
            'area' => 'reports',
            'code' => '8.1.3',
          ),
        ),
      ),
      1 => 
      array (
        'title' => '8.2 Sale',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Báo cáo công việc sale',
            'url' => '/admin/sales/reports/work',
            'area' => 'reports',
          ),
          1 => 
          array (
            'title' => '2. Báo cáo doanh số sale',
            'url' => '/admin/sales/revenue',
            'area' => 'reports',
          ),
          2 => 
          array (
            'title' => '3. Báo cáo lịch hẹn telesales',
            'url' => '/admin/sales/reports/appointments',
            'area' => 'reports',
          ),
        ),
      ),
      2 => 
      array (
        'title' => '8.3 Kho',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Báo cáo nhập, xuất theo ngày',
            'url' => '/admin/warehouse/movements',
            'area' => 'reports',
          ),
          1 => 
          array (
            'title' => '2. Bảng tổng hợp chờ xuất theo ngày',
            'url' => '/admin/warehouse/reports/pending-export',
            'area' => 'reports',
          ),
          2 => 
          array (
            'title' => '3. Báo cáo giá vốn sản phẩm',
            'url' => '/admin/warehouse/reports/movement-summary',
            'area' => 'reports',
          ),
          3 => 
          array (
            'title' => '4. Báo cáo kinh doanh hệ thống',
            'url' => '/admin/reports/system-business',
            'area' => 'reports',
          ),
        ),
      ),
      3 => 
      array (
        'title' => '8.4 Kế toán',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Báo cáo kinh doanh',
            'url' => '/admin/reports/system-business',
            'area' => 'reports',
          ),
        ),
      ),
      4 => 
      array (
        'title' => '8.5 Quản trị',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. CEO dashboard',
            'url' => '/admin/accounting/reports/ceo-dashboard?menu=8.5.1',
            'code' => '8.5.1',
            'area' => 'reports',
          ),
          1 => 
          array (
            'title' => '2. CEO dashboard V2',
            'url' => '/admin/reports/ceo-dashboard-v2?menu=8.5.2',
            'code' => '8.5.2',
            'area' => 'reports',
          ),
          2 => 
          array (
            'title' => '3. Phong thần bảng',
            'url' => '/admin/rankings?menu=8.5.3',
            'code' => '8.5.3',
          ),
          3 => 
          array (
            'title' => '4. Biểu đồ xu hướng',
            'url' => '/admin/reports/trends',
            'code' => '8.5.4',
          ),
          4 => 
          array (
            'title' => '5. Bảng tổng hợp chia data',
            'url' => '/admin/reports/data-allocation',
            'code' => '8.5.5',
          ),
          5 => 
          array (
            'title' => '6. Báo cáo biểu đồ',
            'disabled' => true,
          ),
          6 => 
          array (
            'title' => '7. Báo cáo doanh số',
            'url' => '/admin/sales/reports/revenue?menu=8.5.7',
            'area' => 'reports',
          ),
          7 => 
          array (
            'title' => '8. Báo cáo doanh số V2',
            'url' => '/admin/sales/reports/revenue-v2?menu=8.5.8',
            'area' => 'reports',
          ),
          8 => 
          array (
            'title' => '9. Power dashboard',
            'url' => '/admin/reports/power-dashboard',
            'code' => '8.5.9',
          ),
          9 => 
          array (
            'title' => '10. Thống kê khách hàng mua lại',
            'url' => '/admin/reports/repurchase',
            'area' => 'reports',
            'code' => '8.5.10',
          ),
          10 => 
          array (
            'title' => '11. Thống kê KH mua lại theo số sp',
            'url' => '/admin/reports/repurchase-products',
            'area' => 'reports',
            'code' => '8.5.11',
          ),
          11 => 
          array (
            'title' => '12. Thống kê KH mua lại theo sản phẩm',
            'url' => '/admin/reports/repurchase-products?menu=8.5.12&variant=product',
            'code' => '8.5.12',
            'area' => 'reports',
          ),
          12 => 
          array (
            'title' => '13. Báo cáo thao tác nhập số',
            'disabled' => true,
            'code' => '8.5.13',
            'area' => 'reports',
          ),
          13 => 
          array (
            'title' => '14. Báo cáo tỉ lệ chốt đơn sản phẩm',
            'url' => '/admin/reports/product-conversion?menu=8.5.14',
            'code' => '8.5.14',
            'area' => 'reports',
          ),
          14 => 
          array (
            'title' => '15. Bảng tổng hợp chia data V2',
            'url' => '/admin/reports/data-allocation-v2',
            'code' => '8.5.15',
          ),
          15 => 
          array (
            'title' => '16. Báo cáo care đơn',
            'url' => '/admin/reports/care-orders',
            'code' => '8.5.16',
          ),
          16 => 
          array (
            'title' => '17. Báo cáo chia số care đơn',
            'url' => '/admin/reports/care-allocation',
            'code' => '8.5.17',
          ),
        ),
      ),
      5 => 
      array (
        'title' => '8.6 Số liệu nâng cao',
        'disabled' => true,
      ),
      6 => 
      array (
        'title' => '8.7 Khách hàng 360',
        'area' => 'customers',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Thống kê khách hàng đa chiều',
            'url' => '/admin/customers/reports/multidimensional',
            'area' => 'customers',
          ),
        ),
      ),
    ),
  ),
  8 => 
  array (
    'title' => '9. Dịch vụ trả phí',
    'icon' => 'credit-card',
    'children' => 
    array (
      0 => 
      array (
        'title' => '9.1 Tổng đài',
        'children' => 
        array (
          0 => 
          array (
            'title' => '3. Tổng đài user',
            'disabled' => true,
          ),
          1 => 
          array (
            'title' => '4. Quản lý lịch sử cuộc gọi',
            'disabled' => true,
          ),
          2 => 
          array (
            'title' => '5. Thống kê',
            'disabled' => true,
          ),
          3 => 
          array (
            'title' => '6. Cài đặt tổng đài',
            'disabled' => true,
          ),
          4 => 
          array (
            'title' => '7. Download App Pushcall',
            'disabled' => true,
          ),
          5 => 
          array (
            'title' => '8. Quản lý sim push call',
            'disabled' => true,
          ),
        ),
      ),
      1 => 
      array (
        'title' => '9.2 Quản lý notification',
        'children' => 
        array (
          0 => 
          array (
            'title' => '2. Cấu hình thông báo',
            'url' => '/notifications',
          ),
          1 => 
          array (
            'title' => '3. Quản lý tin nhắn mẫu',
            'url' => '/notifications',
          ),
          2 => 
          array (
            'title' => '4. Quản lý SMS',
            'url' => '/notifications',
          ),
        ),
      ),
    ),
  ),
  9 => 
  array (
    'title' => '10. Vận hành hệ thống',
    'icon' => 'server',
    'roles' => 
    array (
      0 => 'admin',
    ),
    'children' => 
    array (
      0 => 
      array (
        'title' => '10.1 Giám sát & nhật ký',
        'children' => 
        array (
          0 => 
          array (
            'title' => '1. Giám sát hệ thống',
            'url' => '/admin/system-monitor',
            'code' => '10.1.1',
            'roles' => 
            array (
              0 => 'admin',
            ),
          ),
          1 => 
          array (
            'title' => '2. Nhật ký hoạt động',
            'url' => '/admin/activity-logs',
            'code' => '10.1.2',
            'roles' => 
            array (
              0 => 'admin',
            ),
          ),
          2 => 
          array (
            'title' => '3. Báo cáo tỉ lệ chốt đơn sản phẩm',
            'url' => '/admin/reports/product-conversion?menu=10.1.3',
            'area' => 'reports',
            'code' => '10.1.3',
            'roles' => 
            array (
              0 => 'admin',
            ),
          ),
          3 => 
          array (
            'title' => '4. Cấu hình hệ thống',
            'url' => '/admin/system/settings',
            'code' => '10.1.4',
            'roles' => 
            array (
              0 => 'admin',
            ),
          ),
          // 10.1.5 Định danh đăng nhập — append runtime cho platform admin (NavigationService).
        ),
      ),
      // 10.2 Quản trị doanh nghiệp — append runtime cho platform admin (NavigationService).
    ),
  ),
);
