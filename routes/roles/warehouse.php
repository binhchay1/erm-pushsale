<?php

/**
 * Không gian làm việc của vai trò Kho (/warehouse/...).
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->group(...)
 */

use App\Http\Controllers\Admin\ShippingOrderController;
use App\Http\Controllers\Admin\Warehouse\InventoryController;
use App\Http\Controllers\Admin\Warehouse\InventoryMovementController;
use App\Http\Controllers\Admin\Warehouse\OperationsController as WarehouseOperationsController;
use App\Http\Controllers\Reports\ExtraReportController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\Warehouse\DashboardController as WarehouseDashboardController;
use App\Http\Controllers\Warehouse\OrderReturnController;
use App\Http\Controllers\Warehouse\ShippingOrderController as WarehouseShippingOrderController;
use App\Http\Controllers\Warehouse\WarehouseOrderActionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.User::ROLE_WAREHOUSE)->prefix('warehouse')->name('warehouse.')->group(function (): void {
    Route::get('dashboard', WarehouseDashboardController::class)->name('dashboard');
    Route::get('workspace', WarehouseOperationsController::class)->name('workspace');
    Route::get('customers', CustomerProfileController::class)->name('customers.index');

    Route::post('orders/bulk/export', [WarehouseOrderActionController::class, 'bulkExport'])->name('orders.bulk.export');
    Route::post('orders/bulk/invoices', [WarehouseOrderActionController::class, 'bulkInvoices'])->name('orders.bulk.invoices');
    Route::post('orders/bulk/update-by-code', [WarehouseOrderActionController::class, 'bulkUpdateByCode'])->name('orders.bulk.update-by-code');
    Route::delete('orders/{order}', [WarehouseOrderActionController::class, 'destroy'])->name('orders.destroy');
    Route::patch('orders/{order}/desired-delivery', [WarehouseOrderActionController::class, 'desiredDelivery'])->name('orders.desired-delivery');
    Route::patch('orders/{order}/order-code', [WarehouseOrderActionController::class, 'changeOrderCode'])->name('orders.order-code');
    Route::post('orders/{order}/blacklist', [WarehouseOrderActionController::class, 'blacklist'])->name('orders.blacklist');
    Route::patch('orders/{order}/care', [WarehouseOrderActionController::class, 'care'])->name('orders.care');
    Route::patch('orders/{order}/delivery-status', [WarehouseOrderActionController::class, 'deliveryStatus'])->name('orders.delivery-status');
    Route::put('orders/{order}', [WarehouseOrderActionController::class, 'updateOrder'])->name('orders.update');
    Route::post('orders/{order}/split', [WarehouseOrderActionController::class, 'split'])->name('orders.split');
    Route::post('orders/{order}/printed', [WarehouseOrderActionController::class, 'printed'])->name('orders.printed');
    Route::post('orders/{order}/return-receipt', [WarehouseOrderActionController::class, 'receiveReturn'])->name('orders.return-receipt');

    Route::get('inventory', InventoryController::class)->name('inventory');
    Route::post('inventory/intake', [InventoryMovementController::class, 'intake'])->name('inventory.intake');
    Route::post('inventory/export', [InventoryMovementController::class, 'export'])->name('inventory.export');

    Route::get('shipping/orders', [WarehouseShippingOrderController::class, 'index'])->name('shipping.orders');
    Route::get('shipping/orders/{order}/detail', [ShippingOrderController::class, 'detail'])->name('shipping.orders.detail');
    Route::post('shipping/orders/{order}/create-shipment', [ShippingOrderController::class, 'createShipment'])->name('shipping.orders.create-shipment');
    Route::post('shipping/orders/{order}/sync-status', [ShippingOrderController::class, 'syncStatus'])->name('shipping.orders.sync-status');
    Route::post('shipping/orders/{order}/calculate-fee', [ShippingOrderController::class, 'calculateFee'])->name('shipping.orders.calculate-fee');
    Route::post('shipping/orders/{order}/cancel-shipment', [ShippingOrderController::class, 'cancelShipment'])->name('shipping.orders.cancel-shipment');
    Route::get('shipping/orders/{order}/label', [ShippingOrderController::class, 'printLabel'])->name('shipping.orders.label');
    Route::post('shipping/orders/{order}/receive-return', [OrderReturnController::class, 'store'])->name('shipping.orders.receive-return');

    Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
});
