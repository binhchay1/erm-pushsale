<?php

/**
 * Không gian làm việc của vai trò Kế toán (/accounting/...).
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->group(...)
 */

use App\Http\Controllers\Accounting\DashboardController as AccountingDashboardController;
use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;
use App\Http\Controllers\Admin\Warehouse\BulkUpdateByCodeController;
use App\Http\Controllers\Admin\Warehouse\ShippingLabelPrintController;
use App\Http\Controllers\Admin\ShippingOrderController;
use App\Http\Controllers\Reports\ExtraReportController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\Warehouse\DeliveryStatusBulkController;
use App\Http\Controllers\Warehouse\OrderReturnController;
use App\Http\Controllers\Warehouse\WarehouseOrderActionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.User::ROLE_ACCOUNTING)->prefix('accounting')->name('accounting.')->group(function (): void {
    Route::get('dashboard', AccountingDashboardController::class)->name('dashboard');
    Route::get('workspace', AccountingOperationsController::class)->name('workspace');
    Route::get('customers', CustomerProfileController::class)->name('customers.index');

    Route::post('orders/bulk/export', [WarehouseOrderActionController::class, 'bulkExport'])->name('orders.bulk.export');
    Route::post('orders/bulk/invoices', [WarehouseOrderActionController::class, 'bulkInvoices'])->name('orders.bulk.invoices');
    Route::post('orders/bulk/update-by-code', [WarehouseOrderActionController::class, 'bulkUpdateByCode'])->name('orders.bulk.update-by-code');
    Route::get('orders/update-by-code', [BulkUpdateByCodeController::class, 'index'])->name('orders.update-by-code');
    Route::post('orders/update-by-code', [BulkUpdateByCodeController::class, 'execute'])->name('orders.update-by-code.execute');
    Route::get('orders/delivery-status-bulk/meta', [DeliveryStatusBulkController::class, 'meta'])->name('orders.delivery-status-bulk.meta');
    Route::post('orders/delivery-status-bulk/inspect', [DeliveryStatusBulkController::class, 'inspect'])->name('orders.delivery-status-bulk.inspect');
    Route::post('orders/delivery-status-bulk/update', [DeliveryStatusBulkController::class, 'updateByCodes'])->name('orders.delivery-status-bulk.update');
    Route::get('orders/delivery-status-bulk/template', [DeliveryStatusBulkController::class, 'template'])->name('orders.delivery-status-bulk.template');
    Route::post('orders/delivery-status-bulk/upload', [DeliveryStatusBulkController::class, 'upload'])->name('orders.delivery-status-bulk.upload');
    Route::get('orders/delivery-status-bulk/history', [DeliveryStatusBulkController::class, 'history'])->name('orders.delivery-status-bulk.history');
    Route::post('orders/delivery-status-bulk/batches/{batch}/apply', [DeliveryStatusBulkController::class, 'apply'])->whereNumber('batch')->name('orders.delivery-status-bulk.apply');
    Route::post('orders/delivery-status-bulk/batches/{batch}/clear', [DeliveryStatusBulkController::class, 'clear'])->whereNumber('batch')->name('orders.delivery-status-bulk.clear');
    Route::get('orders/print/profiles', [ShippingLabelPrintController::class, 'profiles'])->name('orders.print.profiles');
    Route::post('orders/print/mark-printed', [ShippingLabelPrintController::class, 'markPrinted'])->name('orders.print.mark-printed');
    Route::get('orders/print/{profile}', [ShippingLabelPrintController::class, 'show'])->where('profile', 'internal|shopee|tiktok|ghtk|jnt|spx')->name('orders.print');
    Route::delete('orders/{order}', [WarehouseOrderActionController::class, 'destroy'])->name('orders.destroy');
    Route::patch('orders/{order}/desired-delivery', [WarehouseOrderActionController::class, 'desiredDelivery'])->name('orders.desired-delivery');
    Route::patch('orders/{order}/order-code', [WarehouseOrderActionController::class, 'changeOrderCode'])->name('orders.order-code');
    Route::post('orders/{order}/blacklist', [WarehouseOrderActionController::class, 'blacklist'])->name('orders.blacklist');
    Route::patch('orders/{order}/care', [WarehouseOrderActionController::class, 'care'])->name('orders.care');
    Route::post('orders/{order}/internal-message', [WarehouseOrderActionController::class, 'internalMessage'])->name('orders.internal-message');
    Route::patch('orders/{order}/delivery-status', [WarehouseOrderActionController::class, 'deliveryStatus'])->name('orders.delivery-status');
    Route::put('orders/{order}', [WarehouseOrderActionController::class, 'updateOrder'])->name('orders.update');
    Route::post('orders/{order}/split', [WarehouseOrderActionController::class, 'split'])->name('orders.split');
    Route::post('orders/{order}/printed', [WarehouseOrderActionController::class, 'printed'])->name('orders.printed');
    Route::post('orders/{order}/return-receipt', [WarehouseOrderActionController::class, 'receiveReturn'])->name('orders.return-receipt');

    Route::get('shipping/orders/{order}/detail', [ShippingOrderController::class, 'detail'])->name('shipping.orders.detail');
    Route::post('shipping/orders/{order}/create-shipment', [ShippingOrderController::class, 'createShipment'])->name('shipping.orders.create-shipment');
    Route::post('shipping/orders/{order}/sync-status', [ShippingOrderController::class, 'syncStatus'])->name('shipping.orders.sync-status');
    Route::post('shipping/orders/{order}/calculate-fee', [ShippingOrderController::class, 'calculateFee'])->name('shipping.orders.calculate-fee');
    Route::post('shipping/orders/{order}/cancel-shipment', [ShippingOrderController::class, 'cancelShipment'])->name('shipping.orders.cancel-shipment');
    Route::get('shipping/orders/{order}/label', [ShippingOrderController::class, 'printLabel'])->name('shipping.orders.label');
    Route::post('shipping/orders/{order}/receive-return', [OrderReturnController::class, 'store'])->name('shipping.orders.receive-return');

    Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
});
