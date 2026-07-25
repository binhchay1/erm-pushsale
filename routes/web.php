<?php

use App\Http\Controllers\Accounting\DashboardController as AccountingDashboardController;
use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FailedPartnerOrderController;
use App\Http\Controllers\Admin\IntegrationsController;
use App\Http\Controllers\Admin\EcommerceConnectShopController;
use App\Http\Controllers\Admin\Ecommerce\EcommerceController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\LandingApprovalController;
use App\Http\Controllers\Admin\LeadIngestionController;
use App\Http\Controllers\Admin\DataDistributionController;
use App\Http\Controllers\Admin\LeadReviewController;
use App\Http\Controllers\Admin\ManualLeadController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Http\Controllers\Admin\ManualLeadAllocationController;
use App\Http\Controllers\Admin\Marketing\CampaignBudgetController;
use App\Http\Controllers\Admin\Marketing\CampaignReportController;
use App\Http\Controllers\Admin\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Admin\Marketing\DashboardDataController as MarketingDashboardDataController;
use App\Http\Controllers\Admin\Marketing\RevenueReportController as MarketingRevenueReportController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\Orders\FailedOrdersController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\Reports\CeoReportController;
use App\Http\Controllers\Admin\Sales\PerformanceReportController as AdminSalesPerformanceReportController;
use App\Http\Controllers\Admin\Sales\WorkspaceController as AdminSalesWorkspaceController;
use App\Http\Controllers\Admin\Sales\RevenueReportController as SaleRevenueReportController;
use App\Http\Controllers\Admin\ShippingOrderController;
use App\Http\Controllers\Admin\ShippingPartnersController;
use App\Http\Controllers\Admin\ShippingPartnerTestController;
use App\Http\Controllers\Admin\CarrierSettlementController;
use App\Http\Controllers\Admin\ShippingReconciliationController;
use App\Http\Controllers\Admin\SystemMonitorController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\Warehouse\InventoryController;
use App\Http\Controllers\Admin\Warehouse\InventoryMovementController;
use App\Http\Controllers\Admin\Warehouse\MovementHistoryController;
use App\Http\Controllers\Admin\Warehouse\OperationsController as WarehouseOperationsController;
use App\Http\Controllers\Admin\Warehouse\WarehouseController;
use App\Http\Controllers\Admin\WarehouseInventoryController;
use App\Http\Controllers\Allocator\DashboardController as AllocatorDashboardController;
use App\Http\Controllers\Allocator\ReportController as AllocatorReportController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerInteractions\CustomerInternalMessageController;
use App\Http\Controllers\CustomerInteractions\CustomerPurchaseHistoryController;
use App\Http\Controllers\CustomerInteractions\CustomerSupplementPacketController;
use App\Http\Controllers\CustomerInteractions\CustomerDataViewHistoryController;
use App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController;
use App\Http\Controllers\CustomerInteractions\OrderOperationHistoryController;
use App\Http\Controllers\CustomerInteractions\PancakeCustomerMessageController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\Marketing\CampaignReportController as MarketingCampaignReportController;
use App\Http\Controllers\Marketing\DashboardController as RoleMarketingDashboardController;
use App\Http\Controllers\Marketing\RankingController as MarketingRankingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\Platform\CompanyController as PlatformCompanyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ExtraReportController;
use App\Http\Controllers\Reports\HourlyStatsController;
use App\Http\Controllers\Reports\TeamLeaderStatsController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\Sales\DashboardController as SalesDashboardController;
use App\Http\Controllers\Sales\OperationController;
use App\Http\Controllers\Sales\OrderClosingController;
use App\Http\Controllers\Sales\PerformanceReportController as SalesPerformanceReportController;
use App\Http\Controllers\Sales\RankingController as SalesRankingController;
use App\Http\Controllers\Sales\SaleBulkCloseController;
use App\Http\Controllers\Sales\SaleOperationCallController;
use App\Http\Controllers\Sales\SaleOperationOrderController;
use App\Http\Controllers\Sales\SaleOrderDeletionController;
use App\Http\Controllers\Sales\SaleOperationStatusController;
use App\Http\Controllers\Sales\SaleOperationNoteController;
use App\Http\Controllers\Sales\DesiredDeliveryDateController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Warehouse\DashboardController as WarehouseDashboardController;
use App\Http\Controllers\Warehouse\OrderReturnController;
use App\Http\Controllers\Warehouse\WarehouseOrderActionController;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['web', 'auth', 'tenant']]);

Route::match(['get', 'post'], 'locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/', HomeController::class)->name('home');

// Trang vệ tinh công khai (SEO) — không cần đăng nhập.
Route::get('features', [MarketingController::class, 'features'])->name('marketing.features');
Route::get('solutions', [MarketingController::class, 'solutions'])->name('marketing.solutions');
Route::get('docs', [MarketingController::class, 'docs'])->name('marketing.docs');
Route::get('about', [MarketingController::class, 'about'])->name('marketing.about');
Route::get('contact', [MarketingController::class, 'contact'])->name('marketing.contact');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');


