<?php

/**
 * Menu 2.x Marketing — hồ sơ KH, kết nối landing/website/Facebook, nhập lead,
 * seeding, duyệt landing, dashboard marketing + 1.5 phân bổ data.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\DataDistributionController;
use App\Http\Controllers\Admin\LandingApprovalController;
use App\Http\Controllers\Admin\LeadIngestionController;
use App\Http\Controllers\Admin\LeadReviewController;
use App\Http\Controllers\Admin\LeadsLogController;
use App\Http\Controllers\Admin\ManualLeadAllocationController;
use App\Http\Controllers\Admin\ManualLeadController;
use App\Http\Controllers\Admin\Marketing\CampaignBudgetController;
use App\Http\Controllers\Admin\Marketing\CampaignReportController;
use App\Http\Controllers\Admin\Marketing\DashboardController as MarketingDashboardController;
use App\Http\Controllers\Admin\Marketing\DashboardDataController as MarketingDashboardDataController;
use App\Http\Controllers\Admin\Marketing\FacebookConnectController;
use App\Http\Controllers\Admin\Marketing\LandingConnectionsController;
use App\Http\Controllers\Admin\Marketing\LeadImportController;
use App\Http\Controllers\Admin\Marketing\ManualLeadEntryController;
use App\Http\Controllers\Admin\Marketing\PartnerConnectionController;
use App\Http\Controllers\Admin\Marketing\RevenueReportController as MarketingRevenueReportController;
use App\Http\Controllers\Admin\Marketing\SeedingNumberController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 2.3 Hồ sơ khách hàng (góc nhìn marketing)
Route::get('marketing/customers', CustomerProfileController::class)->name('marketing.customers');

/*
|--------------------------------------------------------------------------
| 2.4 Kết nối landing / website / duyệt landing
|--------------------------------------------------------------------------
| Landing và website dùng chung controller, khác nhau ở connection_type.
*/
foreach ([
    'marketing/landing-connections' => 'marketing.landing-connections',
    'marketing/website-connections' => 'marketing.website-connections',
] as $connectionPath => $connectionName) {
    Route::get($connectionPath, [LandingConnectionsController::class, 'index'])->name($connectionName);
    Route::get($connectionPath.'/records', fn () => redirect('/admin/'.$connectionPath))->name($connectionName.'.records');
    Route::post($connectionPath.'/records', [LandingConnectionsController::class, 'store'])->name($connectionName.'.store');
    Route::delete($connectionPath.'/records', [LandingConnectionsController::class, 'destroyMany'])->name($connectionName.'.destroy-many');
    Route::patch($connectionPath.'/records/{record}/flags', [LandingConnectionsController::class, 'updateFlags'])->whereNumber('record')->name($connectionName.'.flags');
    Route::match(['put', 'patch'], $connectionPath.'/records/{record}', [LandingConnectionsController::class, 'update'])->whereNumber('record')->name($connectionName.'.update');
    Route::delete($connectionPath.'/records/{record}', [LandingConnectionsController::class, 'destroy'])->whereNumber('record')->name($connectionName.'.destroy');
}

// 2.4.3 Duyệt landing — URL canonical duy nhất cho mọi vai trò dùng menu admin.
Route::get('marketing/landing-approvals', [LandingApprovalController::class, 'index'])->name('marketing.landing-approvals');
Route::post('marketing/landing-approvals/{connection}/approve', [LandingApprovalController::class, 'approve'])->whereNumber('connection')->name('marketing.landing-approvals.approve');
Route::post('marketing/landing-approvals/{connection}/reject', [LandingApprovalController::class, 'reject'])->whereNumber('connection')->name('marketing.landing-approvals.reject');

/*
|--------------------------------------------------------------------------
| 2.5 Kết nối Facebook
|--------------------------------------------------------------------------
*/
Route::get('marketing/facebook/connect', [FacebookConnectController::class, 'connect'])->name('marketing.facebook.connect');
Route::post('marketing/facebook/connect/sync', [FacebookConnectController::class, 'sync'])->name('marketing.facebook.connect.sync');
Route::get('marketing/facebook/posts', [FacebookConnectController::class, 'posts'])->name('marketing.facebook.posts');
Route::post('marketing/facebook/posts/sync', [FacebookConnectController::class, 'syncPosts'])->name('marketing.facebook.posts.sync');
Route::patch('marketing/facebook/posts/{post}', [FacebookConnectController::class, 'updatePost'])->whereNumber('post')->name('marketing.facebook.posts.update');

