<?php

/**
 * Không gian làm việc của vai trò Kế toán (/accounting/...).
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->group(...)
 */

use App\Http\Controllers\Accounting\DashboardController as AccountingDashboardController;
use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;
use App\Http\Controllers\Reports\ExtraReportController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.User::ROLE_ACCOUNTING)->prefix('accounting')->name('accounting.')->group(function (): void {
    Route::get('dashboard', AccountingDashboardController::class)->name('dashboard');
    Route::get('workspace', AccountingOperationsController::class)->name('workspace');
    Route::get('customers', CustomerProfileController::class)->name('customers.index');
    Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
});
