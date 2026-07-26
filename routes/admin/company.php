<?php

/**
 * Menu 1.1 Thông tin đơn vị · 1.14 Hóa đơn điện tử đơn vị.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\Company\ElectronicInvoiceConfigController;
use App\Http\Controllers\Admin\Company\SubscriptionHistoryController;
use App\Http\Controllers\Admin\CompanyProfileController;
use App\Http\Controllers\Admin\CompanySettingsController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 1.1.1 Thông tin đơn vị
Route::get('company/profile', [CompanyProfileController::class, 'index'])->name('company.profile');
Route::put('company/profile', [CompanyProfileController::class, 'update'])->name('company.profile.update');

// 1.1.2 Lịch sử đăng ký gói dịch vụ
Route::get('company/subscription-history', [SubscriptionHistoryController::class, 'index'])->name('company.subscription-history');
Route::post('company/subscription-history/records', [SubscriptionHistoryController::class, 'store'])->name('company.subscription-history.store');
Route::match(['put', 'patch'], 'company/subscription-history/records/{record}', [SubscriptionHistoryController::class, 'update'])->whereNumber('record')->name('company.subscription-history.update');
Route::delete('company/subscription-history/records/{record}', [SubscriptionHistoryController::class, 'destroy'])->whereNumber('record')->name('company.subscription-history.destroy');

// 1.14.1 Danh sách cấu hình hóa đơn điện tử
Route::get('unit/electronic-invoice-configs', [ElectronicInvoiceConfigController::class, 'index'])->name('unit.electronic-invoice-configs');
Route::post('unit/electronic-invoice-configs/records', [ElectronicInvoiceConfigController::class, 'store'])->name('unit.electronic-invoice-configs.store');
Route::match(['put', 'patch'], 'unit/electronic-invoice-configs/records/{record}', [ElectronicInvoiceConfigController::class, 'update'])->whereNumber('record')->name('unit.electronic-invoice-configs.update');
Route::delete('unit/electronic-invoice-configs/records/{record}', [ElectronicInvoiceConfigController::class, 'destroy'])->whereNumber('record')->name('unit.electronic-invoice-configs.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    // Mẫu file import lead của đơn vị.
    Route::post('company/lead-template', [CompanySettingsController::class, 'updateLeadTemplate'])->name('company.lead-template');
    Route::delete('company/lead-template', [CompanySettingsController::class, 'destroyLeadTemplate'])->name('company.lead-template.destroy');
});
