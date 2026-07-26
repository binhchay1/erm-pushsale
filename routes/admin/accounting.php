<?php

/**
 * Menu 6.x Kế toán — chi phí, danh mục chi phí, kế hoạch tháng, hóa đơn điện tử.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\Accounting\ElectronicInvoiceController;
use App\Http\Controllers\Admin\Accounting\ExpenseCategoryController;
use App\Http\Controllers\Admin\Accounting\ExpenseGroupController;
use App\Http\Controllers\Admin\Accounting\ExpenseUnitController;
use App\Http\Controllers\Admin\Accounting\MonthlyPlanSummaryController;
use App\Http\Controllers\Admin\Accounting\OperationsController as AccountingOperationsController;
use App\Http\Controllers\Admin\Accounting\UnitExpenseController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 6.2.1 Quản lý chi phí đơn vị
Route::get('accounting/expenses', [UnitExpenseController::class, 'index'])->name('accounting.expenses');
Route::post('accounting/expenses/records', [UnitExpenseController::class, 'store'])->name('accounting.expenses.store');
Route::match(['put', 'patch'], 'accounting/expenses/records/{record}', [UnitExpenseController::class, 'update'])->whereNumber('record')->name('accounting.expenses.update');
Route::delete('accounting/expenses/records/{record}', [UnitExpenseController::class, 'destroy'])->whereNumber('record')->name('accounting.expenses.destroy');

// 6.2.2 Danh mục chi phí
Route::get('accounting/expense-categories', [ExpenseCategoryController::class, 'index'])->name('accounting.expense-categories');
Route::post('accounting/expense-categories/records', [ExpenseCategoryController::class, 'store'])->name('accounting.expense-categories.store');
Route::match(['put', 'patch'], 'accounting/expense-categories/records/{record}', [ExpenseCategoryController::class, 'update'])->whereNumber('record')->name('accounting.expense-categories.update');
Route::delete('accounting/expense-categories/records/{record}', [ExpenseCategoryController::class, 'destroy'])->whereNumber('record')->name('accounting.expense-categories.destroy');

// 6.2.3 Danh mục nhóm chi phí
Route::get('accounting/expense-groups', [ExpenseGroupController::class, 'index'])->name('accounting.expense-groups');
Route::post('accounting/expense-groups/records', [ExpenseGroupController::class, 'store'])->name('accounting.expense-groups.store');
Route::match(['put', 'patch'], 'accounting/expense-groups/records/{record}', [ExpenseGroupController::class, 'update'])->whereNumber('record')->name('accounting.expense-groups.update');
Route::delete('accounting/expense-groups/records/{record}', [ExpenseGroupController::class, 'destroy'])->whereNumber('record')->name('accounting.expense-groups.destroy');

// 6.2.4 Danh mục đơn vị tính
Route::get('accounting/expense-units', [ExpenseUnitController::class, 'index'])->name('accounting.expense-units');
Route::post('accounting/expense-units/records', [ExpenseUnitController::class, 'store'])->name('accounting.expense-units.store');
Route::match(['put', 'patch'], 'accounting/expense-units/records/{record}', [ExpenseUnitController::class, 'update'])->whereNumber('record')->name('accounting.expense-units.update');
Route::delete('accounting/expense-units/records/{record}', [ExpenseUnitController::class, 'destroy'])->whereNumber('record')->name('accounting.expense-units.destroy');

// 6.3.5 Tổng kết kế hoạch tháng
Route::get('accounting/reports/monthly-plan', [MonthlyPlanSummaryController::class, 'index'])->name('accounting.reports.monthly-plan');
Route::post('accounting/reports/monthly-plan/records', [MonthlyPlanSummaryController::class, 'store'])->name('accounting.reports.monthly-plan.store');
Route::match(['put', 'patch'], 'accounting/reports/monthly-plan/records/{record}', [MonthlyPlanSummaryController::class, 'update'])->whereNumber('record')->name('accounting.reports.monthly-plan.update');
Route::delete('accounting/reports/monthly-plan/records/{record}', [MonthlyPlanSummaryController::class, 'destroy'])->whereNumber('record')->name('accounting.reports.monthly-plan.destroy');

// 6.4 Xử lý xuất hóa đơn điện tử
Route::get('accounting/electronic-invoices', [ElectronicInvoiceController::class, 'index'])->name('accounting.electronic-invoices');
Route::post('accounting/electronic-invoices/records', [ElectronicInvoiceController::class, 'store'])->name('accounting.electronic-invoices.store');
Route::match(['put', 'patch'], 'accounting/electronic-invoices/records/{record}', [ElectronicInvoiceController::class, 'update'])->whereNumber('record')->name('accounting.electronic-invoices.update');
Route::delete('accounting/electronic-invoices/records/{record}', [ElectronicInvoiceController::class, 'destroy'])->whereNumber('record')->name('accounting.electronic-invoices.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    Route::get('accounting', AccountingOperationsController::class)->name('accounting');
});
