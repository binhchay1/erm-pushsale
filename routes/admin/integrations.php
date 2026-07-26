<?php

/**
 * Menu 1.10 Import contact · 1.11 Facebook đơn vị · 1.4 Kết nối giao hàng
 * + sàn TMĐT, webhook nền tảng, giám sát hệ thống.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\CarrierSettlementController;
use App\Http\Controllers\Admin\Ecommerce\EcommerceController;
use App\Http\Controllers\Admin\Integrations\LeadImportPageController;
use App\Http\Controllers\Admin\Integrations\UnitFacebookPageController;
use App\Http\Controllers\Admin\IntegrationsController;
use App\Http\Controllers\Admin\ShippingOrderController;
use App\Http\Controllers\Admin\ShippingPartnersController;
use App\Http\Controllers\Admin\ShippingPartnerTestController;
use App\Http\Controllers\Admin\ShippingReconciliationController;
use App\Http\Controllers\Admin\SystemMonitorController;
use App\Http\Controllers\Warehouse\OrderReturnController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 1.10 Import contact
Route::get('leads/import', [LeadImportPageController::class, 'index'])->name('leads.import-page');

// 1.11 Cấu hình Facebook của đơn vị
Route::get('integrations/facebook-pages', [UnitFacebookPageController::class, 'index'])->name('integrations.facebook-pages');
Route::post('integrations/facebook-pages/records', [UnitFacebookPageController::class, 'store'])->name('integrations.facebook-pages.store');
Route::match(['put', 'patch'], 'integrations/facebook-pages/records/{record}', [UnitFacebookPageController::class, 'update'])->whereNumber('record')->name('integrations.facebook-pages.update');
Route::delete('integrations/facebook-pages/records/{record}', [UnitFacebookPageController::class, 'destroy'])->whereNumber('record')->name('integrations.facebook-pages.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    // 1.4 Kết nối giao hàng
    Route::get('shipping-partners', [ShippingPartnersController::class, 'index'])->name('shipping-partners.index');
    Route::put('shipping-partners/{provider}', [ShippingPartnersController::class, 'update'])->name('shipping-partners.update');
    Route::put('shipping-default', [ShippingPartnersController::class, 'updateDefault'])->name('shipping-partners.default');
    Route::post('shipping-partners/{provider}/test/{action}', ShippingPartnerTestController::class)->name('shipping-partners.test');

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

    // Sàn thương mại điện tử
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

    // Webhook nền tảng + giám sát sự kiện vào
    Route::get('integrations', [IntegrationsController::class, 'index'])->name('integrations.index');
    Route::put('integrations/{platform}', [IntegrationsController::class, 'update'])->name('integrations.update');
    Route::post('integrations/{platform}/test', [IntegrationsController::class, 'testWebhook'])->name('integrations.test');
    Route::get('system-monitor', [SystemMonitorController::class, 'index'])->name('system-monitor.index');
    Route::get('system-monitor/events/{inboundEvent}', [SystemMonitorController::class, 'show'])->name('system-monitor.show');
});