// Staging-only QA endpoints. Protected by ERM_STAGING_TEST_MODE + ERM_STAGING_TEST_SECRET.
Route::prefix('__erm-test')->name('staging-test.')->group(function () {
    Route::get('health', [\App\Http\Controllers\Testing\StagingTestController::class, 'health'])->name('health');
    Route::get('pages', [\App\Http\Controllers\Testing\StagingTestController::class, 'pages'])->name('pages');
    Route::get('bootstrap', [\App\Http\Controllers\Testing\StagingTestController::class, 'bootstrap'])->name('bootstrap');
    Route::get('demo-ui', [\App\Http\Controllers\Testing\StagingTestController::class, 'demoUi'])->name('demo-ui');
    Route::get('flow', [\App\Http\Controllers\Testing\StagingTestController::class, 'flow'])->name('flow');
    Route::get('landing-flow', [\App\Http\Controllers\Testing\StagingTestController::class, 'landingFlow'])->name('landing-flow');
    Route::get('audit', [\App\Http\Controllers\Testing\StagingTestController::class, 'audit'])->name('audit');
    Route::get('logs', [\App\Http\Controllers\Testing\StagingTestController::class, 'logs'])->name('logs');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'tenant', 'permissions'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Sơ đồ tổ chức — test RBAC: /org-chart (xem OrgChartController)
    Route::get('org-chart', [OrgChartController::class, 'index'])->name('org-chart.index');

    // Địa giới hành chính cho ô chọn Tỉnh/Huyện/Xã (cascading).
    Route::get('geo/provinces', [GeoController::class, 'provinces'])->name('geo.provinces');
    Route::get('geo/provinces/{province}/districts', [GeoController::class, 'districts'])->name('geo.districts');
    Route::get('geo/provinces/{province}/wards', [GeoController::class, 'provinceWards'])->name('geo.province-wards');
    Route::get('geo/districts/{district}/wards', [GeoController::class, 'wards'])->name('geo.wards');

    Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('ld/unit-admin/cau-hinh-chuc-nang', [SettingsController::class, 'index'])->name('legacy.unit.feature-settings');

    Route::redirect('ld/unit-admin/phone-blacklist', '/admin/security/phone-blacklist', 301);
    Route::redirect('ld/unit-admin/danh-sach-cau-hinh-hddt', '/admin/unit/electronic-invoice-configs', 301);
    Route::get('ld/unit-admin/cau-hinh-giao-hang', [ShippingPartnersController::class, 'index'])->name('legacy.unit.shipping-config');
    Route::get('ld/marketing/thong-ke-truong-nhom', TeamLeaderStatsController::class)->name('legacy.marketing.team-leader-stats');
    Route::get('ld/thong-ke', HourlyStatsController::class)->name('legacy.reports.hourly');
    Route::get('bao-cao/bao-cao-doanh-so-chi-tiet-marketing', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'marketing-1');
    })->name('legacy.reports.marketing-revenue-detail');
    Route::get('ld/thong-ke/bao-cao-cong-viec-mkt', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'marketing-3');
    })->name('legacy.reports.marketing-work');
    Route::get('ld/thong-ke/bao-cao-up-sale', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'marketing-4');
    })->name('legacy.reports.marketing-upsale');
    Route::get('ld/sale/bang-tong-hop-ban-hang', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'sale-closing-summary');
    })->name('legacy.reports.sale-closing-summary');


    Route::get('ld/sale/sale-kpi', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'sale-kpi');
    })->name('legacy.reports.sale-kpi');
    Route::get('ld/sale/bao-cao/bao-cao-cong-viec-sale', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'sale-work');
    })->name('legacy.reports.sale-work');
    Route::get('ld/sale/bao-cao-doanh-so-chi-tiet', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'sale-revenue-detail');
    })->name('legacy.reports.sale-revenue-detail');
    Route::get('ld/sale/bao-cao/bao-cao-doanh-so', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'sale-revenue');
    })->name('legacy.reports.sale-revenue');
    Route::get('ld/sale/bao-cao/bao-cao-doanh-so-v2', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'sale-revenue-v2');
    })->name('legacy.reports.sale-revenue-v2');
    Route::get('ld/thong-ke/bao-cao-lich-hen-telesales', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'sale-appointments');
    })->name('legacy.reports.sale-appointments');
    Route::get('ld/thong-ke/bao-cao-kinh-doanh-he-thong', function (Illuminate\Http\Request $request, ExtraReportController $controller) {
        return $controller($request, 'system-business');
    })->name('legacy.reports.system-business');


    // Hồ sơ khách hàng dùng chung cho các vai trò có quyền customers:view.
    Route::get('customers', CustomerProfileController::class)->name('customers.index');

    // Dialog lịch sử tác nghiệp, mua hàng, tin nội bộ và chat Pancake theo khách hàng.
    // customers:full gửi tin nội bộ; customer_chat:full gửi trực tiếp qua Pancake.
    Route::get('customers/orders/{order}/operation-history', [OrderOperationHistoryController::class, 'index'])
        ->name('customers.orders.operation-history');
    Route::get('customers/orders/{order}/purchase-history', [CustomerPurchaseHistoryController::class, 'index'])
        ->name('customers.orders.purchase-history');
    Route::get('customers/orders/{order}/data-view-history', [CustomerDataViewHistoryController::class, 'index'])
        ->name('customers.orders.data-view-history');
    Route::get('customers/orders/{order}/messages', [CustomerInternalMessageController::class, 'index'])
        ->name('customers.orders.messages.index');
    Route::post('customers/orders/{order}/messages', [CustomerInternalMessageController::class, 'store'])
        ->name('customers.orders.messages.store');
    Route::get('customers/orders/{order}/pancake-messages', [PancakeCustomerMessageController::class, 'index'])
        ->name('customers.orders.pancake-messages.index');
    Route::post('customers/orders/{order}/pancake-messages', [PancakeCustomerMessageController::class, 'store'])
        ->name('customers.orders.pancake-messages.store');
    Route::get('customers/orders/{order}/supplement-packets', [CustomerSupplementPacketController::class, 'index'])
        ->name('customers.orders.supplement-packets.index');
    Route::post(
        'customers/orders/{order}/supplement-packets/{leadIngestion}/review',
        [CustomerSupplementPacketController::class, 'store'],
    )->name('customers.orders.supplement-packets.review');

    Route::get('customers/export', [CustomerProfileBulkActionController::class, 'export'])
        ->name('customers.export');
    Route::post('customers/bulk/reallocate-now', [CustomerProfileBulkActionController::class, 'reallocateNow'])
        ->name('customers.bulk.reallocate-now');
    Route::post('customers/bulk/queue-reallocation', [CustomerProfileBulkActionController::class, 'queueReallocation'])
        ->name('customers.bulk.queue-reallocation');
    Route::post('customers/bulk/recall', [CustomerProfileBulkActionController::class, 'recall'])
        ->name('customers.bulk.recall');
    Route::delete('customers/bulk/operation-history', [CustomerProfileBulkActionController::class, 'deleteOperationHistory'])
        ->name('customers.bulk.operation-history.destroy');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::redirect('ld/facebook/cau-hinh-don-vi', '/admin/integrations/facebook-pages', 301)->name('legacy.facebook.unit-config');
    Route::redirect('ld/unit-admin/thiet-lap-kpi', '/admin/ceo/business-plan/monthly', 301)->name('legacy.ceo.monthly-kpi');
    Route::redirect('ld/thong-ke/lap-ke-hoach-kinh-doanh', '/admin/ceo/business-plan/yearly', 301)->name('legacy.ceo.yearly-business-plan');
    Route::redirect('ld/unit-admin/danh-muc-kpi', '/admin/ceo/business-plan/kpi-catalog', 301)->name('legacy.ceo.kpi-catalog');
    Route::redirect('ld/unit-admin/thiet-lap-thuong-theo-doanh-so', '/admin/ceo/business-plan/revenue-bonus', 301)->name('legacy.ceo.revenue-bonus');
    Route::redirect('ld/ceo/power-dashboard', '/admin/reports/power-dashboard', 301)->name('legacy.ceo.power-dashboard');
    Route::redirect('settings', '/admin/system/settings', 301)->name('legacy.system.settings');
    Route::redirect('connect-shop-list', '/admin/ecommerce/connect-shops', 301)->name('legacy.ecommerce.connect-shops');
    Route::redirect('ld/ecommerce/e-connect-shop-list', '/admin/ecommerce/connect-shops', 301)->name('legacy.ecommerce.connect-shops.ld');
    Route::redirect('connect-product-list', '/admin/ecommerce/connect-products', 301)->name('legacy.ecommerce.connect-products');
    Route::redirect('ld/ecommerce/e-connect-product-list', '/admin/ecommerce/connect-products', 301)->name('legacy.ecommerce.connect-products.ld');
    Route::redirect('error-order-list', '/admin/ecommerce/sync-errors', 301)->name('legacy.ecommerce.sync-errors');
    Route::redirect('ld/ecommerce/e-order-sync-error-list', '/admin/ecommerce/sync-errors', 301)->name('legacy.ecommerce.sync-errors.ld');

    // Mỗi mã menu là một màn hình nghiệp vụ với controller và route tĩnh riêng.
    // Các file template chỉ cung cấp content + dialog; header/sidebar do AppLayout quản lý.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('company/profile', [\App\Http\Controllers\Admin\CompanyProfileController::class, 'index'])
            ->name('company.profile');
        Route::put('company/profile', [\App\Http\Controllers\Admin\CompanyProfileController::class, 'update'])
            ->name('company.profile.update');

        require __DIR__.'/pushsale_pages.php';
    });

    Route::middleware('role:'.User::ROLE_ADMIN)->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        // "Tổng quan vận hành" đã gộp vào "Tổng quan điều hành" (dashboard). Giữ redirect cho link cũ.
        Route::get('reports/business', fn () => redirect()->route('admin.reports.system-business'))->name('reports.business');
        foreach ((array) config('pushsale_report_routes', []) as $reportKey => $routeConfig) {
            $adminPath = (string) ($routeConfig['admin_path'] ?? '');
            $routeName = (string) ($routeConfig['route_name'] ?? '');

            if ($adminPath === '' || $routeName === '' || ! str_starts_with($adminPath, '/admin/')) {
                continue;
            }

            Route::get(substr($adminPath, strlen('/admin/')), function (Illuminate\Http\Request $request, ExtraReportController $controller) use ($reportKey) {
                return $controller($request, (string) $reportKey);
            })->name($routeName);
        }
        Route::get('reports/ceo', CeoReportController::class)->name('reports.ceo');
        Route::get('reports/ceo-dashboard-v2', CeoReportController::class)->name('reports.ceo-dashboard-v2');
        Route::redirect('sales/reports/ceo-dashboard-v2', '/admin/reports/ceo-dashboard-v2', 301)->name('sales.reports.ceo-dashboard-v2.redirect');
        Route::get('reports/hourly', HourlyStatsController::class)->name('reports.hourly');
        Route::get('reports/team-leaders', TeamLeaderStatsController::class)->name('reports.team-leaders');
        Route::get('reports/extra/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
        Route::get('marketing/dashboard', MarketingDashboardController::class)->name('marketing.dashboard');
        Route::get('marketing/dashboard/chart', [MarketingDashboardDataController::class, 'chart'])->name('marketing.dashboard.chart');
        Route::get('marketing/dashboard/daily-metrics', [MarketingDashboardDataController::class, 'dailyMetrics'])->name('marketing.dashboard.daily-metrics');
        Route::put('marketing/dashboard/daily-metrics', [MarketingDashboardDataController::class, 'saveDailyMetrics'])->name('marketing.dashboard.daily-metrics.update');
        Route::get('marketing/dashboard/export', [MarketingDashboardDataController::class, 'export'])->name('marketing.dashboard.export');
        Route::get('landing-approvals', [LandingApprovalController::class, 'index'])->name('landing-approvals.index');
        Route::post('landing-approvals/{campaign}/approve', [LandingApprovalController::class, 'approve'])->name('landing-approvals.approve');
        Route::post('landing-approvals/{campaign}/reject', [LandingApprovalController::class, 'reject'])->name('landing-approvals.reject');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::get('marketing/revenue', MarketingRevenueReportController::class)->name('marketing.revenue');
        Route::get('marketing/campaign-report', CampaignReportController::class)->name('marketing.campaign-report');
        Route::patch('marketing/campaigns/{campaign}/budget', [CampaignBudgetController::class, 'update'])->name('marketing.campaigns.budget');
        Route::get('sales/revenue', SaleRevenueReportController::class)->name('sales.revenue');
        Route::get('sales/performance', AdminSalesPerformanceReportController::class)->name('sales.performance');
        Route::get('sales/workspace', AdminSalesWorkspaceController::class)->name('sales.workspace');
        Route::post('sales/orders/bulk-close', [SaleBulkCloseController::class, 'store'])->name('sales.orders.bulk-close');
        Route::post('sales/orders/{order}/call', [SaleOperationCallController::class, 'store'])->name('sales.orders.call');
        Route::post('sales/orders/{order}/operation-status', [SaleOperationStatusController::class, 'update'])->name('sales.orders.operation-status');
        Route::patch('sales/orders/{order}/operation-note', [SaleOperationNoteController::class, 'update'])->name('sales.orders.operation-note');
        Route::patch('sales/orders/{order}/desired-delivery-date', [DesiredDeliveryDateController::class, 'update'])->name('sales.orders.desired-delivery-date');
        Route::post('sales/orders/{order}/details', [SaleOperationOrderController::class, 'update'])->name('sales.orders.details');
        Route::delete('sales/orders/{order}', [SaleOrderDeletionController::class, 'destroy'])->name('sales.orders.destroy');
        Route::post('sales/orders/{order}/close', [OrderClosingController::class, 'store'])->name('sales.orders.close');
        Route::post('sales/leads/manual', [ManualLeadController::class, 'store'])->name('sales.leads.manual');
        Route::get('accounting', AccountingOperationsController::class)->name('accounting');
        Route::get('warehouse/operations', WarehouseOperationsController::class)->name('warehouse.operations');
        Route::post('warehouse/orders/bulk/export', [WarehouseOrderActionController::class, 'bulkExport'])->name('warehouse.orders.bulk.export');
        Route::post('warehouse/orders/bulk/invoices', [WarehouseOrderActionController::class, 'bulkInvoices'])->name('warehouse.orders.bulk.invoices');
        Route::post('warehouse/orders/bulk/update-by-code', [WarehouseOrderActionController::class, 'bulkUpdateByCode'])->name('warehouse.orders.bulk.update-by-code');
        Route::patch('warehouse/orders/{order}/desired-delivery', [WarehouseOrderActionController::class, 'desiredDelivery'])->name('warehouse.orders.desired-delivery');
        Route::post('warehouse/orders/{order}/blacklist', [WarehouseOrderActionController::class, 'blacklist'])->name('warehouse.orders.blacklist');
        Route::patch('warehouse/orders/{order}/care', [WarehouseOrderActionController::class, 'care'])->name('warehouse.orders.care');
        Route::patch('warehouse/orders/{order}/delivery-status', [WarehouseOrderActionController::class, 'deliveryStatus'])->name('warehouse.orders.delivery-status');
        Route::put('warehouse/orders/{order}', [WarehouseOrderActionController::class, 'updateOrder'])->name('warehouse.orders.update');
        Route::post('warehouse/orders/{order}/split', [WarehouseOrderActionController::class, 'split'])->name('warehouse.orders.split');
        Route::post('warehouse/orders/{order}/printed', [WarehouseOrderActionController::class, 'printed'])->name('warehouse.orders.printed');
        Route::post('warehouse/orders/{order}/return-receipt', [WarehouseOrderActionController::class, 'receiveReturn'])->name('warehouse.orders.return-receipt');
        Route::get('warehouse/inventory', InventoryController::class)->name('warehouse.inventory');
        Route::post('warehouse/inventory/intake', [InventoryMovementController::class, 'intake'])->name('warehouse.inventory.intake');
        Route::post('warehouse/inventory/export', [InventoryMovementController::class, 'export'])->name('warehouse.inventory.export');
        Route::get('warehouse/movements', MovementHistoryController::class)->name('warehouse.movements');
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
        Route::get('warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
        Route::delete('warehouse-inventories/{inventory}', [WarehouseInventoryController::class, 'destroy'])->name('warehouse-inventories.destroy');
        Route::resource('teams', TeamController::class)->except(['show']);
        Route::get('products/import', [ProductController::class, 'importPage'])->name('products.import-page');
        Route::get('products/import/sample', [ProductController::class, 'importTemplate'])->name('products.import-template');
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::post('products/categories', [ProductController::class, 'storeCategory'])->name('products.categories.store');
        Route::patch('products/categories/{category}', [ProductController::class, 'updateCategory'])->name('products.categories.update');
        Route::delete('products/categories/{category}', [ProductController::class, 'destroyCategory'])->name('products.categories.destroy');
        Route::post('products/attributes', [ProductController::class, 'storeAttribute'])->name('products.attributes.store');
        Route::patch('products/attributes/{attribute}', [ProductController::class, 'updateAttribute'])->name('products.attributes.update');
        Route::delete('products/attributes/{attribute}', [ProductController::class, 'destroyAttribute'])->name('products.attributes.destroy');
        Route::post('products/attribute-values', [ProductController::class, 'storeAttributeValue'])->name('products.attribute-values.store');
        Route::patch('products/attribute-values/{attributeValue}', [ProductController::class, 'updateAttributeValue'])->name('products.attribute-values.update');
        Route::delete('products/attribute-values/{attributeValue}', [ProductController::class, 'destroyAttributeValue'])->name('products.attribute-values.destroy');
        Route::resource('products', ProductController::class)->except(['show']);
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::delete('leads/{leadIngestion}', [LeadIngestionController::class, 'destroy'])->name('leads.destroy');
        Route::patch('leads/{leadIngestion}/review', [LeadReviewController::class, 'update'])->name('leads.review');
        Route::delete('failed-orders/{failedPartnerOrder}', [FailedPartnerOrderController::class, 'destroy'])->name('failed-orders.destroy');
        Route::get('orders/failed', FailedOrdersController::class)->name('orders.failed');
        Route::get('rankings', RankingController::class)->name('rankings');
        Route::get('ecommerce/connect-shops', [EcommerceController::class, 'shops'])->name('ecommerce.connect-shops');
        Route::post('ecommerce/connect-shops', [EcommerceController::class, 'storeShop'])->name('ecommerce.connect-shops.store');
        Route::patch('ecommerce/connect-shops/{shop}', [EcommerceController::class, 'updateShop'])->name('ecommerce.connect-shops.update');
        Route::delete('ecommerce/connect-shops/{shop}', [EcommerceController::class, 'destroyShop'])->name('ecommerce.connect-shops.destroy');
        Route::get('ecommerce/connect-products', [EcommerceController::class, 'products'])->name('ecommerce.connect-products');
        Route::post('ecommerce/connect-products/sync', [EcommerceController::class, 'syncProducts'])->name('ecommerce.connect-products.sync');
        Route::patch('ecommerce/connect-products/{link}', [EcommerceController::class, 'mapProduct'])->name('ecommerce.connect-products.map');
        Route::get('ecommerce/sync-errors', [EcommerceController::class, 'errors'])->name('ecommerce.sync-errors');
        Route::post('ecommerce/sync-errors/fetch-missing-orders', [EcommerceController::class, 'fetchMissingOrders'])->name('ecommerce.sync-errors.fetch-missing');
        Route::get('ecommerce/sync-errors/export', [EcommerceController::class, 'exportErrors'])->name('ecommerce.sync-errors.export');
        Route::get('integrations', [IntegrationsController::class, 'index'])->name('integrations.index');
        Route::put('integrations/{platform}', [IntegrationsController::class, 'update'])->name('integrations.update');
        Route::post('integrations/{platform}/test', [IntegrationsController::class, 'testWebhook'])->name('integrations.test');
        Route::get('system-monitor', [SystemMonitorController::class, 'index'])->name('system-monitor.index');
        Route::get('system-monitor/events/{inboundEvent}', [SystemMonitorController::class, 'show'])->name('system-monitor.show');
        Route::get('system/settings', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'index'])->name('system.settings');
        Route::put('system/settings', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'update'])->name('system.settings.update');
        Route::get('leads', [DataDistributionController::class, 'index'])->name('leads.index');
        Route::post('leads/distribute', [DataDistributionController::class, 'store'])->name('leads.distribute');
        Route::get('customers', CustomerProfileController::class)->name('customers.index');
        Route::post('leads/allocate', [ManualLeadAllocationController::class, 'store'])->name('leads.allocate');
        Route::post('leads/allocation-mode', [ManualLeadAllocationController::class, 'updateMode'])->name('leads.allocation-mode');
        Route::post('leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');
        Route::post('leads/import', [ManualLeadController::class, 'import'])->name('leads.import');
        Route::get('leads/import-template', [ManualLeadController::class, 'template'])->name('leads.import-template');
        Route::post('company/lead-template', [CompanySettingsController::class, 'updateLeadTemplate'])->name('company.lead-template');
        Route::delete('company/lead-template', [CompanySettingsController::class, 'destroyLeadTemplate'])->name('company.lead-template.destroy');
        Route::get('shipping-partners', [ShippingPartnersController::class, 'index'])->name('shipping-partners.index');
        Route::get('ld/unit-admin/cau-hinh-giao-hang', [ShippingPartnersController::class, 'index'])->name('legacy.unit.shipping-config');
        Route::put('shipping-partners/{provider}', [ShippingPartnersController::class, 'update'])->name('shipping-partners.update');
        Route::put('shipping-default', [ShippingPartnersController::class, 'updateDefault'])->name('shipping-partners.default');
        Route::get('shipping/reconciliation', ShippingReconciliationController::class)->name('shipping.reconciliation');
        Route::post('shipping/reconciliation/import', [CarrierSettlementController::class, 'import'])->name('shipping.reconciliation.import');
        Route::post('shipping/reconciliation/sync', [CarrierSettlementController::class, 'syncApi'])->name('shipping.reconciliation.sync');
        Route::get('shipping/orders', [ShippingOrderController::class, 'index'])->name('shipping.orders');
        Route::get('shipping/orders/{order}/detail', [ShippingOrderController::class, 'detail'])->name('shipping.orders.detail');
        Route::post('shipping/orders/{order}/create-shipment', [ShippingOrderController::class, 'createShipment'])->name('shipping.orders.create-shipment');
        Route::post('shipping/orders/{order}/sync-status', [ShippingOrderController::class, 'syncStatus'])->name('shipping.orders.sync-status');
        Route::post('shipping/orders/{order}/calculate-fee', [ShippingOrderController::class, 'calculateFee'])->name('shipping.orders.calculate-fee');
        Route::post('shipping/orders/{order}/cancel-shipment', [ShippingOrderController::class, 'cancelShipment'])->name('shipping.orders.cancel-shipment');
        Route::get('shipping/orders/{order}/label', [ShippingOrderController::class, 'printLabel'])->name('shipping.orders.label');
        Route::post('shipping/orders/{order}/receive-return', [OrderReturnController::class, 'store'])->name('shipping.orders.receive-return');
        Route::post('shipping-partners/{provider}/test/{action}', ShippingPartnerTestController::class)->name('shipping-partners.test');
    });

    // Quản lý nhân viên: mở cho nhánh trên có quyền HR (không chỉ admin). Chặn qua middleware permissions + phân cấp.
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('users/bulk', [UserController::class, 'storeBulk'])
            ->name('users.bulk.store');
        Route::patch('users/{user}/quick-update', [UserController::class, 'quickUpdate'])
            ->name('users.quick-update');
        Route::patch('users/{user}/operational-status', [UserController::class, 'updateOperationalStatus'])
            ->name('users.operational-status');
        Route::patch('users/{user}/password', [UserController::class, 'updatePassword'])
            ->name('users.password');
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::middleware('role:'.User::ROLE_SALES)->prefix('sales')->name('sales.')->group(function () {
        Route::get('dashboard', SalesDashboardController::class)->name('dashboard');
        Route::get('customers', CustomerProfileController::class)->name('customers');
        Route::get('rankings', SalesRankingController::class)->name('rankings');
        Route::get('performance', SalesPerformanceReportController::class)->name('performance');
        Route::get('workspace', OperationController::class)->name('workspace');
        Route::post('leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');
        Route::post('orders/bulk-close', [SaleBulkCloseController::class, 'store'])->name('orders.bulk-close');
        Route::post('orders/{order}/call', [SaleOperationCallController::class, 'store'])->name('orders.call');
        Route::post('orders/{order}/operation-status', [SaleOperationStatusController::class, 'update'])->name('orders.operation-status');
        Route::patch('orders/{order}/operation-note', [SaleOperationNoteController::class, 'update'])->name('orders.operation-note');
        Route::patch('orders/{order}/desired-delivery-date', [DesiredDeliveryDateController::class, 'update'])->name('orders.desired-delivery-date');
        Route::post('orders/{order}/details', [SaleOperationOrderController::class, 'update'])->name('orders.details');
        Route::delete('orders/{order}', [SaleOrderDeletionController::class, 'destroy'])->name('orders.destroy');
        Route::post('orders/{order}/close', [OrderClosingController::class, 'store'])->name('orders.close');
        Route::get('customers', CustomerProfileController::class)->name('customers');
        Route::get('reports/hourly', HourlyStatsController::class)->name('reports.hourly');
        Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
    });

    Route::middleware('role:'.User::ROLE_MARKETING)->prefix('marketing')->name('marketing.')->group(function () {
        Route::get('dashboard', RoleMarketingDashboardController::class)->name('dashboard');
        Route::get('customers', CustomerProfileController::class)->name('customers');
        Route::get('rankings', MarketingRankingController::class)->name('rankings');
        Route::get('workspace', MarketingDashboardController::class)->name('workspace');
        Route::get('workspace/chart', [MarketingDashboardDataController::class, 'chart'])->name('workspace.chart');
        Route::get('workspace/daily-metrics', [MarketingDashboardDataController::class, 'dailyMetrics'])->name('workspace.daily-metrics');
        Route::put('workspace/daily-metrics', [MarketingDashboardDataController::class, 'saveDailyMetrics'])->name('workspace.daily-metrics.update');
        Route::get('workspace/export', [MarketingDashboardDataController::class, 'export'])->name('workspace.export');
        Route::redirect('campaigns', '/admin/marketing/landing-connections', 301)->name('campaigns.index');
        Route::redirect('campaigns/create', '/admin/marketing/landing-connections', 301)->name('campaigns.create');
        Route::redirect('campaigns/{campaign}/edit', '/admin/marketing/landing-connections', 301)->name('campaigns.edit');
        Route::post('campaigns', function () {
            abort(410, 'Luồng tạo chiến dịch đã được thay bằng Kết nối landing.');
        })->name('campaigns.store');
        Route::put('campaigns/{campaign}', function () {
            abort(410, 'Luồng cập nhật chiến dịch đã được thay bằng Kết nối landing.');
        })->whereNumber('campaign')->name('campaigns.update');
        Route::delete('campaigns/{campaign}', function () {
            abort(410, 'Luồng xóa chiến dịch đã được thay bằng Kết nối landing.');
        })->whereNumber('campaign')->name('campaigns.destroy');
        Route::get('landing-approvals', [LandingApprovalController::class, 'index'])->name('landing-approvals.index');
        Route::post('landing-approvals/{campaign}/approve', [LandingApprovalController::class, 'approve'])->name('landing-approvals.approve');
        Route::post('landing-approvals/{campaign}/reject', [LandingApprovalController::class, 'reject'])->name('landing-approvals.reject');
        Route::get('revenue', MarketingRevenueReportController::class)->name('revenue');
        Route::get('campaign-report', MarketingCampaignReportController::class)->name('campaign-report');
        Route::patch('campaigns/{campaign}/budget', [CampaignBudgetController::class, 'update'])->name('campaigns.budget');
        Route::get('reports/hourly', HourlyStatsController::class)->name('reports.hourly');
        Route::get('reports/team-leaders', TeamLeaderStatsController::class)->name('reports.team-leaders');
        Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
        Route::get('leads', [DataDistributionController::class, 'index'])->name('leads.index');
        Route::post('leads/distribute', [DataDistributionController::class, 'store'])->name('leads.distribute');
        Route::post('leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');
        Route::post('leads/import', [ManualLeadController::class, 'import'])->name('leads.import');
        Route::get('leads/import-template', [ManualLeadController::class, 'template'])->name('leads.import-template');
    });

    Route::middleware('role:'.User::ROLE_WAREHOUSE)->prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('dashboard', WarehouseDashboardController::class)->name('dashboard');
        Route::get('workspace', WarehouseOperationsController::class)->name('workspace');
        Route::post('orders/bulk/export', [WarehouseOrderActionController::class, 'bulkExport'])->name('orders.bulk.export');
        Route::post('orders/bulk/invoices', [WarehouseOrderActionController::class, 'bulkInvoices'])->name('orders.bulk.invoices');
        Route::post('orders/bulk/update-by-code', [WarehouseOrderActionController::class, 'bulkUpdateByCode'])->name('orders.bulk.update-by-code');
        Route::patch('orders/{order}/desired-delivery', [WarehouseOrderActionController::class, 'desiredDelivery'])->name('orders.desired-delivery');
        Route::post('orders/{order}/blacklist', [WarehouseOrderActionController::class, 'blacklist'])->name('orders.blacklist');
        Route::patch('orders/{order}/care', [WarehouseOrderActionController::class, 'care'])->name('orders.care');
        Route::patch('orders/{order}/delivery-status', [WarehouseOrderActionController::class, 'deliveryStatus'])->name('orders.delivery-status');
        Route::put('orders/{order}', [WarehouseOrderActionController::class, 'updateOrder'])->name('orders.update');
        Route::post('orders/{order}/split', [WarehouseOrderActionController::class, 'split'])->name('orders.split');
        Route::post('orders/{order}/printed', [WarehouseOrderActionController::class, 'printed'])->name('orders.printed');
        Route::post('orders/{order}/return-receipt', [WarehouseOrderActionController::class, 'receiveReturn'])->name('orders.return-receipt');
        Route::get('inventory', InventoryController::class)->name('inventory');
        Route::get('customers', CustomerProfileController::class)->name('customers.index');
        Route::post('inventory/intake', [InventoryMovementController::class, 'intake'])->name('inventory.intake');
        Route::post('inventory/export', [InventoryMovementController::class, 'export'])->name('inventory.export');
        Route::get('shipping/orders', [App\Http\Controllers\Warehouse\ShippingOrderController::class, 'index'])->name('shipping.orders');
        Route::get('shipping/orders/{order}/detail', [ShippingOrderController::class, 'detail'])->name('shipping.orders.detail');
        Route::post('shipping/orders/{order}/create-shipment', [ShippingOrderController::class, 'createShipment'])->name('shipping.orders.create-shipment');
        Route::post('shipping/orders/{order}/sync-status', [ShippingOrderController::class, 'syncStatus'])->name('shipping.orders.sync-status');
        Route::post('shipping/orders/{order}/calculate-fee', [ShippingOrderController::class, 'calculateFee'])->name('shipping.orders.calculate-fee');
        Route::post('shipping/orders/{order}/cancel-shipment', [ShippingOrderController::class, 'cancelShipment'])->name('shipping.orders.cancel-shipment');
        Route::get('shipping/orders/{order}/label', [ShippingOrderController::class, 'printLabel'])->name('shipping.orders.label');
        Route::post('shipping/orders/{order}/receive-return', [OrderReturnController::class, 'store'])->name('shipping.orders.receive-return');
        Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
    });

    Route::middleware('role:'.User::ROLE_ACCOUNTING)->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('dashboard', AccountingDashboardController::class)->name('dashboard');
        Route::get('workspace', AccountingOperationsController::class)->name('workspace');
        Route::get('customers', CustomerProfileController::class)->name('customers.index');
        Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
    });

    Route::middleware('role:'.User::ROLE_ALLOCATOR)->prefix('allocator')->name('allocator.')->group(function () {
        Route::get('dashboard', AllocatorDashboardController::class)->name('dashboard');
        Route::get('workspace', [DataDistributionController::class, 'index'])->name('workspace');
        Route::post('leads/distribute', [DataDistributionController::class, 'store'])->name('leads.distribute');
        Route::post('leads/allocate', [ManualLeadAllocationController::class, 'store'])->name('leads.allocate');
        Route::patch('leads/{leadIngestion}/review', [LeadReviewController::class, 'update'])->name('leads.review');
        Route::post('leads/allocation-mode', [ManualLeadAllocationController::class, 'updateMode'])->name('leads.allocation-mode');
        Route::post('leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');
        Route::post('leads/import', [ManualLeadController::class, 'import'])->name('leads.import');
        Route::get('leads/import-template', [ManualLeadController::class, 'template'])->name('leads.import-template');
        Route::get('customers', CustomerProfileController::class)->name('customers.index');
        Route::get('reports/{report}', AllocatorReportController::class)->where('report', '[a-z0-9\-]+')->name('reports');
    });

    Route::middleware('platform')->prefix('platform')->name('platform.')->group(function () {
        Route::get('companies', [PlatformCompanyController::class, 'index'])->name('companies.index');
        Route::post('companies', [PlatformCompanyController::class, 'store'])->name('companies.store');
        Route::get('companies/{company}/accounts', [PlatformCompanyController::class, 'accounts'])->name('companies.accounts');
        Route::get('companies/{company}/admins', [PlatformCompanyController::class, 'admins'])->name('companies.admins');
        Route::post('companies/{company}/admins', [PlatformCompanyController::class, 'storeAdmin'])->name('companies.admins.store');
        Route::put('companies/{company}/admins/{admin}', [PlatformCompanyController::class, 'updateAdmin'])->name('companies.admins.update');
        Route::delete('companies/{company}/admins/{admin}', [PlatformCompanyController::class, 'destroyAdmin'])->name('companies.admins.destroy');
        Route::put('companies/{company}', [PlatformCompanyController::class, 'update'])->name('companies.update');
        Route::post('companies/{company}/toggle', [PlatformCompanyController::class, 'toggle'])->name('companies.toggle');
        Route::get('settings', [App\Http\Controllers\Platform\SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [App\Http\Controllers\Platform\SettingsController::class, 'update'])->name('settings.update');
    });
});
