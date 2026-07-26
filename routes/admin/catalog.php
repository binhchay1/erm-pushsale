<?php

/**
 * Menu 1.3 Sản phẩm — danh mục sản phẩm, thuộc tính, combo.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->prefix('admin')->name('admin.')
 */

use App\Http\Controllers\Admin\Catalog\ProductComboController;
use App\Http\Controllers\Admin\ProductController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// 1.3.2 Quản lý combo
Route::get('catalog/combos', [ProductComboController::class, 'index'])->name('catalog.combos');
Route::post('catalog/combos/records', [ProductComboController::class, 'store'])->name('catalog.combos.store');
Route::match(['put', 'patch'], 'catalog/combos/records/{record}', [ProductComboController::class, 'update'])->whereNumber('record')->name('catalog.combos.update');
Route::delete('catalog/combos/records/{record}', [ProductComboController::class, 'destroy'])->whereNumber('record')->name('catalog.combos.destroy');
Route::post('catalog/combos/dialogs/{dialog}/records', [ProductComboController::class, 'storeDialog'])->where('dialog', '[a-z0-9\-]+')->name('catalog.combos.dialogs.store');
Route::match(['put', 'patch'], 'catalog/combos/dialogs/{dialog}/records/{record}', [ProductComboController::class, 'updateDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('catalog.combos.dialogs.update');
Route::delete('catalog/combos/dialogs/{dialog}/records/{record}', [ProductComboController::class, 'destroyDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('catalog.combos.dialogs.destroy');

Route::middleware('role:'.User::ROLE_ADMIN)->group(function (): void {
    // 1.3.1 Danh sách sản phẩm + import
    Route::get('products/import', [ProductController::class, 'importPage'])->name('products.import-page');
    Route::get('products/import/sample', [ProductController::class, 'importTemplate'])->name('products.import-template');
    Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
    Route::post('products/categories', [ProductController::class, 'storeCategory'])->name('products.categories.store');
    Route::patch('products/categories/{category}', [ProductController::class, 'updateCategory'])->name('products.categories.update');
    Route::delete('products/categories/{category}', [ProductController::class, 'destroyCategory'])->name('products.categories.destroy');
    Route::post('products/attributes', [ProductController::class, 'storeAttribute'])->name('products.attributes.store');
    Route::patch('products/attributes/{attribute}', [ProductController::class, 'updateAttribute'])->name('products.attributes.update');
    Route::delete('products/attributes/{attribute}', [ProductController::class, 'destroyAttribute'])->name('products.attributes.destroy');
    Route::post('products/attribute-values', [ProductController::class, 'storeAttributeValue'])->name('products.attribute-values.store');
    Route::patch('products/attribute-values/{attributeValue}', [ProductController::class, 'updateAttributeValue'])->name('products.attribute-values.update');
    Route::delete('products/attribute-values/{attributeValue}', [ProductController::class, 'destroyAttributeValue'])->name('products.attribute-values.destroy');
    Route::patch('products/{product}/business-status', [ProductController::class, 'updateBusinessStatus'])->name('products.business-status');
    Route::resource('products', ProductController::class)->except(['show']);
});
