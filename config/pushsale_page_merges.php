<?php

/**
 * Pushsale template menu codes that intentionally reuse an existing ERM
 * business module instead of creating a second, duplicated implementation.
 *
 * The menu code remains independent for navigation/active-state purposes,
 * while the controller, query/service layer and Inertia component are the
 * existing production implementation for that business meaning.
 */
return [
    '1.2.1' => [
        'label' => 'Danh sách nhân viên',
        'controller' => 'app/Http/Controllers/Admin/UserController.php',
        'component' => 'resources/js/pages/Admin/Users/Index.jsx',
        'route_file' => 'routes/web.php',
        'route_marker' => "Route::resource('users'",
        'uri' => '/admin/users',
    ],
    '1.2.2' => [
        'label' => 'Quản lý đội, nhóm',
        'controller' => 'app/Http/Controllers/Admin/TeamController.php',
        'component' => 'resources/js/pages/Admin/Teams/Index.jsx',
        'route_file' => 'routes/web.php',
        'route_marker' => "Route::resource('teams'",
        'uri' => '/admin/teams',
    ],
    '1.3.1' => [
        'label' => 'Quản lý sản phẩm',
        'controller' => 'app/Http/Controllers/Admin/ProductController.php',
        'component' => 'resources/js/pages/Admin/Products/Index.jsx',
        'route_file' => 'routes/web.php',
        'route_marker' => "Route::resource('products'",
        'uri' => '/admin/products',
    ],
    '2.3' => [
        'label' => 'Hồ sơ khách hàng (Marketing)',
        'controller' => 'app/Http/Controllers/Sales/CustomerProfileController.php',
        'component' => 'resources/js/pages/Sales/CustomerProfile.jsx',
        'route_file' => 'routes/pushsale_pages.php',
        'route_marker' => "Route::get('marketing/customers'",
        'uri' => '/admin/marketing/customers',
    ],
    '3.1' => [
        'label' => 'Quản lý khách hàng',
        'controller' => 'app/Http/Controllers/Sales/CustomerProfileController.php',
        'component' => 'resources/js/pages/Sales/CustomerProfile.jsx',
        'route_file' => 'routes/pushsale_pages.php',
        'route_marker' => "Route::get('customer-management'",
        'uri' => '/admin/customer-management',
    ],
    '4.2' => [
        'label' => 'Hồ sơ khách hàng (Telesale)',
        'controller' => 'app/Http/Controllers/Sales/CustomerProfileController.php',
        'component' => 'resources/js/pages/Sales/CustomerProfile.jsx',
        'route_file' => 'routes/pushsale_pages.php',
        'route_marker' => "Route::get('sales/customers'",
        'uri' => '/admin/sales/customers',
    ],
    '5.1' => [
        'label' => 'Đăng đơn / tác nghiệp vận đơn',
        'controller' => 'app/Http/Controllers/Admin/Warehouse/OperationsController.php',
        'component' => 'resources/js/pages/Admin/Warehouse/Operations.jsx',
        'route_file' => 'routes/web.php',
        'route_marker' => "Route::get('warehouse/operations'",
        'uri' => '/admin/warehouse/operations',
    ],
    '5.2.1' => [
        'label' => 'Danh sách kho',
        'controller' => 'app/Http/Controllers/Admin/Warehouse/WarehouseController.php',
        'component' => 'resources/js/pages/Admin/Warehouse/Index.jsx',
        'route_file' => 'routes/web.php',
        'route_marker' => "Route::get('warehouses'",
        'uri' => '/admin/warehouses',
    ],
    '5.2.2' => [
        'label' => 'Danh sách sản phẩm kho',
        'controller' => 'app/Http/Controllers/Admin/Warehouse/InventoryController.php',
        'component' => 'resources/js/pages/Admin/Warehouse/Inventory.jsx',
        'route_file' => 'routes/web.php',
        'route_marker' => "Route::get('warehouse/inventory'",
        'uri' => '/admin/warehouse/inventory',
    ],
];
