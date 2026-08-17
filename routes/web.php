<?php

/**
 * Xương sống routing: public + auth + phần dùng chung cho mọi vai trò,
 * rồi require các module theo domain.
 *
 * - routes/admin/*.php   → menu admin 1.x–8.x (prefix /admin, name admin.)
 * - routes/roles/*.php   → không gian làm việc theo vai trò (/sales, /marketing, …)
 * - routes/legacy.php    → 301 từ URL đời Pushsale cũ
 *
 * Quy ước đặt tên: AGENTS.md + docs/PROJECT_CONTRACT.md (§0b).
 */

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerInteractions\CustomerDataViewHistoryController;
use App\Http\Controllers\CustomerInteractions\CustomerInternalMessageController;
use App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController;
use App\Http\Controllers\CustomerInteractions\CustomerPurchaseHistoryController;
use App\Http\Controllers\CustomerInteractions\CustomerSupplementPacketController;
use App\Http\Controllers\CustomerInteractions\OrderOperationHistoryController;
use App\Http\Controllers\CustomerInteractions\PancakeCustomerMessageController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrgChartController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Sales\CustomerProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Testing\StagingTestController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['web', 'auth', 'tenant']]);

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::match(['get', 'post'], 'locale', [LocaleController::class, 'update'])->name('locale.update');
Route::get('/', HomeController::class)->name('home');

// Trang vệ tinh công khai (SEO) — không cần đăng nhập.
Route::get('features', [MarketingController::class, 'features'])->name('marketing.features');
Route::get('solutions', [MarketingController::class, 'solutions'])->name('marketing.solutions');
Route::get('docs', [MarketingController::class, 'docs'])->name('marketing.docs');
Route::get('about', [MarketingController::class, 'about'])->name('marketing.about');
Route::get('contact', [MarketingController::class, 'contact'])->name('marketing.contact');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

// QA trên staging. Bảo vệ bằng ERM_STAGING_TEST_MODE + ERM_STAGING_TEST_SECRET.
Route::prefix('__erm-test')->name('staging-test.')->group(function (): void {
    Route::get('health', [StagingTestController::class, 'health'])->name('health');
    Route::get('pages', [StagingTestController::class, 'pages'])->name('pages');
    Route::get('bootstrap', [StagingTestController::class, 'bootstrap'])->name('bootstrap');
    Route::get('demo-ui', [StagingTestController::class, 'demoUi'])->name('demo-ui');
    Route::get('flow', [StagingTestController::class, 'flow'])->name('flow');
    Route::get('landing-flow', [StagingTestController::class, 'landingFlow'])->name('landing-flow');
    Route::get('audit', [StagingTestController::class, 'audit'])->name('audit');
    Route::get('logs', [StagingTestController::class, 'logs'])->name('logs');
});

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

