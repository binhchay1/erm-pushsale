<?php

/**
 * Khu vực quản trị nền tảng đa đơn vị (/platform/...).
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->group(...)
 */

use App\Http\Controllers\Platform\CompanyController as PlatformCompanyController;
use App\Http\Controllers\Platform\SettingsController as PlatformSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('platform')->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('companies', [PlatformCompanyController::class, 'index'])->name('companies.index');
    Route::post('companies', [PlatformCompanyController::class, 'store'])->name('companies.store');
    Route::get('companies/{company}/accounts', [PlatformCompanyController::class, 'accounts'])->name('companies.accounts');
    Route::get('companies/{company}/admins', [PlatformCompanyController::class, 'admins'])->name('companies.admins');
    Route::post('companies/{company}/admins', [PlatformCompanyController::class, 'storeAdmin'])->name('companies.admins.store');
    Route::put('companies/{company}/admins/{admin}', [PlatformCompanyController::class, 'updateAdmin'])->name('companies.admins.update');
    Route::delete('companies/{company}/admins/{admin}', [PlatformCompanyController::class, 'destroyAdmin'])->name('companies.admins.destroy');
    Route::put('companies/{company}', [PlatformCompanyController::class, 'update'])->name('companies.update');
    Route::post('companies/{company}/toggle', [PlatformCompanyController::class, 'toggle'])->name('companies.toggle');

    Route::get('settings', [PlatformSettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [PlatformSettingsController::class, 'update'])->name('settings.update');
});
