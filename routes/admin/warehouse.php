<?php

/**
 * Menu 5.x Kho — phiếu nhập xuất, thẻ kho, biên bản, báo cáo kho, care đơn
 * + tác nghiệp vận đơn và tồn kho.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\Pushsale\Warehouse\WarehouseVoucherEntryController;
use App\Http\Controllers\Admin\Pushsale\Warehouse\WarehouseVoucherListController;
use App\Http\Controllers\Admin\Warehouse\CareDistributionController;
use App\Http\Controllers\Admin\Warehouse\CareOperationReportController;
use App\Http\Controllers\Admin\Warehouse\DailyStockReportController;
use App\Http\Controllers\Admin\Warehouse\DeliveryStatusReportController;
use App\Http\Controllers\Admin\Warehouse\InventoryController;
use App\Http\Controllers\Admin\Warehouse\InventoryMovementController;
use App\Http\Controllers\Admin\Warehouse\MovementHistoryController;
use App\Http\Controllers\Admin\Warehouse\MovementSummaryReportController;
use App\Http\Controllers\Admin\Warehouse\BulkUpdateByCodeController;
use App\Http\Controllers\Admin\Warehouse\OperationsController as WarehouseOperationsController;
use App\Http\Controllers\Admin\Warehouse\ShippingLabelPrintController;
use App\Http\Controllers\Admin\Warehouse\PendingExportReportController;
use App\Http\Controllers\Admin\Warehouse\PhoneCorrectionReportController;
use App\Http\Controllers\Admin\Warehouse\StockCardHistoryController;
use App\Http\Controllers\Admin\Warehouse\WarehouseCareOrderReportController;
use App\Http\Controllers\Admin\Warehouse\WarehouseController;
use App\Http\Controllers\Admin\Warehouse\WarehouseIncidentController;
use App\Http\Controllers\Admin\WarehouseInventoryController;
use App\Http\Controllers\Warehouse\DeliveryStatusBulkController;
use App\Http\Controllers\Warehouse\WarehouseOrderActionController;
use App\Http\Controllers\Operations\OrderInteractionLockController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 5.3.1 Phiếu nhập / xuất kho
Route::get('warehouse/vouchers/entry', [WarehouseVoucherEntryController::class, 'index'])->name('warehouse.vouchers.entry');
Route::post('warehouse/vouchers/entry/records', [WarehouseVoucherEntryController::class, 'store'])->name('warehouse.vouchers.entry.store');
Route::match(['put', 'patch'], 'warehouse/vouchers/entry/records/{record}', [WarehouseVoucherEntryController::class, 'update'])->whereNumber('record')->name('warehouse.vouchers.entry.update');
Route::delete('warehouse/vouchers/entry/records/{record}', [WarehouseVoucherEntryController::class, 'destroy'])->whereNumber('record')->name('warehouse.vouchers.entry.destroy');

// 5.3.2 Danh sách phiếu xuất / nhập kho
Route::get('warehouse/vouchers', [WarehouseVoucherListController::class, 'index'])->name('warehouse.vouchers.index-page');

// 5.3.3 Lịch sử nhập xuất kho (thẻ kho)
Route::get('warehouse/movement-history', [StockCardHistoryController::class, 'index'])->name('warehouse.movement-history');

// 5.4 Danh sách biên bản
Route::get('warehouse/incidents', [WarehouseIncidentController::class, 'index'])->name('warehouse.incidents');
Route::post('warehouse/incidents/records', [WarehouseIncidentController::class, 'store'])->name('warehouse.incidents.store');
Route::match(['put', 'patch'], 'warehouse/incidents/records/{record}', [WarehouseIncidentController::class, 'update'])->whereNumber('record')->name('warehouse.incidents.update');
Route::delete('warehouse/incidents/records/{record}', [WarehouseIncidentController::class, 'destroy'])->whereNumber('record')->name('warehouse.incidents.destroy');

// 5.5 Báo cáo kho
Route::get('warehouse/reports/daily-stock', [DailyStockReportController::class, 'index'])->name('warehouse.reports.daily-stock');
Route::get('warehouse/reports/pending-export', [PendingExportReportController::class, 'index'])->name('warehouse.reports.pending-export');
Route::get('warehouse/reports/movement-summary', [MovementSummaryReportController::class, 'index'])->name('warehouse.reports.movement-summary');
Route::get('warehouse/reports/care-orders', [WarehouseCareOrderReportController::class, 'index'])->name('warehouse.reports.care-orders');
Route::get('warehouse/reports/phone-corrections', [PhoneCorrectionReportController::class, 'index'])->name('warehouse.reports.phone-corrections');
Route::get('warehouse/reports/phone_corrections', [PhoneCorrectionReportController::class, 'index'])->name('warehouse.reports.phone-corrections.alias');
Route::get('warehouse/reports/delivery-status', [DeliveryStatusReportController::class, 'index'])->name('warehouse.reports.delivery-status');
Route::get('warehouse/reports/care-operations', [CareOperationReportController::class, 'index'])->name('warehouse.reports.care-operations');

// 5.8.2 Phân bổ data care đơn
Route::get('warehouse/care-distribution', [CareDistributionController::class, 'index'])->name('warehouse.care-distribution');
Route::post('warehouse/care-distribution/records', [CareDistributionController::class, 'store'])->name('warehouse.care-distribution.store');
Route::match(['put', 'patch'], 'warehouse/care-distribution/records/{record}', [CareDistributionController::class, 'update'])->whereNumber('record')->name('warehouse.care-distribution.update');
Route::delete('warehouse/care-distribution/records/{record}', [CareDistributionController::class, 'destroy'])->whereNumber('record')->name('warehouse.care-distribution.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    // 5.1 Tác nghiệp vận đơn
    Route::get('warehouse/operations', WarehouseOperationsController::class)->name('warehouse.operations');
    Route::post('warehouse/orders/interaction-locks', [OrderInteractionLockController::class, 'index'])->name('warehouse.orders.interaction-locks.index');
    Route::get('warehouse/orders/{order}/interaction-lock', [OrderInteractionLockController::class, 'show'])->name('warehouse.orders.interaction-lock.show');
    Route::post('warehouse/orders/{order}/interaction-lock', [OrderInteractionLockController::class, 'store'])->name('warehouse.orders.interaction-lock.store');
    Route::post('warehouse/orders/{order}/interaction-lock/heartbeat', [OrderInteractionLockController::class, 'heartbeat'])->name('warehouse.orders.interaction-lock.heartbeat');
    Route::delete('warehouse/orders/{order}/interaction-lock', [OrderInteractionLockController::class, 'destroy'])->name('warehouse.orders.interaction-lock.destroy');
    Route::delete('warehouse/orders/{order}', [WarehouseOrderActionController::class, 'destroy'])->name('warehouse.orders.destroy');
    Route::post('warehouse/orders/bulk/export', [WarehouseOrderActionController::class, 'bulkExport'])->name('warehouse.orders.bulk.export');
    Route::post('warehouse/orders/bulk/invoices', [WarehouseOrderActionController::class, 'bulkInvoices'])->name('warehouse.orders.bulk.invoices');
    Route::post('warehouse/orders/bulk/update-by-code', [WarehouseOrderActionController::class, 'bulkUpdateByCode'])->name('warehouse.orders.bulk.update-by-code');
    Route::get('warehouse/orders/update-by-code', [BulkUpdateByCodeController::class, 'index'])->name('warehouse.orders.update-by-code');
    Route::post('warehouse/orders/update-by-code', [BulkUpdateByCodeController::class, 'execute'])->name('warehouse.orders.update-by-code.execute');
    Route::get('warehouse/orders/delivery-status-bulk/meta', [DeliveryStatusBulkController::class, 'meta'])->name('warehouse.orders.delivery-status-bulk.meta');
    Route::post('warehouse/orders/delivery-status-bulk/inspect', [DeliveryStatusBulkController::class, 'inspect'])->name('warehouse.orders.delivery-status-bulk.inspect');
    Route::post('warehouse/orders/delivery-status-bulk/update', [DeliveryStatusBulkController::class, 'updateByCodes'])->name('warehouse.orders.delivery-status-bulk.update');
    Route::get('warehouse/orders/delivery-status-bulk/template', [DeliveryStatusBulkController::class, 'template'])->name('warehouse.orders.delivery-status-bulk.template');
    Route::post('warehouse/orders/delivery-status-bulk/upload', [DeliveryStatusBulkController::class, 'upload'])->name('warehouse.orders.delivery-status-bulk.upload');
    Route::get('warehouse/orders/delivery-status-bulk/history', [DeliveryStatusBulkController::class, 'history'])->name('warehouse.orders.delivery-status-bulk.history');
    Route::post('warehouse/orders/delivery-status-bulk/batches/{batch}/apply', [DeliveryStatusBulkController::class, 'apply'])->whereNumber('batch')->name('warehouse.orders.delivery-status-bulk.apply');
    Route::post('warehouse/orders/delivery-status-bulk/batches/{batch}/clear', [DeliveryStatusBulkController::class, 'clear'])->whereNumber('batch')->name('warehouse.orders.delivery-status-bulk.clear');
    Route::get('warehouse/orders/print/profiles', [ShippingLabelPrintController::class, 'profiles'])->name('warehouse.orders.print.profiles');
    Route::post('warehouse/orders/print/mark-printed', [ShippingLabelPrintController::class, 'markPrinted'])->name('warehouse.orders.print.mark-printed');
    Route::get('warehouse/orders/print/{profile}', [ShippingLabelPrintController::class, 'show'])->where('profile', 'internal|shopee|tiktok|ghtk|jnt|spx')->name('warehouse.orders.print');
    Route::patch('warehouse/orders/{order}/desired-delivery', [WarehouseOrderActionController::class, 'desiredDelivery'])->name('warehouse.orders.desired-delivery');
    Route::patch('warehouse/orders/{order}/order-code', [WarehouseOrderActionController::class, 'changeOrderCode'])->name('warehouse.orders.order-code');
    Route::post('warehouse/orders/{order}/blacklist', [WarehouseOrderActionController::class, 'blacklist'])->name('warehouse.orders.blacklist');
    Route::patch('warehouse/orders/{order}/care', [WarehouseOrderActionController::class, 'care'])->name('warehouse.orders.care');
    Route::post('warehouse/orders/{order}/internal-message', [WarehouseOrderActionController::class, 'internalMessage'])->name('warehouse.orders.internal-message');
    Route::patch('warehouse/orders/{order}/delivery-status', [WarehouseOrderActionController::class, 'deliveryStatus'])->name('warehouse.orders.delivery-status');
    Route::put('warehouse/orders/{order}', [WarehouseOrderActionController::class, 'updateOrder'])->name('warehouse.orders.update');
    Route::post('warehouse/orders/{order}/split', [WarehouseOrderActionController::class, 'split'])->name('warehouse.orders.split');
    Route::post('warehouse/orders/{order}/printed', [WarehouseOrderActionController::class, 'printed'])->name('warehouse.orders.printed');
    Route::post('warehouse/orders/{order}/return-receipt', [WarehouseOrderActionController::class, 'receiveReturn'])->name('warehouse.orders.return-receipt');

    // 5.2 Kho và tồn kho
    Route::get('warehouse/inventory', InventoryController::class)->name('warehouse.inventory');
    Route::post('warehouse/inventory/intake', [InventoryMovementController::class, 'intake'])->name('warehouse.inventory.intake');
    Route::post('warehouse/inventory/export', [InventoryMovementController::class, 'export'])->name('warehouse.inventory.export');
    Route::get('warehouse/movements', MovementHistoryController::class)->name('warehouse.movements');
    Route::get('warehouses', [WarehouseController::class, 'index'])->name('warehouses.index');
    Route::get('warehouses/locations', [WarehouseController::class, 'locationBook'])->name('warehouses.locations');
    Route::get('warehouses/create', [WarehouseController::class, 'create'])->name('warehouses.create');
    Route::post('warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->name('warehouses.show');
    Route::get('warehouses/{warehouse}/edit', [WarehouseController::class, 'edit'])->name('warehouses.edit');
    Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->name('warehouses.update');
    Route::put('warehouses/{warehouse}/shipping-account', [WarehouseController::class, 'updateShippingAccount'])->name('warehouses.shipping-account.update');
    Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->name('warehouses.destroy');
    Route::delete('warehouse-inventories/{inventory}', [WarehouseInventoryController::class, 'destroy'])->name('warehouse-inventories.destroy');
});