/*
|--------------------------------------------------------------------------
| 2.6 Nhập data
|--------------------------------------------------------------------------
*/
Route::get('marketing/leads/import', [LeadImportController::class, 'index'])->name('marketing.leads.import-page');
Route::get('marketing/leads/manual', [ManualLeadEntryController::class, 'index'])->name('marketing.leads.manual-page');

Route::get('marketing/partner-connections', [PartnerConnectionController::class, 'index'])->name('marketing.partner-connections');
Route::patch('marketing/partner-connections/provider', [PartnerConnectionController::class, 'toggleProvider'])->name('marketing.partner-connections.provider');
Route::get('marketing/partner-connections/eligible-sources', [PartnerConnectionController::class, 'eligibleSources'])->name('marketing.partner-connections.eligible');
Route::post('marketing/partner-connections/attach-sources', [PartnerConnectionController::class, 'attachSources'])->name('marketing.partner-connections.attach');
Route::patch('marketing/partner-connections/records/{record}/flags', [PartnerConnectionController::class, 'updateFlags'])->whereNumber('record')->name('marketing.partner-connections.flags');
Route::delete('marketing/partner-connections/records/{record}', [PartnerConnectionController::class, 'destroy'])->whereNumber('record')->name('marketing.partner-connections.destroy');

Route::get('marketing/seeding-numbers', [SeedingNumberController::class, 'index'])->name('marketing.seeding-numbers');
Route::post('marketing/seeding-numbers/records', [SeedingNumberController::class, 'store'])->name('marketing.seeding-numbers.store');
Route::match(['put', 'patch'], 'marketing/seeding-numbers/records/{record}', [SeedingNumberController::class, 'update'])->whereNumber('record')->name('marketing.seeding-numbers.update');
Route::delete('marketing/seeding-numbers/records/{record}', [SeedingNumberController::class, 'destroy'])->whereNumber('record')->name('marketing.seeding-numbers.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    // Tổng quan marketing
    Route::get('marketing/dashboard', MarketingDashboardController::class)->name('marketing.dashboard');
    Route::get('marketing/dashboard/chart', [MarketingDashboardDataController::class, 'chart'])->name('marketing.dashboard.chart');
    Route::get('marketing/dashboard/packets', [MarketingDashboardDataController::class, 'packets'])->name('marketing.dashboard.packets');
    Route::get('marketing/dashboard/daily-metrics', [MarketingDashboardDataController::class, 'dailyMetrics'])->name('marketing.dashboard.daily-metrics');
    Route::put('marketing/dashboard/daily-metrics', [MarketingDashboardDataController::class, 'saveDailyMetrics'])->name('marketing.dashboard.daily-metrics.update');
    Route::get('marketing/dashboard/export', [MarketingDashboardDataController::class, 'export'])->name('marketing.dashboard.export');
    Route::get('marketing/revenue', MarketingRevenueReportController::class)->name('marketing.revenue');
    Route::get('marketing/campaign-report', CampaignReportController::class)->name('marketing.campaign-report');
    Route::patch('marketing/campaigns/{campaign}/budget', [CampaignBudgetController::class, 'update'])->name('marketing.campaigns.budget');

    /*
    |----------------------------------------------------------------------
    | 1.5 Phân bổ data
    |----------------------------------------------------------------------
    */
    Route::get('leads', [DataDistributionController::class, 'index'])->name('leads.index');
    Route::get('leads/log', LeadsLogController::class)->name('leads.log');
    Route::post('leads/distribute', [DataDistributionController::class, 'store'])->name('leads.distribute');
    Route::post('leads/allocate', [ManualLeadAllocationController::class, 'store'])->name('leads.allocate');
    Route::post('leads/allocation-mode', [ManualLeadAllocationController::class, 'updateMode'])->name('leads.allocation-mode');
    Route::post('leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');
    Route::post('leads/import', [ManualLeadController::class, 'import'])->name('leads.import');
    Route::get('leads/import-template', [ManualLeadController::class, 'template'])->name('leads.import-template');
    Route::delete('leads/{leadIngestion}', [LeadIngestionController::class, 'destroy'])->name('leads.destroy');
    Route::patch('leads/{leadIngestion}/review', [LeadReviewController::class, 'update'])->name('leads.review');
});
