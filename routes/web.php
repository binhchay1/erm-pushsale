<?php

use App\Http\Controllers\Accounting\DashboardController as AccountingDashboardController;
use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;
use App\Http\Controllers\Admin\BusinessOverviewController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FailedPartnerOrderController;
use App\Http\Controllers\Admin\IntegrationsController;
use App\Http\Controllers\Admin\LandingApprovalController;
use App\Http\Controllers\Admin\LeadIngestionController;
use App\Http\Controllers\Admin\LeadsLogController;
use App\Http\Controllers\Admin\ManualLeadAllocationController;
use App\Http\Controllers\Admin\Marketing\CampaignBudgetController;
use App\Http\Controllers\Admin\Marketing\CampaignController;
use App\Http\Controllers\Admin\Marketing\CampaignReportController;
use App\Http\Controllers\Admin\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Admin\Marketing\RevenueReportController as MarketingRevenueReportController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\Orders\FailedOrdersController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\Reports\CeoReportController;
use App\Http\Controllers\Admin\Sales\PerformanceReportController as AdminSalesPerformanceReportController;
use App\Http\Controllers\Admin\Sales\RevenueReportController as SaleRevenueReportController;
use App\Http\Controllers\Admin\ShippingOrderController;
use App\Http\Controllers\Admin\ShippingPartnersController;
use App\Http\Controllers\Admin\ShippingPartnerTestController;
use App\Http\Controllers\Admin\ShippingReconciliationController;
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
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\Marketing\CampaignReportController as MarketingCampaignReportController;
use App\Http\Controllers\Marketing\DashboardController as RoleMarketingDashboardController;
use App\Http\Controllers\Marketing\RankingController as MarketingRankingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\Platform\CompanyController as PlatformCompanyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ExtraReportController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\Sales\DashboardController as SalesDashboardController;
use App\Http\Controllers\Sales\OperationController;
use App\Http\Controllers\Sales\OrderClosingController;
use App\Http\Controllers\Sales\PerformanceReportController as SalesPerformanceReportController;
use App\Http\Controllers\Sales\RankingController as SalesRankingController;
use App\Http\Controllers\Sales\SaleOperationCallController;
use App\Http\Controllers\Sales\SaleOperationStatusController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Warehouse\DashboardController as WarehouseDashboardController;
use App\Http\Controllers\Warehouse\OrderReturnController;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['web', 'auth', 'tenant']]);

Route::post('locale', [LocaleController::class, 'update'])->name('locale.update');

Route::get('/', HomeController::class)->name('home');

