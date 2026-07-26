<?php

/**
 * Menu 8.x Báo cáo hệ thống + tổng quan điều hành và các báo cáo cấu hình động.
 *
 * Required LAST from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 *
 * Nạp cuối vì vòng lặp config/pushsale_report_routes.php phải biết trang nào
 * đã có route riêng để không đăng ký trùng tên.
 */

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Reports\CareAllocationReportController;
use App\Http\Controllers\Admin\Reports\CareOrderReportController;
use App\Http\Controllers\Admin\Reports\CeoReportController;
use App\Http\Controllers\Admin\Reports\DataAllocationReportController;
use App\Http\Controllers\Admin\Reports\DataAllocationV2ReportController;
use App\Http\Controllers\Admin\Reports\PowerDashboardController;
use App\Http\Controllers\Admin\Reports\RepurchaseByProductReportController;
use App\Http\Controllers\Admin\Reports\RepurchaseReportController;
use App\Http\Controllers\Admin\Reports\TrendsReportController;
use App\Http\Controllers\Reports\ExtraReportController;
use App\Http\Controllers\Reports\HourlyStatsController;
use App\Http\Controllers\Reports\TeamLeaderStatsController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 8.5.4 Biểu đồ xu hướng
Route::get('reports/trends', [TrendsReportController::class, 'index'])->name('reports.trends');

// 8.5.5 / 8.5.15 Tổng hợp kết quả chia data trong ngày
Route::get('reports/data-allocation', [DataAllocationReportController::class, 'index'])->name('reports.data-allocation');
Route::get('reports/data-allocation-v2', [DataAllocationV2ReportController::class, 'index'])->name('reports.data-allocation-v2');

// 8.5.9 Power dashboard
Route::get('reports/power-dashboard', [PowerDashboardController::class, 'index'])->name('reports.power-dashboard');

// 8.5.10 / 8.5.11 Thống kê mua lại
Route::get('reports/repurchase', [RepurchaseReportController::class, 'index'])->name('reports.repurchase');
Route::get('reports/repurchase-products', [RepurchaseByProductReportController::class, 'index'])->name('reports.repurchase-products');

// 8.5.16 / 8.5.17 Care đơn
Route::get('reports/care-orders', [CareOrderReportController::class, 'index'])->name('reports.care-orders');
Route::get('reports/care-allocation', [CareAllocationReportController::class, 'index'])->name('reports.care-allocation');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    // "Tổng quan vận hành" đã gộp vào "Tổng quan điều hành". Giữ link cũ sống.
    Route::get('reports/business', fn () => redirect()->route('admin.reports.system-business'))->name('reports.business');

    Route::get('reports/ceo', CeoReportController::class)->name('reports.ceo');
    Route::get('reports/ceo-dashboard-v2', CeoReportController::class)->name('reports.ceo-dashboard-v2');
    Route::redirect('sales/reports/ceo-dashboard-v2', '/admin/reports/ceo-dashboard-v2', 301)->name('sales.reports.ceo-dashboard-v2.redirect');

    Route::get('reports/hourly', HourlyStatsController::class)->name('reports.hourly');
    Route::get('reports/team-leaders', TeamLeaderStatsController::class)->name('reports.team-leaders');
    Route::get('reports/extra/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');

    /*
    |----------------------------------------------------------------------
    | Báo cáo cấu hình động (config/pushsale_report_routes.php)
    |----------------------------------------------------------------------
    | Một vài báo cáo đã có trang riêng theo schema (vd. 4.6.2 Báo cáo công
    | việc sale). Bỏ qua các key trùng để không đăng ký hai route cùng tên.
    */
    foreach ((array) config('pushsale_report_routes', []) as $reportKey => $routeConfig) {
        $adminPath = (string) ($routeConfig['admin_path'] ?? '');
        $routeName = (string) ($routeConfig['route_name'] ?? '');

        if ($adminPath === '' || $routeName === '' || ! str_starts_with($adminPath, '/admin/')) {
            continue;
        }

        if (Route::getRoutes()->getByName('admin.'.$routeName) !== null) {
            continue;
        }

        Route::get(substr($adminPath, strlen('/admin/')), function (Request $request, ExtraReportController $controller) use ($reportKey) {
            return $controller($request, (string) $reportKey);
        })->name($routeName);
    }
});
