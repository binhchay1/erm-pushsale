<?php

/**
 * Không gian làm việc của vai trò Chia data (/allocator/...).
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->group(...)
 */

use App\Http\Controllers\Admin\DataDistributionController;
use App\Http\Controllers\Admin\LeadReviewController;
use App\Http\Controllers\Admin\ManualLeadAllocationController;
use App\Http\Controllers\Admin\ManualLeadController;
use App\Http\Controllers\Allocator\DashboardController as AllocatorDashboardController;
use App\Http\Controllers\Allocator\ReportController as AllocatorReportController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::middleware('role:'.User::ROLE_ALLOCATOR)->prefix('allocator')->name('allocator.')->group(function (): void {
    Route::get('dashboard', AllocatorDashboardController::class)->name('dashboard');
    Route::get('workspace', [DataDistributionController::class, 'index'])->name('workspace');
    Route::get('customers', CustomerProfileController::class)->name('customers.index');

    Route::post('leads/distribute', [DataDistributionController::class, 'store'])->name('leads.distribute');
    Route::post('leads/allocate', [ManualLeadAllocationController::class, 'store'])->name('leads.allocate');
    Route::patch('leads/{leadIngestion}/review', [LeadReviewController::class, 'update'])->name('leads.review');
    Route::post('leads/allocation-mode', [ManualLeadAllocationController::class, 'updateMode'])->name('leads.allocation-mode');
    Route::post('leads/manual', [ManualLeadController::class, 'store'])->name('leads.manual');
    Route::post('leads/import', [ManualLeadController::class, 'import'])->name('leads.import');
    Route::get('leads/import-template', [ManualLeadController::class, 'template'])->name('leads.import-template');

    Route::get('reports/{report}', AllocatorReportController::class)->where('report', '[a-z0-9\-]+')->name('reports');
});
