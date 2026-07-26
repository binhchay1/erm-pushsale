<?php

/**
 * Không gian làm việc của vai trò Sale (/sales/...).
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->group(...)
 */

use App\Http\Controllers\Admin\ManualLeadController;
use App\Http\Controllers\Reports\ExtraReportController;
use App\Http\Controllers\Reports\HourlyStatsController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\Sales\DashboardController as SalesDashboardController;
use App\Http\Controllers\Sales\DesiredDeliveryDateController;
use App\Http\Controllers\Sales\OperationController;
use App\Http\Controllers\Sales\OrderClosingController;
use App\Http\Controllers\Sales\PerformanceReportController as SalesPerformanceReportController;
use App\Http\Controllers\Sales\RankingController as SalesRankingController;
use App\Http\Controllers\Sales\SaleBulkCloseController;
use App\Http\Controllers\Sales\SaleOperationCallController;
use App\Http\Controllers\Sales\SaleOperationNoteController;
use App\Http\Controllers\Sales\SaleOperationOrderController;
use App\Http\Controllers\Sales\SaleOperationStatusController;
use App\Http\Controllers\Sales\SaleOrderDeletionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.User::ROLE_SALES)->prefix('sales')->name('sales.')->group(function (): void {
    Route::get('dashboard', SalesDashboardController::class)->name('dashboard');
    Route::get('customers', CustomerProfileController::class)->name('customers');
    Route::get('rankings', SalesRankingController::class)->name('rankings');
    Route::get('performance', SalesPerformanceReportController::class)->name('performance');
    Route::get('workspace', OperationController::class)->name('workspace');

    Route::post('leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');
    Route::post('orders/bulk-close', [SaleBulkCloseController::class, 'store'])->name('orders.bulk-close');
    Route::post('orders/{order}/call', [SaleOperationCallController::class, 'store'])->name('orders.call');
    Route::post('orders/{order}/operation-status', [SaleOperationStatusController::class, 'update'])->name('orders.operation-status');
    Route::patch('orders/{order}/operation-note', [SaleOperationNoteController::class, 'update'])->name('orders.operation-note');
    Route::patch('orders/{order}/desired-delivery-date', [DesiredDeliveryDateController::class, 'update'])->name('orders.desired-delivery-date');
    Route::post('orders/{order}/details', [SaleOperationOrderController::class, 'update'])->name('orders.details');
    Route::delete('orders/{order}', [SaleOrderDeletionController::class, 'destroy'])->name('orders.destroy');
    Route::post('orders/{order}/close', [OrderClosingController::class, 'store'])->name('orders.close');

    Route::get('reports/hourly', HourlyStatsController::class)->name('reports.hourly');
    Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');
});
