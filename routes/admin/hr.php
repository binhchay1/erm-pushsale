<?php

/**
 * Menu 1.2 Nhân sự — nhân viên, đội nhóm, ca làm việc, cấu hình chia số.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\Hr\CareDistributionRuleController;
use App\Http\Controllers\Admin\Hr\LeadDistributionRuleController;
use App\Http\Controllers\Admin\Hr\ReportAccessRuleController;
use App\Http\Controllers\Admin\Hr\WorkShiftController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 1.2.1 Danh sách nhân viên — mở cho nhánh quản lý có quyền HR, không chỉ admin.
Route::post('users/bulk', [UserController::class, 'storeBulk'])->name('users.bulk.store');
Route::patch('users/{user}/quick-update', [UserController::class, 'quickUpdate'])->name('users.quick-update');
Route::patch('users/{user}/operational-status', [UserController::class, 'updateOperationalStatus'])->name('users.operational-status');
Route::patch('users/{user}/password', [UserController::class, 'updatePassword'])->name('users.password');
Route::resource('users', UserController::class)->except(['show']);

// 1.2.3 Ca làm việc
Route::get('hr/work-shifts', [WorkShiftController::class, 'index'])->name('hr.work-shifts');
Route::post('hr/work-shifts/schedule', [WorkShiftController::class, 'saveSchedule'])->name('hr.work-shifts.schedule');
Route::post('hr/work-shifts/records', [WorkShiftController::class, 'store'])->name('hr.work-shifts.store');
Route::match(['put', 'patch'], 'hr/work-shifts/records/{record}', [WorkShiftController::class, 'update'])->whereNumber('record')->name('hr.work-shifts.update');
Route::delete('hr/work-shifts/records/{record}', [WorkShiftController::class, 'destroy'])->whereNumber('record')->name('hr.work-shifts.destroy');

// 1.2.4 Cấu hình chia số
Route::get('hr/lead-distribution-rules', [LeadDistributionRuleController::class, 'index'])->name('hr.lead-distribution-rules');
Route::post('hr/lead-distribution-rules/records', [LeadDistributionRuleController::class, 'store'])->name('hr.lead-distribution-rules.store');
Route::match(['put', 'patch'], 'hr/lead-distribution-rules/records/{record}', [LeadDistributionRuleController::class, 'update'])->whereNumber('record')->name('hr.lead-distribution-rules.update');
Route::delete('hr/lead-distribution-rules/records/{record}', [LeadDistributionRuleController::class, 'destroy'])->whereNumber('record')->name('hr.lead-distribution-rules.destroy');

// 1.2.5 Cấu hình tài khoản xem báo cáo
Route::get('hr/report-access-rules', [ReportAccessRuleController::class, 'index'])->name('hr.report-access-rules');
Route::post('hr/report-access-rules/records', [ReportAccessRuleController::class, 'store'])->name('hr.report-access-rules.store');
Route::match(['put', 'patch'], 'hr/report-access-rules/records/{record}', [ReportAccessRuleController::class, 'update'])->whereNumber('record')->name('hr.report-access-rules.update');
Route::delete('hr/report-access-rules/records/{record}', [ReportAccessRuleController::class, 'destroy'])->whereNumber('record')->name('hr.report-access-rules.destroy');

// 1.2.6 Cấu hình chia số care đơn
Route::get('hr/care-distribution-rules', [CareDistributionRuleController::class, 'index'])->name('hr.care-distribution-rules');
Route::post('hr/care-distribution-rules/records', [CareDistributionRuleController::class, 'store'])->name('hr.care-distribution-rules.store');
Route::match(['put', 'patch'], 'hr/care-distribution-rules/records/{record}', [CareDistributionRuleController::class, 'update'])->whereNumber('record')->name('hr.care-distribution-rules.update');
Route::delete('hr/care-distribution-rules/records/{record}', [CareDistributionRuleController::class, 'destroy'])->whereNumber('record')->name('hr.care-distribution-rules.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    // 1.2.2 Quản lý đội nhóm
    Route::resource('teams', TeamController::class)->except(['show']);
});
