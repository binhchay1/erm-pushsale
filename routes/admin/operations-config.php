<?php

/**
 * Menu 1.6 Cấu hình chức năng · 1.8 Cấu hình tác nghiệp · 1.9 Chiết khấu COD
 * + cấu hình hệ thống.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\OperationsConfig\DiscountCodRuleController;
use App\Http\Controllers\Admin\OperationsConfig\OperationCategoryController;
use App\Http\Controllers\Admin\OperationsConfig\OperationWorkflowController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\SettingsController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 1.6 Cấu hình chức năng — cùng trang với /settings, đây là URL theo menu.
Route::get('settings/features', [SettingsController::class, 'index'])->name('settings.features');

// 1.8.1 Quản lý danh mục tác nghiệp
Route::get('sales/operation-categories', [OperationCategoryController::class, 'index'])->name('sales.operation-categories');
Route::post('sales/operation-categories/records', [OperationCategoryController::class, 'store'])->name('sales.operation-categories.store');
Route::match(['put', 'patch'], 'sales/operation-categories/records/{record}', [OperationCategoryController::class, 'update'])->whereNumber('record')->name('sales.operation-categories.update');
Route::delete('sales/operation-categories/records/{record}', [OperationCategoryController::class, 'destroy'])->whereNumber('record')->name('sales.operation-categories.destroy');
Route::patch('sales/operation-categories/results/{value}', [OperationCategoryController::class, 'updateResult'])->where('value', '[A-Za-z0-9_\-]+')->name('sales.operation-categories.results.update');

// 1.8.2 Thiết lập tác nghiệp
Route::get('sales/operation-workflows', [OperationWorkflowController::class, 'index'])->name('sales.operation-workflows');
Route::post('sales/operation-workflows/records', [OperationWorkflowController::class, 'store'])->name('sales.operation-workflows.store');
Route::match(['put', 'patch'], 'sales/operation-workflows/records/{record}', [OperationWorkflowController::class, 'update'])->whereNumber('record')->name('sales.operation-workflows.update');
Route::delete('sales/operation-workflows/records/{record}', [OperationWorkflowController::class, 'destroy'])->whereNumber('record')->name('sales.operation-workflows.destroy');

// 1.9 Thiết lập chiết khấu / COD
Route::get('sales/discount-cod-rules', [DiscountCodRuleController::class, 'index'])->name('sales.discount-cod-rules');
Route::post('sales/discount-cod-rules/records', [DiscountCodRuleController::class, 'store'])->name('sales.discount-cod-rules.store');
Route::match(['put', 'patch'], 'sales/discount-cod-rules/records/{record}', [DiscountCodRuleController::class, 'update'])->whereNumber('record')->name('sales.discount-cod-rules.update');
Route::delete('sales/discount-cod-rules/records/{record}', [DiscountCodRuleController::class, 'destroy'])->whereNumber('record')->name('sales.discount-cod-rules.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    Route::get('system/settings', [SystemSettingsController::class, 'index'])->name('system.settings');
    Route::put('system/settings', [SystemSettingsController::class, 'update'])->name('system.settings.update');
});
