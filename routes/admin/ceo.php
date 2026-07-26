<?php

/**
 * Menu 7.x CEO — kế hoạch kinh doanh tháng/năm, danh mục KPI, thưởng doanh số.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\Ceo\KpiCatalogController;
use App\Http\Controllers\Admin\Ceo\MonthlyBusinessPlanController;
use App\Http\Controllers\Admin\Ceo\RevenueBonusController;
use App\Http\Controllers\Admin\Ceo\YearlyBusinessPlanController;
use Illuminate\Support\Facades\Route;

// 7.1.1 Kế hoạch kinh doanh tháng
Route::get('ceo/business-plan/monthly', [MonthlyBusinessPlanController::class, 'index'])->name('ceo.business-plan.monthly');
Route::post('ceo/business-plan/monthly/records', [MonthlyBusinessPlanController::class, 'store'])->name('ceo.business-plan.monthly.store');
Route::match(['put', 'patch'], 'ceo/business-plan/monthly/records/{record}', [MonthlyBusinessPlanController::class, 'update'])->whereNumber('record')->name('ceo.business-plan.monthly.update');
Route::delete('ceo/business-plan/monthly/records/{record}', [MonthlyBusinessPlanController::class, 'destroy'])->whereNumber('record')->name('ceo.business-plan.monthly.destroy');
Route::post('ceo/business-plan/monthly/add-missing', [MonthlyBusinessPlanController::class, 'addMissing'])->name('ceo.business-plan.monthly.add-missing');
Route::post('ceo/business-plan/monthly/copy-previous', [MonthlyBusinessPlanController::class, 'copyPrevious'])->name('ceo.business-plan.monthly.copy-previous');
Route::post('ceo/business-plan/monthly/lock-period', [MonthlyBusinessPlanController::class, 'lockPeriod'])->name('ceo.business-plan.monthly.lock-period');
Route::post('ceo/business-plan/monthly/bulk-save', [MonthlyBusinessPlanController::class, 'bulkSave'])->name('ceo.business-plan.monthly.bulk-save');

// 7.1.2 Lập kế hoạch kinh doanh năm
Route::get('ceo/business-plan/yearly', [YearlyBusinessPlanController::class, 'index'])->name('ceo.business-plan.yearly');
Route::post('ceo/business-plan/yearly/planned-data', [YearlyBusinessPlanController::class, 'storePlannedData'])->name('ceo.business-plan.yearly.planned-data');

// 7.1.3 Danh mục KPI
Route::get('ceo/business-plan/kpi-catalog', [KpiCatalogController::class, 'index'])->name('ceo.business-plan.kpi-catalog');
Route::post('ceo/business-plan/kpi-catalog/records', [KpiCatalogController::class, 'store'])->name('ceo.business-plan.kpi-catalog.store');
Route::match(['put', 'patch'], 'ceo/business-plan/kpi-catalog/records/{record}', [KpiCatalogController::class, 'update'])->whereNumber('record')->name('ceo.business-plan.kpi-catalog.update');
Route::delete('ceo/business-plan/kpi-catalog/records/{record}', [KpiCatalogController::class, 'destroy'])->whereNumber('record')->name('ceo.business-plan.kpi-catalog.destroy');
Route::post('ceo/business-plan/kpi-catalog/initialize-defaults', [KpiCatalogController::class, 'initializeDefaults'])->name('ceo.business-plan.kpi-catalog.initialize-defaults');
Route::post('ceo/business-plan/kpi-catalog/bulk-save', [KpiCatalogController::class, 'bulkSave'])->name('ceo.business-plan.kpi-catalog.bulk-save');

// 7.1.4 Khai báo thưởng theo doanh số
Route::get('ceo/business-plan/revenue-bonus', [RevenueBonusController::class, 'index'])->name('ceo.business-plan.revenue-bonus');
Route::post('ceo/business-plan/revenue-bonus/bulk-save', [RevenueBonusController::class, 'bulkSave'])->name('ceo.business-plan.revenue-bonus.bulk-save');
Route::delete('ceo/business-plan/revenue-bonus/records/{record}', [RevenueBonusController::class, 'destroy'])->whereNumber('record')->name('ceo.business-plan.revenue-bonus.destroy');
Route::post('ceo/business-plan/revenue-bonus/copy-previous', [RevenueBonusController::class, 'copyPrevious'])->name('ceo.business-plan.revenue-bonus.copy-previous');
Route::post('ceo/business-plan/revenue-bonus/lock-period', [RevenueBonusController::class, 'setLocked'])->name('ceo.business-plan.revenue-bonus.lock-period');
