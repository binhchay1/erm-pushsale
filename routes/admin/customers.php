<?php

/**
 * Menu 3.x Quản lý khách hàng — hồ sơ 360, chiến dịch chăm sóc, báo cáo KH
 * + hành động hàng loạt dùng chung cho mọi trang hồ sơ khách hàng.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\Customers\CareCampaignController;
use App\Http\Controllers\Admin\Customers\MultidimensionalReportController;
use App\Http\Controllers\Admin\Customers\SpendingReportController;
use App\Http\Controllers\CustomerInteractions\Customer360ManagementController;
use App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 3.1 Quản lý khách hàng (360)
Route::get('customer-management', [Customer360ManagementController::class, 'index'])->name('customers.page');
Route::get('customer-management/export', [Customer360ManagementController::class, 'export'])->name('customer-management.export');
Route::post('customer-management/campaigns', [Customer360ManagementController::class, 'createCampaign'])->name('customer-management.campaigns.store');
Route::post('customer-management/campaigns/attach', [Customer360ManagementController::class, 'attachCampaign'])->name('customer-management.campaigns.attach');
Route::put('customer-management/segments', [Customer360ManagementController::class, 'saveSegments'])->name('customer-management.segments.update');

// 3.2 Quản lý chiến dịch chăm sóc
Route::get('customers/care-campaigns', [CareCampaignController::class, 'index'])->name('customers.care-campaigns');
Route::post('customers/care-campaigns/records', [CareCampaignController::class, 'store'])->name('customers.care-campaigns.store');
Route::match(['put', 'patch'], 'customers/care-campaigns/records/{record}', [CareCampaignController::class, 'update'])->whereNumber('record')->name('customers.care-campaigns.update');
Route::delete('customers/care-campaigns/records/{record}', [CareCampaignController::class, 'destroy'])->whereNumber('record')->name('customers.care-campaigns.destroy');

// 3.3 Báo cáo khách hàng
Route::get('customers/reports/multidimensional', [MultidimensionalReportController::class, 'index'])->name('customers.reports.multidimensional');
Route::get('customers/reports/spending', [SpendingReportController::class, 'index'])->name('customers.reports.spending');

/*
|--------------------------------------------------------------------------
| Hành động hàng loạt trên trang hồ sơ khách hàng
|--------------------------------------------------------------------------
| URL hành động bám theo URL trang đang mở, nên /admin/marketing/customers
| không phải gọi ngầm sang /admin/customers.
*/
foreach ([
    'customers' => 'customers',
    'marketing/customers' => 'marketing.customers',
    'sales/customers' => 'sales.customers',
] as $customerProfilePath => $customerProfileName) {
    Route::get($customerProfilePath.'/export', [CustomerProfileBulkActionController::class, 'export'])->name($customerProfileName.'.export');
    Route::post($customerProfilePath.'/bulk/reallocate-now', [CustomerProfileBulkActionController::class, 'reallocateNow'])->name($customerProfileName.'.bulk.reallocate-now');
    Route::post($customerProfilePath.'/bulk/queue-reallocation', [CustomerProfileBulkActionController::class, 'queueReallocation'])->name($customerProfileName.'.bulk.queue-reallocation');
    Route::post($customerProfilePath.'/bulk/recall', [CustomerProfileBulkActionController::class, 'recall'])->name($customerProfileName.'.bulk.recall');
    Route::delete($customerProfilePath.'/bulk/operation-history', [CustomerProfileBulkActionController::class, 'deleteOperationHistory'])->name($customerProfileName.'.bulk.operation-history.destroy');
}

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    Route::get('customers', CustomerProfileController::class)->name('customers.index');
});
