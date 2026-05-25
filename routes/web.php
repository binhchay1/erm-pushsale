<?php

use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;
use App\Http\Controllers\Admin\IntegrationsController;
use App\Http\Controllers\Admin\LeadsLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Admin\Marketing\RevenueReportController as MarketingRevenueReportController;
use App\Http\Controllers\Admin\Orders\FailedOrdersController;
use App\Http\Controllers\Admin\Reports\CeoReportController;
use App\Http\Controllers\Admin\Sales\RevenueReportController as SaleRevenueReportController;
use App\Http\Controllers\Admin\Warehouse\InventoryController;
use App\Http\Controllers\Admin\Warehouse\OperationsController as WarehouseOperationsController;
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
        Route::get('reports/ceo', CeoReportController::class)->name('reports.ceo');
        Route::get('marketing/dashboard', MarketingDashboardController::class)->name('marketing.dashboard');
        Route::get('marketing/revenue', MarketingRevenueReportController::class)->name('marketing.revenue');
        Route::get('sales/revenue', SaleRevenueReportController::class)->name('sales.revenue');
        Route::get('accounting', AccountingOperationsController::class)->name('accounting');
        Route::get('warehouse/operations', WarehouseOperationsController::class)->name('warehouse.operations');
        Route::get('warehouse/inventory', InventoryController::class)->name('warehouse.inventory');
        Route::get('orders/failed', FailedOrdersController::class)->name('orders.failed');
        Route::get('integrations', [IntegrationsController::class, 'index'])->name('integrations.index');
        Route::put('integrations/{platform}', [IntegrationsController::class, 'update'])->name('integrations.update');
        Route::post('integrations/{platform}/test', [IntegrationsController::class, 'testWebhook'])->name('integrations.test');
        Route::get('leads', LeadsLogController::class)->name('leads.index');
    });

    Route::middleware('role:'.User::ROLE_SALES)->prefix('sales')->name('sales.')->group(function () {
        Route::get('workspace', OperationController::class)->name('workspace');
        Route::get('customers', CustomerProfileController::class)->name('customers');
    });
});