Route::middleware(['auth', 'tenant', 'permissions'])->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    /*
    |----------------------------------------------------------------------
    | Dùng chung cho mọi vai trò
    |----------------------------------------------------------------------
    */
    Route::get('org-chart', [OrgChartController::class, 'index'])->name('org-chart.index');

    // Địa giới hành chính cho ô chọn Tỉnh/Huyện/Xã (cascading).
    Route::get('geo/provinces', [GeoController::class, 'provinces'])->name('geo.provinces');
    Route::get('geo/provinces/{province}/districts', [GeoController::class, 'districts'])->name('geo.districts');
    Route::get('geo/provinces/{province}/wards', [GeoController::class, 'provinceWards'])->name('geo.province-wards');
    Route::get('geo/districts/{district}/wards', [GeoController::class, 'wards'])->name('geo.wards');

    Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');

    // Cấu hình chức năng của đơn vị. URL theo menu là /admin/settings/features (menu 1.6).
    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    /*
    |----------------------------------------------------------------------
    | Hồ sơ khách hàng dùng chung (quyền customers:view)
    |----------------------------------------------------------------------
    | Dialog lịch sử tác nghiệp / mua hàng / tin nội bộ / chat Pancake.
    | customers:view gửi tin nội bộ; customer_chat:full gửi qua Pancake.
    */
    Route::get('customers', CustomerProfileController::class)->name('customers.index');
    Route::get('customers/orders/{order}/operation-history', [OrderOperationHistoryController::class, 'index'])->name('customers.orders.operation-history');
    Route::get('customers/orders/{order}/purchase-history', [CustomerPurchaseHistoryController::class, 'index'])->name('customers.orders.purchase-history');
    Route::get('customers/orders/{order}/data-view-history', [CustomerDataViewHistoryController::class, 'index'])->name('customers.orders.data-view-history');
    Route::get('customers/orders/{order}/messages', [CustomerInternalMessageController::class, 'index'])->name('customers.orders.messages.index');
    Route::post('customers/orders/{order}/messages', [CustomerInternalMessageController::class, 'store'])->name('customers.orders.messages.store');
    Route::get('customers/orders/{order}/pancake-messages', [PancakeCustomerMessageController::class, 'index'])->name('customers.orders.pancake-messages.index');
    Route::post('customers/orders/{order}/pancake-messages', [PancakeCustomerMessageController::class, 'store'])->name('customers.orders.pancake-messages.store');
    Route::get('customers/orders/{order}/supplement-packets', [CustomerSupplementPacketController::class, 'index'])->name('customers.orders.supplement-packets.index');
    Route::post('customers/orders/{order}/supplement-packets/{leadIngestion}/review', [CustomerSupplementPacketController::class, 'store'])->name('customers.orders.supplement-packets.review');

    Route::match(['get', 'post'], 'customers/export', [CustomerProfileBulkActionController::class, 'export'])->name('customers.export');
    Route::post('customers/bulk/reallocate-now', [CustomerProfileBulkActionController::class, 'reallocateNow'])->name('customers.bulk.reallocate-now');
    Route::post('customers/bulk/queue-reallocation', [CustomerProfileBulkActionController::class, 'queueReallocation'])->name('customers.bulk.queue-reallocation');
    Route::post('customers/bulk/recall', [CustomerProfileBulkActionController::class, 'recall'])->name('customers.bulk.recall');
    Route::delete('customers/bulk/operation-history', [CustomerProfileBulkActionController::class, 'deleteOperationHistory'])->name('customers.bulk.operation-history.destroy');

    /*
    |----------------------------------------------------------------------
    | Menu admin theo nhóm 1.x–8.x
    |----------------------------------------------------------------------
    | Gate vai trò nằm trong từng file (role:admin bọc phần chỉ admin dùng),
    | phần còn lại chỉ qua middleware `permissions`.
    | reports.php nạp cuối vì phải biết route nào đã đăng ký.
    */
    Route::prefix('admin')->name('admin.')->group(function (): void {
        require __DIR__.'/admin/company.php';
        require __DIR__.'/admin/hr.php';
        require __DIR__.'/admin/catalog.php';
        require __DIR__.'/admin/security.php';
        require __DIR__.'/admin/operations-config.php';
        require __DIR__.'/admin/integrations.php';
        require __DIR__.'/admin/marketing.php';
        require __DIR__.'/admin/customers.php';
        require __DIR__.'/admin/sales.php';
        require __DIR__.'/admin/warehouse.php';
        require __DIR__.'/admin/accounting.php';
        require __DIR__.'/admin/ceo.php';
        require __DIR__.'/admin/reports.php';
    });

    /*
    |----------------------------------------------------------------------
    | Không gian làm việc theo vai trò
    |----------------------------------------------------------------------
    */
    require __DIR__.'/roles/sales.php';
    require __DIR__.'/roles/marketing.php';
    require __DIR__.'/roles/warehouse.php';
    require __DIR__.'/roles/accounting.php';
    require __DIR__.'/roles/allocator.php';
    require __DIR__.'/roles/platform.php';

    require __DIR__.'/legacy.php';
});