// Trang vệ tinh công khai (SEO) — không cần đăng nhập.
Route::get('features', [MarketingController::class, 'features'])->name('marketing.features');
Route::get('solutions', [MarketingController::class, 'solutions'])->name('marketing.solutions');
Route::get('about', [MarketingController::class, 'about'])->name('marketing.about');
Route::get('contact', [MarketingController::class, 'contact'])->name('marketing.contact');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Sơ đồ tổ chức — test RBAC: /org-chart (xem OrgChartController)
    Route::get('org-chart', [OrgChartController::class, 'index'])->name('org-chart.index');

    Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('role:'.User::ROLE_ADMIN)->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('reports/business', BusinessOverviewController::class)->name('reports.business');
        Route::get('reports/ceo', CeoReportController::class)->name('reports.ceo');
        Route::get('reports/extra/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
        Route::get('marketing/dashboard', MarketingDashboardController::class)->name('marketing.dashboard');
        Route::get('landing-approvals', [LandingApprovalController::class, 'index'])->name('landing-approvals.index');
        Route::post('landing-approvals/{campaign}/approve', [LandingApprovalController::class, 'approve'])->name('landing-approvals.approve');
        Route::get('marketing/revenue', MarketingRevenueReportController::class)->name('marketing.revenue');
        Route::get('marketing/campaign-report', CampaignReportController::class)->name('marketing.campaign-report');
        Route::patch('marketing/campaigns/{campaign}/budget', [CampaignBudgetController::class, 'update'])->name('marketing.campaigns.budget');
        Route::get('sales/revenue', SaleRevenueReportController::class)->name('sales.revenue');
        Route::get('sales/performance', AdminSalesPerformanceReportController::class)->name('sales.performance');
        Route::get('accounting', AccountingOperationsController::class)->name('accounting');
        Route::get('warehouse/operations', WarehouseOperationsController::class)->name('warehouse.operations');
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
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('teams', TeamController::class)->except(['show']);
        Route::resource('products', ProductController::class)->except(['show']);
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::delete('leads/{leadIngestion}', [LeadIngestionController::class, 'destroy'])->name('leads.destroy');
        Route::delete('failed-orders/{failedPartnerOrder}', [FailedPartnerOrderController::class, 'destroy'])->name('failed-orders.destroy');
        Route::get('orders/failed', FailedOrdersController::class)->name('orders.failed');
        Route::get('rankings', RankingController::class)->name('rankings');
        Route::get('integrations', [IntegrationsController::class, 'index'])->name('integrations.index');
        Route::put('integrations/{platform}', [IntegrationsController::class, 'update'])->name('integrations.update');
        Route::post('integrations/{platform}/test', [IntegrationsController::class, 'testWebhook'])->name('integrations.test');
        Route::get('leads', LeadsLogController::class)->name('leads.index');
        Route::post('leads/allocate', [ManualLeadAllocationController::class, 'store'])->name('leads.allocate');
        Route::post('leads/allocation-mode', [ManualLeadAllocationController::class, 'updateMode'])->name('leads.allocation-mode');
        Route::get('shipping-partners', [ShippingPartnersController::class, 'index'])->name('shipping-partners.index');
        Route::put('shipping-partners/{provider}', [ShippingPartnersController::class, 'update'])->name('shipping-partners.update');
        Route::get('shipping/reconciliation', ShippingReconciliationController::class)->name('shipping.reconciliation');
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

    Route::middleware('role:'.User::ROLE_SALES)->prefix('sales')->name('sales.')->group(function () {
        Route::get('dashboard', SalesDashboardController::class)->name('dashboard');
        Route::get('rankings', SalesRankingController::class)->name('rankings');
        Route::get('performance', SalesPerformanceReportController::class)->name('performance');
        Route::get('workspace', OperationController::class)->name('workspace');
        Route::post('orders/{order}/call', [SaleOperationCallController::class, 'store'])->name('orders.call');
        Route::post('orders/{order}/operation-status', [SaleOperationStatusController::class, 'update'])->name('orders.operation-status');
        Route::post('orders/{order}/close', [OrderClosingController::class, 'store'])->name('orders.close');
        Route::get('customers', CustomerProfileController::class)->name('customers');
        Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
    });

    Route::middleware('role:'.User::ROLE_MARKETING)->prefix('marketing')->name('marketing.')->group(function () {
        Route::get('dashboard', RoleMarketingDashboardController::class)->name('dashboard');
        Route::get('rankings', MarketingRankingController::class)->name('rankings');
        Route::get('workspace', MarketingDashboardController::class)->name('workspace');
        Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
        Route::put('campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::get('revenue', MarketingRevenueReportController::class)->name('revenue');
        Route::get('campaign-report', MarketingCampaignReportController::class)->name('campaign-report');
        Route::patch('campaigns/{campaign}/budget', [CampaignBudgetController::class, 'update'])->name('campaigns.budget');
        Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
    });

    Route::middleware('role:'.User::ROLE_WAREHOUSE)->prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('dashboard', WarehouseDashboardController::class)->name('dashboard');
        Route::get('workspace', WarehouseOperationsController::class)->name('workspace');
        Route::get('inventory', InventoryController::class)->name('inventory');
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
        Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
    });

    Route::middleware('role:'.User::ROLE_ALLOCATOR)->prefix('allocator')->name('allocator.')->group(function () {
        Route::get('dashboard', AllocatorDashboardController::class)->name('dashboard');
        Route::get('workspace', LeadsLogController::class)->name('workspace');
        Route::post('leads/allocate', [ManualLeadAllocationController::class, 'store'])->name('leads.allocate');
        Route::post('leads/allocation-mode', [ManualLeadAllocationController::class, 'updateMode'])->name('leads.allocation-mode');
        Route::get('reports/{report}', AllocatorReportController::class)->where('report', '[a-z0-9\-]+')->name('reports');
    });

    Route::middleware('platform')->prefix('platform')->name('platform.')->group(function () {
        Route::get('companies', [PlatformCompanyController::class, 'index'])->name('companies.index');
        Route::post('companies', [PlatformCompanyController::class, 'store'])->name('companies.store');
        Route::get('companies/{company}/accounts', [PlatformCompanyController::class, 'accounts'])->name('companies.accounts');
        Route::put('companies/{company}', [PlatformCompanyController::class, 'update'])->name('companies.update');
        Route::post('companies/{company}/toggle', [PlatformCompanyController::class, 'toggle'])->name('companies.toggle');
        Route::get('settings', [App\Http\Controllers\Platform\SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [App\Http\Controllers\Platform\SettingsController::class, 'update'])->name('settings.update');
    });
});
