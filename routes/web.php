<?php

use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;
use App\Http\Controllers\Admin\IntegrationsController;
use App\Http\Controllers\Admin\LeadsLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BusinessOverviewController;
use App\Http\Controllers\Admin\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Admin\Marketing\RevenueReportController as MarketingRevenueReportController;
use App\Http\Controllers\Admin\Orders\FailedOrdersController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\Reports\CeoReportController;
use App\Http\Controllers\Admin\Sales\RevenueReportController as SaleRevenueReportController;
use App\Http\Controllers\Admin\ShippingPartnersController;
use App\Http\Controllers\Admin\ShippingReconciliationController;
use App\Http\Controllers\Admin\Warehouse\InventoryController;
use App\Http\Controllers\Admin\Warehouse\OperationsController as WarehouseOperationsController;
use App\Http\Controllers\Admin\Warehouse\WarehouseController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\Sales\OperationController;
use App\Http\Controllers\SettingsController;
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

    Route::middleware('role:'.User::ROLE_ADMIN)->prefix('admin')->name('admin.')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('reports/business', BusinessOverviewController::class)->name('reports.business');
        Route::get('reports/ceo', CeoReportController::class)->name('reports.ceo');
        Route::get('marketing/dashboard', MarketingDashboardController::class)->name('marketing.dashboard');
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
        Route::get('orders/failed', FailedOrdersController::class)->name('orders.failed');
        Route::get('rankings', RankingController::class)->name('rankings');
        Route::get('integrations', [IntegrationsController::class, 'index'])->name('integrations.index');
        Route::put('integrations/{platform}', [IntegrationsController::class, 'update'])->name('integrations.update');
        Route::post('integrations/{platform}/test', [IntegrationsController::class, 'testWebhook'])->name('integrations.test');
        Route::get('leads', LeadsLogController::class)->name('leads.index');
        Route::get('shipping-partners', [ShippingPartnersController::class, 'index'])->name('shipping-partners.index');
        Route::put('shipping-partners/{provider}', [ShippingPartnersController::class, 'update'])->name('shipping-partners.update');
        Route::get('shipping/reconciliation', ShippingReconciliationController::class)->name('shipping.reconciliation');
    });

    Route::middleware('role:'.User::ROLE_SALES)->prefix('sales')->name('sales.')->group(function () {
        Route::get('workspace', OperationController::class)->name('workspace');
        Route::get('customers', CustomerProfileController::class)->name('customers');
    });

    Route::middleware('role:'.User::ROLE_MARKETING)->prefix('marketing')->name('marketing.')->group(function () {
        Route::get('workspace', MarketingDashboardController::class)->name('workspace');
        Route::get('revenue', MarketingRevenueReportController::class)->name('revenue');
    });

    Route::middleware('role:'.User::ROLE_WAREHOUSE)->prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('workspace', WarehouseOperationsController::class)->name('workspace');
        Route::get('inventory', InventoryController::class)->name('inventory');
    });

    Route::middleware('role:'.User::ROLE_ACCOUNTING)->prefix('accounting')->name('accounting.')->group(function () {
        Route::get('workspace', AccountingOperationsController::class)->name('workspace');
    });

    Route::middleware('role:'.User::ROLE_ALLOCATOR)->prefix('allocator')->name('allocator.')->group(function () {
        Route::get('workspace', LeadsLogController::class)->name('workspace');
    });
});
