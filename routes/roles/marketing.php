<?php

/**
 * Không gian làm việc của vai trò Marketing (/marketing/...).
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->group(...)
 */

use App\Http\Controllers\Admin\DataDistributionController;
use App\Http\Controllers\Admin\LandingApprovalController;
use App\Http\Controllers\Admin\ManualLeadController;
use App\Http\Controllers\Admin\Marketing\CampaignBudgetController;
use App\Http\Controllers\Admin\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Admin\Marketing\DashboardDataController as MarketingDashboardDataController;
use App\Http\Controllers\Admin\Marketing\RevenueReportController as MarketingRevenueReportController;
use App\Http\Controllers\Marketing\CampaignReportController as MarketingCampaignReportController;
use App\Http\Controllers\Marketing\DashboardController as RoleMarketingDashboardController;
use App\Http\Controllers\Marketing\RankingController as MarketingRankingController;
use App\Http\Controllers\Reports\ExtraReportController;
use App\Http\Controllers\Reports\HourlyStatsController;
use App\Http\Controllers\Reports\TeamLeaderStatsController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.User::ROLE_MARKETING)->prefix('marketing')->name('marketing.')->group(function (): void {
    Route::get('dashboard', RoleMarketingDashboardController::class)->name('dashboard');
    Route::get('customers', CustomerProfileController::class)->name('customers');
    Route::get('rankings', MarketingRankingController::class)->name('rankings');

    Route::get('workspace', MarketingDashboardController::class)->name('workspace');
    Route::get('workspace/chart', [MarketingDashboardDataController::class, 'chart'])->name('workspace.chart');
    Route::get('workspace/daily-metrics', [MarketingDashboardDataController::class, 'dailyMetrics'])->name('workspace.daily-metrics');
    Route::put('workspace/daily-metrics', [MarketingDashboardDataController::class, 'saveDailyMetrics'])->name('workspace.daily-metrics.update');
    Route::get('workspace/export', [MarketingDashboardDataController::class, 'export'])->name('workspace.export');

    // Luồng "chiến dịch" cũ đã được thay bằng Kết nối landing.
    Route::redirect('campaigns', '/admin/marketing/landing-connections', 301)->name('campaigns.index');
    Route::redirect('campaigns/create', '/admin/marketing/landing-connections', 301)->name('campaigns.create');
    Route::redirect('campaigns/{campaign}/edit', '/admin/marketing/landing-connections', 301)->name('campaigns.edit');
    Route::post('campaigns', fn () => abort(410, 'Luồng tạo chiến dịch đã được thay bằng Kết nối landing.'))->name('campaigns.store');
    Route::put('campaigns/{campaign}', fn () => abort(410, 'Luồng cập nhật chiến dịch đã được thay bằng Kết nối landing.'))->whereNumber('campaign')->name('campaigns.update');
    Route::delete('campaigns/{campaign}', fn () => abort(410, 'Luồng xóa chiến dịch đã được thay bằng Kết nối landing.'))->whereNumber('campaign')->name('campaigns.destroy');
    Route::patch('campaigns/{campaign}/budget', [CampaignBudgetController::class, 'update'])->name('campaigns.budget');

    Route::get('landing-approvals', [LandingApprovalController::class, 'index'])->name('landing-approvals.index');
    Route::post('landing-approvals/{connection}/approve', [LandingApprovalController::class, 'approve'])->name('landing-approvals.approve');
    Route::post('landing-approvals/{connection}/reject', [LandingApprovalController::class, 'reject'])->name('landing-approvals.reject');

    Route::get('revenue', MarketingRevenueReportController::class)->name('revenue');
    Route::get('campaign-report', MarketingCampaignReportController::class)->name('campaign-report');
    Route::get('reports/hourly', HourlyStatsController::class)->name('reports.hourly');
    Route::get('reports/team-leaders', TeamLeaderStatsController::class)->name('reports.team-leaders');
    Route::get('reports/{report}', ExtraReportController::class)->where('report', '[a-z0-9\-]+')->name('reports.extra');

    Route::get('leads', [DataDistributionController::class, 'index'])->name('leads.index');
    Route::post('leads/distribute', [DataDistributionController::class, 'store'])->name('leads.distribute');
    Route::post('leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');
    Route::post('leads/import', [ManualLeadController::class, 'import'])->name('leads.import');
    Route::get('leads/import-template', [ManualLeadController::class, 'template'])->name('leads.import-template');
});
