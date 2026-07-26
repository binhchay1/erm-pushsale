<?php

/**
 * Menu 1.7 Bảo mật · 1.13 Blacklist + nhật ký hoạt động.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\Security\LeadFilterHistoryController;
use App\Http\Controllers\Admin\Security\LoginAccessController;
use App\Http\Controllers\Admin\Security\LoginHistoryController;
use App\Http\Controllers\Admin\Security\PhoneBlacklistController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 1.7.1 Lịch sử đăng nhập
Route::get('security/login-history', [LoginHistoryController::class, 'index'])->name('security.login-history');

// 1.7.2 Quản lý cho phép tài khoản đăng nhập
Route::get('security/login-access', [LoginAccessController::class, 'index'])->name('security.login-access');
Route::patch('security/login-access/users/{user}/approve', [LoginAccessController::class, 'approve'])->whereNumber('user')->name('security.login-access.approve');
Route::patch('security/login-access/users/{user}/block', [LoginAccessController::class, 'block'])->whereNumber('user')->name('security.login-access.block');

// 1.7.3 Lịch sử lọc data chốt đơn
Route::get('security/lead-filter-history', [LeadFilterHistoryController::class, 'index'])->name('security.lead-filter-history');

// 1.13.1 Quản lý số blacklist
Route::get('security/phone-blacklist', [PhoneBlacklistController::class, 'index'])->name('security.phone-blacklist');
Route::post('security/phone-blacklist/records', [PhoneBlacklistController::class, 'store'])->name('security.phone-blacklist.store');
Route::match(['put', 'patch'], 'security/phone-blacklist/records/{record}', [PhoneBlacklistController::class, 'update'])->whereNumber('record')->name('security.phone-blacklist.update');
Route::delete('security/phone-blacklist/records/{record}', [PhoneBlacklistController::class, 'destroy'])->whereNumber('record')->name('security.phone-blacklist.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
});
