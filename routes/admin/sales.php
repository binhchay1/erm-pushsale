<?php

/**
 * Menu 4.x Sale — hồ sơ KH, xếp hạng, báo cáo tác nghiệp, workspace bán hàng.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\FailedPartnerOrderController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\Orders\FailedOrdersController;
use App\Http\Controllers\Admin\RankingController;
use App\Http\Controllers\Admin\Sales\OperationConversionReportController;
use App\Http\Controllers\Admin\Sales\PerformanceReportController as AdminSalesPerformanceReportController;
use App\Http\Controllers\Admin\Sales\RevenueReportController as SaleRevenueReportController;
use App\Http\Controllers\Admin\Sales\SalesDataReportController;
use App\Http\Controllers\Admin\Sales\SalesOptimizationReportController;
use App\Http\Controllers\Admin\Sales\SalesRankingPageController;
use App\Http\Controllers\Admin\Sales\SalesTeamReportController;
use App\Http\Controllers\Admin\Sales\SalesWorkReportController;
use App\Http\Controllers\Admin\Sales\WorkspaceController as AdminSalesWorkspaceController;
use App\Http\Controllers\Admin\ManualLeadController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\Sales\DesiredDeliveryDateController;
use App\Http\Controllers\Sales\OrderClosingController;
use App\Http\Controllers\Sales\SaleBulkCloseController;
use App\Http\Controllers\Sales\SaleOperationCallController;
use App\Http\Controllers\Sales\SaleOperationNoteController;
use App\Http\Controllers\Sales\SaleOperationOrderController;
use App\Http\Controllers\Sales\SaleOperationStatusController;
use App\Http\Controllers\Sales\SaleOrderDeletionController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 4.2 Hồ sơ khách hàng (góc nhìn sale)
Route::get('sales/customers', CustomerProfileController::class)->name('sales.customers');

// 4.3 Bảng xếp hạng sale
Route::get('sales/rankings', [SalesRankingPageController::class, 'index'])->name('sales.rankings-page');

// 4.6 Báo cáo tác nghiệp sale
Route::get('sales/reports/operation-conversion', [OperationConversionReportController::class, 'index'])->name('sales.reports.operation-conversion');
Route::get('sales/reports/work', [SalesWorkReportController::class, 'index'])->name('sales.reports.work');
Route::get('sales/reports/teams', [SalesTeamReportController::class, 'index'])->name('sales.reports.teams');
Route::get('sales/reports/data', [SalesDataReportController::class, 'index'])->name('sales.reports.data');
Route::post('sales/reports/data/receive-data', [SalesDataReportController::class, 'updateReceiveData'])->name('sales.reports.data.receive-data');
Route::get('sales/reports/optimization', [SalesOptimizationReportController::class, 'index'])->name('sales.reports.optimization');
Route::post('sales/reports/optimization/alerts', [SalesOptimizationReportController::class, 'saveAlerts'])->name('sales.reports.optimization.alerts');
Route::post('sales/reports/optimization/targets', [SalesOptimizationReportController::class, 'saveTargets'])->name('sales.reports.optimization.targets');
Route::post('sales/reports/optimization/receive-data', [SalesOptimizationReportController::class, 'updateReceiveData'])->name('sales.reports.optimization.receive-data');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    Route::get('sales/revenue', SaleRevenueReportController::class)->name('sales.revenue');
    Route::get('sales/performance', AdminSalesPerformanceReportController::class)->name('sales.performance');
    Route::get('sales/workspace', AdminSalesWorkspaceController::class)->name('sales.workspace');
    Route::get('rankings', RankingController::class)->name('rankings');

    // Tác nghiệp đơn hàng từ workspace sale.
    Route::post('sales/orders/bulk-close', [SaleBulkCloseController::class, 'store'])->name('sales.orders.bulk-close');
    Route::post('sales/orders/{order}/call', [SaleOperationCallController::class, 'store'])->name('sales.orders.call');
    Route::post('sales/orders/{order}/operation-status', [SaleOperationStatusController::class, 'update'])->name('sales.orders.operation-status');
    Route::patch('sales/orders/{order}/operation-note', [SaleOperationNoteController::class, 'update'])->name('sales.orders.operation-note');
    Route::patch('sales/orders/{order}/desired-delivery-date', [DesiredDeliveryDateController::class, 'update'])->name('sales.orders.desired-delivery-date');
    Route::post('sales/orders/{order}/details', [SaleOperationOrderController::class, 'update'])->name('sales.orders.details');
    Route::delete('sales/orders/{order}', [SaleOrderDeletionController::class, 'destroy'])->name('sales.orders.destroy');
    Route::post('sales/orders/{order}/close', [OrderClosingController::class, 'store'])->name('sales.orders.close');
    Route::post('sales/leads/manual', [ManualLeadController::class, 'store'])->name('sales.leads.manual');

    // Đơn hàng lỗi / xóa đơn.
    Route::get('orders/failed', FailedOrdersController::class)->name('orders.failed');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::delete('failed-orders/{failedPartnerOrder}', [FailedPartnerOrderController::class, 'destroy'])->name('failed-orders.destroy');
});
