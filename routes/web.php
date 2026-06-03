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
use App\Http\Controllers\Admin\Marketing\CampaignController;
use App\Http\Controllers\Admin\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Admin\Marketing\RevenueReportController as MarketingRevenueReportController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\Orders\FailedOrdersController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\Reports\CeoReportController;
use App\Http\Controllers\Admin\Sales\RevenueReportController as SaleRevenueReportController;
use App\Http\Controllers\Admin\ShippingOrderController;
use App\Http\Controllers\Admin\ShippingPartnersController;
use App\Http\Controllers\Admin\ShippingPartnerTestController;
use App\Http\Controllers\Admin\ShippingReconciliationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\Warehouse\InventoryController;
use App\Http\Controllers\Admin\Warehouse\OperationsController as WarehouseOperationsController;
use App\Http\Controllers\Admin\Warehouse\WarehouseController;
use App\Http\Controllers\Admin\WarehouseInventoryController;
use App\Http\Controllers\Allocator\DashboardController as AllocatorDashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Marketing\DashboardController as RoleMarketingDashboardController;
use App\Http\Controllers\Marketing\RankingController as MarketingRankingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\Sales\DashboardController as SalesDashboardController;
use App\Http\Controllers\Sales\OperationController;
use App\Http\Controllers\Sales\OrderClosingController;
use App\Http\Controllers\Sales\RankingController as SalesRankingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Warehouse\DashboardController as WarehouseDashboardController;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(LoginController::homeFor(auth()->user()));
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::middleware('role:'.User::ROLE_ADMIN)->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('reports/business', BusinessOverviewController::class)->name('reports.business');
        Route::get('reports/ceo', CeoReportController::class)->name('reports.ceo');
        Route::get('marketing/dashboard', MarketingDashboardController::class)->name('marketing.dashboard');
        Route::get('landing-approvals', [LandingApprovalController::class, 'index'])->name('landing-approvals.index');
        Route::post('landing-approvals/{campaign}/approve', [LandingApprovalController::class, 'approve'])->name('landing-approvals.approve');
        Route::get('marketing/revenue', MarketingRevenueReportController::class)->name('marketing.revenue');
        Route::get('sales/revenue', SaleRevenueReportController::class)->name('sales.revenue');
        Route::get('accounting', AccountingOperationsController::class)->name('accounting');
        Route::get('warehouse/operations', WarehouseOperationsController::class)->name('warehouse.operations');
        Route::get('warehouse/inventory', InventoryController::class)->name('warehouse.inventory');
        Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
        Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
        Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
        Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
        Route::get('warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
        Route::delete('warehouse-inventories/{inventory}', [WarehouseInventoryController::class, 'destroy'])->name('warehouse-inventories.destroy');
        Route::resource('users', UserController::class)->except(['show']);
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
        Route::post('shipping-partners/{provider}/test/{action}', ShippingPartnerTestController::class)->name('shipping-partners.test');
    });

    Route::middleware('role:'.User::ROLE_SALES)->prefix('sales')->name('sales.')->group(function () {
        Route::get('dashboard', SalesDashboardController::class)->name('dashboard');
        Route::get('rankings', SalesRankingController::class)->name('rankings');
        Route::get('workspace', OperationController::class)->name('workspace');
        Route::post('orders/{order}/close', [OrderClosingController::class, 'store'])->name('orders.close');
        Route::get('customers', CustomerProfileController::class)->name('customers');
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
    });

    Route::middleware('role:'.User::ROLE_WAREHOUSE)->prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('dashboard', WarehouseDashboardController::class)->name('dashboard');
        Route::get('workspace', WarehouseOperationsController::class)->name('workspace');
        Route::get('inventory', InventoryController::class)->name('inventory');
        Route::get('shipping/orders', [App\Http\Controllers\Warehouse\ShippingOrderController::class, 'index'])->name('shipping.orders');
        Route::get('shipping/orders/{order}/detail', [ShippingOrderController::class, 'detail'])->name('shipping.orders.detail');
        Route::post('shipping/orders/{order}/create-shipment', [ShippingOrderController::class, 'createShipment'])->name('shipping.orders.create-shipment');
        Route::post('shipping/orders/{order}/sync-status', [ShippingOrderController::class, 'syncStatus'])->name('shipping.orders.sync-status');
        Route::post('shipping/orders/{order}/calculate-fee', [ShippingOrderController::class, 'calculateFee'])->name('shipping.orders.calculate-fee');
        Route::post('shipping/orders/{order}/cancel-shipment', [ShippingOrderController::class, 'cancelShipment'])->name('shipping.orders.cancel-shipment');
        Route::get('shipping/orders/{order}/label', [ShippingOrderController::class, 'printLabel'])->name('shipping.orders.label');
    });

    Route::middleware('role:'.User::ROLE_ACCOUNTING)->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('dashboard', AccountingDashboardController::class)->name('dashboard');
        Route::get('workspace', AccountingOperationsController::class)->name('workspace');
    });

    Route::middleware('role:'.User::ROLE_ALLOCATOR)->prefix('allocator')->name('allocator.')->group(function () {
        Route::get('dashboard', AllocatorDashboardController::class)->name('dashboard');
        Route::get('workspace', LeadsLogController::class)->name('workspace');
    });
});
