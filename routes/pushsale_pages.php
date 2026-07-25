<?php

use Illuminate\Support\Facades\Route;

Route::get('company/subscription-history', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_1_2Controller::class, 'index'])->name('company.subscription-history');
Route::post('company/subscription-history/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_1_2Controller::class, 'store'])->name('company.subscription-history.store');
Route::match(['put', 'patch'], 'company/subscription-history/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_1_2Controller::class, 'update'])->whereNumber('record')->name('company.subscription-history.update');
Route::delete('company/subscription-history/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_1_2Controller::class, 'destroy'])->whereNumber('record')->name('company.subscription-history.destroy');
Route::redirect('pages/1-1-2-lich-su-dang-ky-goi-dich-vu', '/admin/company/subscription-history', 301);

Route::redirect('hr/employees', '/admin/users', 301);
Route::redirect('pages/1-2-1-danh-sach-nhan-vien', '/admin/users', 301);

Route::redirect('hr/teams', '/admin/teams', 301);
Route::redirect('pages/1-2-2-quan-ly-doi-nhom', '/admin/teams', 301);

Route::get('hr/work-shifts', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'index'])->name('hr.work-shifts');
Route::post('hr/work-shifts/schedule', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'saveSchedule'])->name('hr.work-shifts.schedule');
Route::post('hr/work-shifts/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'store'])->name('hr.work-shifts.store');
Route::match(['put', 'patch'], 'hr/work-shifts/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'update'])->whereNumber('record')->name('hr.work-shifts.update');
Route::delete('hr/work-shifts/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_3Controller::class, 'destroy'])->whereNumber('record')->name('hr.work-shifts.destroy');
Route::redirect('pages/1-2-3-ca-lam-viec', '/admin/hr/work-shifts', 301);

Route::get('hr/lead-distribution-rules', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_4Controller::class, 'index'])->name('hr.lead-distribution-rules');
Route::post('hr/lead-distribution-rules/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_4Controller::class, 'store'])->name('hr.lead-distribution-rules.store');
Route::match(['put', 'patch'], 'hr/lead-distribution-rules/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_4Controller::class, 'update'])->whereNumber('record')->name('hr.lead-distribution-rules.update');
Route::delete('hr/lead-distribution-rules/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_4Controller::class, 'destroy'])->whereNumber('record')->name('hr.lead-distribution-rules.destroy');
Route::redirect('pages/1-2-4-danh-sach-cau-hinh-chia-so', '/admin/hr/lead-distribution-rules', 301);

Route::get('hr/report-access-rules', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_5Controller::class, 'index'])->name('hr.report-access-rules');
Route::post('hr/report-access-rules/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_5Controller::class, 'store'])->name('hr.report-access-rules.store');
Route::match(['put', 'patch'], 'hr/report-access-rules/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_5Controller::class, 'update'])->whereNumber('record')->name('hr.report-access-rules.update');
Route::delete('hr/report-access-rules/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_5Controller::class, 'destroy'])->whereNumber('record')->name('hr.report-access-rules.destroy');
Route::redirect('pages/1-2-5-cau-hinh-tai-khoan-xem-bao-cao', '/admin/hr/report-access-rules', 301);

Route::get('hr/care-distribution-rules', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_6Controller::class, 'index'])->name('hr.care-distribution-rules');
Route::post('hr/care-distribution-rules/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_6Controller::class, 'store'])->name('hr.care-distribution-rules.store');
Route::match(['put', 'patch'], 'hr/care-distribution-rules/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_6Controller::class, 'update'])->whereNumber('record')->name('hr.care-distribution-rules.update');
Route::delete('hr/care-distribution-rules/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_2_6Controller::class, 'destroy'])->whereNumber('record')->name('hr.care-distribution-rules.destroy');
Route::redirect('pages/1-2-6-danh-sach-cau-hinh-chia-so-care-don', '/admin/hr/care-distribution-rules', 301);

Route::redirect('catalog/products', '/admin/products', 301);
Route::redirect('catalog/products/import', '/admin/products/import', 301);
Route::redirect('pages/1-3-1-quan-ly-san-pham', '/admin/products', 301);
Route::redirect('pages/1-3-1-import-san-pham', '/admin/products/import', 301);

Route::get('catalog/combos', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'index'])->name('catalog.combos');
Route::post('catalog/combos/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'store'])->name('catalog.combos.store');
Route::match(['put', 'patch'], 'catalog/combos/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'update'])->whereNumber('record')->name('catalog.combos.update');
Route::delete('catalog/combos/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'destroy'])->whereNumber('record')->name('catalog.combos.destroy');
Route::post('catalog/combos/dialogs/{dialog}/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'storeDialog'])->where('dialog', '[a-z0-9\-]+')->name('catalog.combos.dialogs.store');
Route::match(['put', 'patch'], 'catalog/combos/dialogs/{dialog}/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'updateDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('catalog.combos.dialogs.update');
Route::delete('catalog/combos/dialogs/{dialog}/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_3_2Controller::class, 'destroyDialog'])->where(['dialog' => '[a-z0-9\-]+', 'record' => '[0-9]+'])->name('catalog.combos.dialogs.destroy');
Route::redirect('pages/1-3-2-danh-sach-combo', '/admin/catalog/combos', 301);

Route::get('security/login-history', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_7_1Controller::class, 'index'])->name('security.login-history');
Route::redirect('pages/1-7-1-lich-su-dang-nhap', '/admin/security/login-history', 301);

Route::get('security/login-access', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_7_2Controller::class, 'index'])->name('security.login-access');
Route::patch('security/login-access/users/{user}/approve', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_7_2Controller::class, 'approve'])->whereNumber('user')->name('security.login-access.approve');
Route::patch('security/login-access/users/{user}/block', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_7_2Controller::class, 'block'])->whereNumber('user')->name('security.login-access.block');
Route::redirect('pages/1-7-2-quan-ly-cho-phep-tai-khoan-dang-nhap', '/admin/security/login-access', 301);

Route::get('security/lead-filter-history', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_7_3Controller::class, 'index'])->name('security.lead-filter-history');
Route::redirect('pages/1-7-3-lich-su-loc-data-chot-don', '/admin/security/lead-filter-history', 301);

Route::get('sales/operation-categories', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_1Controller::class, 'index'])->name('sales.operation-categories');
Route::post('sales/operation-categories/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_1Controller::class, 'store'])->name('sales.operation-categories.store');
Route::match(['put', 'patch'], 'sales/operation-categories/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_1Controller::class, 'update'])->whereNumber('record')->name('sales.operation-categories.update');
Route::delete('sales/operation-categories/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_1Controller::class, 'destroy'])->whereNumber('record')->name('sales.operation-categories.destroy');
Route::redirect('pages/1-8-1-quan-ly-danh-muc-tac-nghiep', '/admin/sales/operation-categories', 301);

Route::get('sales/operation-workflows', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_2Controller::class, 'index'])->name('sales.operation-workflows');
Route::post('sales/operation-workflows/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_2Controller::class, 'store'])->name('sales.operation-workflows.store');
Route::match(['put', 'patch'], 'sales/operation-workflows/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_2Controller::class, 'update'])->whereNumber('record')->name('sales.operation-workflows.update');
Route::delete('sales/operation-workflows/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_8_2Controller::class, 'destroy'])->whereNumber('record')->name('sales.operation-workflows.destroy');
Route::redirect('pages/1-8-2-thiet-lap-tac-nghiep', '/admin/sales/operation-workflows', 301);

Route::get('sales/discount-cod-rules', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_9Controller::class, 'index'])->name('sales.discount-cod-rules');
Route::post('sales/discount-cod-rules/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_9Controller::class, 'store'])->name('sales.discount-cod-rules.store');
Route::match(['put', 'patch'], 'sales/discount-cod-rules/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_9Controller::class, 'update'])->whereNumber('record')->name('sales.discount-cod-rules.update');
Route::delete('sales/discount-cod-rules/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_9Controller::class, 'destroy'])->whereNumber('record')->name('sales.discount-cod-rules.destroy');
Route::redirect('pages/1-9-thiet-lap-chiet-khau-cod', '/admin/sales/discount-cod-rules', 301);

Route::get('leads/import', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_10Controller::class, 'index'])->name('leads.import-page');
Route::redirect('pages/1-10-import-contact', '/admin/leads/import', 301);

Route::get('integrations/facebook-pages', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_11Controller::class, 'index'])->name('integrations.facebook-pages');
Route::post('integrations/facebook-pages/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_11Controller::class, 'store'])->name('integrations.facebook-pages.store');
Route::match(['put', 'patch'], 'integrations/facebook-pages/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_11Controller::class, 'update'])->whereNumber('record')->name('integrations.facebook-pages.update');
Route::delete('integrations/facebook-pages/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_11Controller::class, 'destroy'])->whereNumber('record')->name('integrations.facebook-pages.destroy');
Route::redirect('pages/1-11-cau-hinh-facebook-cua-don-vi', '/admin/integrations/facebook-pages', 301);

Route::get('security/phone-blacklist', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_13_1Controller::class, 'index'])->name('security.phone-blacklist');
Route::post('security/phone-blacklist/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_13_1Controller::class, 'store'])->name('security.phone-blacklist.store');
Route::match(['put', 'patch'], 'security/phone-blacklist/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_13_1Controller::class, 'update'])->whereNumber('record')->name('security.phone-blacklist.update');
Route::delete('security/phone-blacklist/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_13_1Controller::class, 'destroy'])->whereNumber('record')->name('security.phone-blacklist.destroy');
Route::redirect('pages/1-13-1-quan-ly-so-blacklist', '/admin/security/phone-blacklist', 301);


Route::get('unit/electronic-invoice-configs', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_14_1Controller::class, 'index'])->name('unit.electronic-invoice-configs');
Route::post('unit/electronic-invoice-configs/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_14_1Controller::class, 'store'])->name('unit.electronic-invoice-configs.store');
Route::match(['put', 'patch'], 'unit/electronic-invoice-configs/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_14_1Controller::class, 'update'])->whereNumber('record')->name('unit.electronic-invoice-configs.update');
Route::delete('unit/electronic-invoice-configs/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page1_14_1Controller::class, 'destroy'])->whereNumber('record')->name('unit.electronic-invoice-configs.destroy');
Route::redirect('pages/1-14-1-danh-sach-cau-hinh-hoa-don-dien-tu', '/admin/unit/electronic-invoice-configs', 301);

Route::get('marketing/customers', \App\Http\Controllers\Sales\CustomerProfileController::class)->name('marketing.customers');
Route::redirect('pages/2-3-ho-so-khach-hang', '/admin/marketing/customers', 301);

Route::get('marketing/landing-connections', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'index'])->name('marketing.landing-connections');
Route::get('marketing/landing-connections/records', fn () => redirect('/admin/marketing/landing-connections'));
Route::post('marketing/landing-connections/records', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'store'])->name('marketing.landing-connections.store');
Route::delete('marketing/landing-connections/records', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'destroyMany'])->name('marketing.landing-connections.destroy-many');
Route::match(['put', 'patch'], 'marketing/landing-connections/records/{record}', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'update'])->whereNumber('record')->name('marketing.landing-connections.update');
Route::delete('marketing/landing-connections/records/{record}', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'destroy'])->whereNumber('record')->name('marketing.landing-connections.destroy');
Route::redirect('pages/2-4-1-ket-noi-du-lieu', '/admin/marketing/landing-connections', 301);
Route::redirect('pages/2-4-1-ket-noi-landing', '/admin/marketing/landing-connections', 301);

Route::get('marketing/website-connections', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'index'])->name('marketing.website-connections');
Route::get('marketing/website-connections/records', fn () => redirect('/admin/marketing/website-connections'));
Route::post('marketing/website-connections/records', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'store'])->name('marketing.website-connections.store');
Route::delete('marketing/website-connections/records', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'destroyMany'])->name('marketing.website-connections.destroy-many');
Route::match(['put', 'patch'], 'marketing/website-connections/records/{record}', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'update'])->whereNumber('record')->name('marketing.website-connections.update');
Route::delete('marketing/website-connections/records/{record}', [\App\Http\Controllers\Admin\Marketing\LandingConnectionsController::class, 'destroy'])->whereNumber('record')->name('marketing.website-connections.destroy');
Route::redirect('pages/2-4-2-ket-noi-du-lieu', '/admin/marketing/website-connections', 301);
Route::get('marketing/landing-approvals', [\App\Http\Controllers\Admin\LandingApprovalController::class, 'index'])->name('marketing.landing-approvals');
Route::post('marketing/landing-approvals/{connection}/approve', [\App\Http\Controllers\Admin\LandingApprovalController::class, 'approve'])->whereNumber('connection')->name('marketing.landing-approvals.approve');
Route::post('marketing/landing-approvals/{connection}/reject', [\App\Http\Controllers\Admin\LandingApprovalController::class, 'reject'])->whereNumber('connection')->name('marketing.landing-approvals.reject');

Route::get('marketing/leads/import', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_1Controller::class, 'index'])->name('marketing.leads.import-page');
Route::redirect('pages/2-6-1-import-contact', '/admin/marketing/leads/import', 301);

Route::get('marketing/leads/manual', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_2Controller::class, 'index'])->name('marketing.leads.manual-page');
Route::redirect('ld/marketing/nhap-contact', '/admin/marketing/leads/manual', 301);
Route::redirect('ld/marketing/import-excel', '/admin/marketing/leads/import', 301);
Route::redirect('pages/2-6-2-nhap-data-thu-cong', '/admin/marketing/leads/manual', 301);

Route::get('marketing/partner-connections', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_3Controller::class, 'index'])->name('marketing.partner-connections');
Route::post('marketing/partner-connections/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_3Controller::class, 'store'])->name('marketing.partner-connections.store');
Route::match(['put', 'patch'], 'marketing/partner-connections/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_3Controller::class, 'update'])->whereNumber('record')->name('marketing.partner-connections.update');
Route::delete('marketing/partner-connections/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_3Controller::class, 'destroy'])->whereNumber('record')->name('marketing.partner-connections.destroy');
Route::redirect('pages/2-6-3-ket-noi-cac-don-vi-doi-tac', '/admin/marketing/partner-connections', 301);

Route::get('marketing/seeding-numbers', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_4Controller::class, 'index'])->name('marketing.seeding-numbers');
Route::post('marketing/seeding-numbers/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_4Controller::class, 'store'])->name('marketing.seeding-numbers.store');
Route::match(['put', 'patch'], 'marketing/seeding-numbers/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_4Controller::class, 'update'])->whereNumber('record')->name('marketing.seeding-numbers.update');
Route::delete('marketing/seeding-numbers/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page2_6_4Controller::class, 'destroy'])->whereNumber('record')->name('marketing.seeding-numbers.destroy');
Route::redirect('pages/2-6-4-kho-so-seeding-toi-da-1000', '/admin/marketing/seeding-numbers', 301);

Route::get('customers/export', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'export'])->name('customers.export.admin-alias');
Route::post('customers/bulk/reallocate-now', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'reallocateNow'])->name('customers.bulk.reallocate-now.admin-alias');
Route::post('customers/bulk/queue-reallocation', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'queueReallocation'])->name('customers.bulk.queue-reallocation.admin-alias');
Route::post('customers/bulk/recall', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'recall'])->name('customers.bulk.recall.admin-alias');
Route::delete('customers/bulk/operation-history', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'deleteOperationHistory'])->name('customers.bulk.operation-history.destroy.admin-alias');

// Role-scoped customer profile action aliases. The visible page URL decides the action URL,
// so /admin/marketing/customers never has to call /admin/customers behind the scenes.
foreach (['marketing/customers' => 'marketing.customers', 'sales/customers' => 'sales.customers'] as $customerProfilePath => $customerProfileName) {
    Route::get($customerProfilePath.'/export', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'export'])->name($customerProfileName.'.export');
    Route::post($customerProfilePath.'/bulk/reallocate-now', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'reallocateNow'])->name($customerProfileName.'.bulk.reallocate-now');
    Route::post($customerProfilePath.'/bulk/queue-reallocation', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'queueReallocation'])->name($customerProfileName.'.bulk.queue-reallocation');
    Route::post($customerProfilePath.'/bulk/recall', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'recall'])->name($customerProfileName.'.bulk.recall');
    Route::delete($customerProfilePath.'/bulk/operation-history', [\App\Http\Controllers\CustomerInteractions\CustomerProfileBulkActionController::class, 'deleteOperationHistory'])->name($customerProfileName.'.bulk.operation-history.destroy');
}

Route::get('customer-management', [\App\Http\Controllers\CustomerInteractions\Customer360ManagementController::class, 'index'])->name('customers.page');
Route::get('ld/customers/list-customers', [\App\Http\Controllers\CustomerInteractions\Customer360ManagementController::class, 'index'])->name('legacy.customers.management');
Route::get('customer-management/export', [\App\Http\Controllers\CustomerInteractions\Customer360ManagementController::class, 'export'])->name('customer-management.export');
Route::post('customer-management/campaigns', [\App\Http\Controllers\CustomerInteractions\Customer360ManagementController::class, 'createCampaign'])->name('customer-management.campaigns.store');
Route::post('customer-management/campaigns/attach', [\App\Http\Controllers\CustomerInteractions\Customer360ManagementController::class, 'attachCampaign'])->name('customer-management.campaigns.attach');
Route::put('customer-management/segments', [\App\Http\Controllers\CustomerInteractions\Customer360ManagementController::class, 'saveSegments'])->name('customer-management.segments.update');
Route::redirect('pages/3-1-quan-ly-khach-hang', '/admin/customer-management', 301);

Route::get('customers/care-campaigns', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_2Controller::class, 'index'])->name('customers.care-campaigns');
Route::post('customers/care-campaigns/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_2Controller::class, 'store'])->name('customers.care-campaigns.store');
Route::match(['put', 'patch'], 'customers/care-campaigns/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_2Controller::class, 'update'])->whereNumber('record')->name('customers.care-campaigns.update');
Route::delete('customers/care-campaigns/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_2Controller::class, 'destroy'])->whereNumber('record')->name('customers.care-campaigns.destroy');
Route::redirect('pages/3-2-quan-ly-chien-dich-cham-soc', '/admin/customers/care-campaigns', 301);

Route::get('customers/reports/multidimensional', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_3_1Controller::class, 'index'])->name('customers.reports.multidimensional');
Route::redirect('pages/3-3-1-thong-ke-khach-hang-da-chieu', '/admin/customers/reports/multidimensional', 301);

Route::get('customers/reports/spending', [\App\Http\Controllers\Admin\Pushsale\Pages\Page3_3_2Controller::class, 'index'])->name('customers.reports.spending');
Route::redirect('pages/3-3-2-thong-ke-khach-hang-chi-tra', '/admin/customers/reports/spending', 301);

Route::get('sales/customers', \App\Http\Controllers\Sales\CustomerProfileController::class)->name('sales.customers');
Route::redirect('pages/4-2-ho-so-khach-hang', '/admin/sales/customers', 301);

Route::get('sales/rankings', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_3Controller::class, 'index'])->name('sales.rankings-page');
Route::redirect('pages/4-3-bang-xep-hang-sales', '/admin/sales/rankings', 301);

Route::get('sales/reports/operation-conversion', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_1Controller::class, 'index'])->name('sales.reports.operation-conversion');
Route::redirect('pages/4-6-1-bao-cao-ti-le-chot-don-theo-tac-nghiep', '/admin/sales/reports/operation-conversion', 301);

Route::get('sales/reports/work', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_2Controller::class, 'index'])->name('sales.reports.work');
Route::redirect('pages/4-6-2-bao-cao-cong-viec-sale', '/admin/sales/reports/work', 301);

Route::get('sales/reports/teams', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_3Controller::class, 'index'])->name('sales.reports.teams');
Route::redirect('pages/4-6-3-bao-cao-nhom-sale', '/admin/sales/reports/teams', 301);

Route::get('sales/reports/data', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_4Controller::class, 'index'])->name('sales.reports.data');
Route::redirect('pages/4-6-4-bao-cao-data-sale', '/admin/sales/reports/data', 301);

Route::get('sales/reports/optimization', [\App\Http\Controllers\Admin\Pushsale\Pages\Page4_6_5Controller::class, 'index'])->name('sales.reports.optimization');
Route::redirect('pages/4-6-5-bao-cao-toi-uu-sale', '/admin/sales/reports/optimization', 301);

Route::redirect('warehouse/shipping-operations', '/admin/warehouse/operations', 301);
Route::redirect('pages/5-1-tac-nghiep-van-don', '/admin/warehouse/operations', 301);

Route::redirect('warehouse/list', '/admin/warehouses', 301);
Route::redirect('pages/5-2-1-danh-sach-kho', '/admin/warehouses', 301);

Route::redirect('warehouse/products', '/admin/warehouse/inventory', 301);
Route::redirect('pages/5-2-2-danh-sach-san-pham-kho', '/admin/warehouse/inventory', 301);

Route::get('warehouse/vouchers/entry', [\App\Http\Controllers\Admin\Pushsale\Warehouse\WarehouseVoucherEntryController::class, 'index'])->name('warehouse.vouchers.entry');
Route::post('warehouse/vouchers/entry/records', [\App\Http\Controllers\Admin\Pushsale\Warehouse\WarehouseVoucherEntryController::class, 'store'])->name('warehouse.vouchers.entry.store');
Route::match(['put', 'patch'], 'warehouse/vouchers/entry/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Warehouse\WarehouseVoucherEntryController::class, 'update'])->whereNumber('record')->name('warehouse.vouchers.entry.update');
Route::delete('warehouse/vouchers/entry/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Warehouse\WarehouseVoucherEntryController::class, 'destroy'])->whereNumber('record')->name('warehouse.vouchers.entry.destroy');
Route::redirect('pages/5-3-1-phieu-nhap-xuat-kho', '/admin/warehouse/vouchers/entry', 301);

Route::get('warehouse/vouchers', [\App\Http\Controllers\Admin\Pushsale\Warehouse\WarehouseVoucherListController::class, 'index'])->name('warehouse.vouchers.index-page');
Route::redirect('pages/5-3-2-danh-sach-phieu-xuat-nhap-kho', '/admin/warehouse/vouchers', 301);

Route::get('warehouse/movement-history', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_3_3Controller::class, 'index'])->name('warehouse.movement-history');
Route::redirect('pages/5-3-3-lich-su-nhap-xuat-kho-the-kho', '/admin/warehouse/movement-history', 301);

Route::get('warehouse/incidents', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_4Controller::class, 'index'])->name('warehouse.incidents');
Route::post('warehouse/incidents/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_4Controller::class, 'store'])->name('warehouse.incidents.store');
Route::match(['put', 'patch'], 'warehouse/incidents/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_4Controller::class, 'update'])->whereNumber('record')->name('warehouse.incidents.update');
Route::delete('warehouse/incidents/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_4Controller::class, 'destroy'])->whereNumber('record')->name('warehouse.incidents.destroy');
Route::redirect('pages/5-4-danh-sach-bien-ban', '/admin/warehouse/incidents', 301);

Route::get('warehouse/reports/daily-stock', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_1Controller::class, 'index'])->name('warehouse.reports.daily-stock');
Route::redirect('pages/5-5-1-bang-tong-hop-san-pham-nhap-xuat-theo-ngay', '/admin/warehouse/reports/daily-stock', 301);

Route::get('warehouse/reports/pending-export', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_2Controller::class, 'index'])->name('warehouse.reports.pending-export');
Route::redirect('pages/5-5-2-bang-tong-hop-cho-xuat-theo-ngay', '/admin/warehouse/reports/pending-export', 301);

Route::get('warehouse/reports/movement-summary', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_4Controller::class, 'index'])->name('warehouse.reports.movement-summary');
Route::redirect('pages/5-5-4-bao-cao-tong-hop-phat-sinh-kho', '/admin/warehouse/reports/movement-summary', 301);

Route::get('warehouse/reports/care-orders', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_5Controller::class, 'index'])->name('warehouse.reports.care-orders');
Route::redirect('pages/5-5-5-bao-cao-care-don', '/admin/warehouse/reports/care-orders', 301);

Route::get('warehouse/reports/phone-corrections', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_6Controller::class, 'index'])->name('warehouse.reports.phone-corrections');
Route::redirect('pages/5-5-6-bao-cao-sua-so-dien-thoai-giao-hang', '/admin/warehouse/reports/phone-corrections', 301);

Route::get('warehouse/reports/delivery-status', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_7Controller::class, 'index'])->name('warehouse.reports.delivery-status');
Route::redirect('pages/5-5-7-tong-hop-trang-thai-giao-hang-theo-van-don', '/admin/warehouse/reports/delivery-status', 301);

Route::get('warehouse/reports/care-operations', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_5_8Controller::class, 'index'])->name('warehouse.reports.care-operations');
Route::redirect('pages/5-5-8-bao-cao-tac-nghiep-care-don', '/admin/warehouse/reports/care-operations', 301);

Route::get('warehouse/care-distribution', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_8_2Controller::class, 'index'])->name('warehouse.care-distribution');
Route::post('warehouse/care-distribution/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_8_2Controller::class, 'store'])->name('warehouse.care-distribution.store');
Route::match(['put', 'patch'], 'warehouse/care-distribution/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_8_2Controller::class, 'update'])->whereNumber('record')->name('warehouse.care-distribution.update');
Route::delete('warehouse/care-distribution/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page5_8_2Controller::class, 'destroy'])->whereNumber('record')->name('warehouse.care-distribution.destroy');
Route::redirect('pages/5-8-2-phan-bo-data-care-don', '/admin/warehouse/care-distribution', 301);

Route::get('accounting/expenses', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_1Controller::class, 'index'])->name('accounting.expenses');
Route::post('accounting/expenses/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_1Controller::class, 'store'])->name('accounting.expenses.store');
Route::match(['put', 'patch'], 'accounting/expenses/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_1Controller::class, 'update'])->whereNumber('record')->name('accounting.expenses.update');
Route::delete('accounting/expenses/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_1Controller::class, 'destroy'])->whereNumber('record')->name('accounting.expenses.destroy');
Route::redirect('pages/6-2-1-quan-ly-chi-phi-don-vi', '/admin/accounting/expenses', 301);

Route::get('accounting/expense-categories', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_2Controller::class, 'index'])->name('accounting.expense-categories');
Route::post('accounting/expense-categories/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_2Controller::class, 'store'])->name('accounting.expense-categories.store');
Route::match(['put', 'patch'], 'accounting/expense-categories/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_2Controller::class, 'update'])->whereNumber('record')->name('accounting.expense-categories.update');
Route::delete('accounting/expense-categories/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_2Controller::class, 'destroy'])->whereNumber('record')->name('accounting.expense-categories.destroy');
Route::redirect('pages/6-2-2-danh-muc-chi-phi', '/admin/accounting/expense-categories', 301);

Route::get('accounting/expense-groups', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_3Controller::class, 'index'])->name('accounting.expense-groups');
Route::post('accounting/expense-groups/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_3Controller::class, 'store'])->name('accounting.expense-groups.store');
Route::match(['put', 'patch'], 'accounting/expense-groups/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_3Controller::class, 'update'])->whereNumber('record')->name('accounting.expense-groups.update');
Route::delete('accounting/expense-groups/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_3Controller::class, 'destroy'])->whereNumber('record')->name('accounting.expense-groups.destroy');
Route::redirect('pages/6-2-3-danh-muc-nhom-chi-phi', '/admin/accounting/expense-groups', 301);

Route::get('accounting/expense-units', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_4Controller::class, 'index'])->name('accounting.expense-units');
Route::post('accounting/expense-units/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_4Controller::class, 'store'])->name('accounting.expense-units.store');
Route::match(['put', 'patch'], 'accounting/expense-units/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_4Controller::class, 'update'])->whereNumber('record')->name('accounting.expense-units.update');
Route::delete('accounting/expense-units/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_2_4Controller::class, 'destroy'])->whereNumber('record')->name('accounting.expense-units.destroy');
Route::redirect('pages/6-2-4-danh-muc-don-vi-tinh', '/admin/accounting/expense-units', 301);

Route::get('accounting/reports/monthly-plan', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_3_5Controller::class, 'index'])->name('accounting.reports.monthly-plan');
Route::post('accounting/reports/monthly-plan/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_3_5Controller::class, 'store'])->name('accounting.reports.monthly-plan.store');
Route::match(['put', 'patch'], 'accounting/reports/monthly-plan/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_3_5Controller::class, 'update'])->whereNumber('record')->name('accounting.reports.monthly-plan.update');
Route::delete('accounting/reports/monthly-plan/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_3_5Controller::class, 'destroy'])->whereNumber('record')->name('accounting.reports.monthly-plan.destroy');
Route::redirect('pages/6-3-5-tong-ket-ke-hoach-thang', '/admin/accounting/reports/monthly-plan', 301);

Route::get('accounting/electronic-invoices', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_4Controller::class, 'index'])->name('accounting.electronic-invoices');
Route::post('accounting/electronic-invoices/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_4Controller::class, 'store'])->name('accounting.electronic-invoices.store');
Route::match(['put', 'patch'], 'accounting/electronic-invoices/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_4Controller::class, 'update'])->whereNumber('record')->name('accounting.electronic-invoices.update');
Route::delete('accounting/electronic-invoices/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page6_4Controller::class, 'destroy'])->whereNumber('record')->name('accounting.electronic-invoices.destroy');
Route::redirect('pages/6-4-danh-sach-xu-ly-xuat-hoa-don-dien-tu', '/admin/accounting/electronic-invoices', 301);

Route::get('ceo/business-plan/monthly', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_1Controller::class, 'index'])->name('ceo.business-plan.monthly');
Route::post('ceo/business-plan/monthly/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_1Controller::class, 'store'])->name('ceo.business-plan.monthly.store');
Route::match(['put', 'patch'], 'ceo/business-plan/monthly/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_1Controller::class, 'update'])->whereNumber('record')->name('ceo.business-plan.monthly.update');
Route::delete('ceo/business-plan/monthly/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_1Controller::class, 'destroy'])->whereNumber('record')->name('ceo.business-plan.monthly.destroy');
Route::post('ceo/business-plan/monthly/add-missing', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_1Controller::class, 'addMissing'])->name('ceo.business-plan.monthly.add-missing');
Route::post('ceo/business-plan/monthly/copy-previous', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_1Controller::class, 'copyPrevious'])->name('ceo.business-plan.monthly.copy-previous');
Route::post('ceo/business-plan/monthly/lock-period', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_1Controller::class, 'lockPeriod'])->name('ceo.business-plan.monthly.lock-period');
Route::post('ceo/business-plan/monthly/bulk-save', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_1Controller::class, 'bulkSave'])->name('ceo.business-plan.monthly.bulk-save');
Route::redirect('pages/7-1-1-ke-hoach-kinh-doanh-thang', '/admin/ceo/business-plan/monthly', 301);
Route::redirect('ld/unit-admin/thiet-lap-kpi', '/admin/ceo/business-plan/monthly', 301);
Route::get('ceo/business-plan/yearly', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_2Controller::class, 'index'])->name('ceo.business-plan.yearly');
Route::post('ceo/business-plan/yearly/planned-data', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_2Controller::class, 'storePlannedData'])->name('ceo.business-plan.yearly.planned-data');
Route::redirect('pages/7-1-2-lap-ke-hoach-kinh-doanh-nam', '/admin/ceo/business-plan/yearly', 301);

Route::get('ceo/business-plan/kpi-catalog', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_3Controller::class, 'index'])->name('ceo.business-plan.kpi-catalog');
Route::post('ceo/business-plan/kpi-catalog/records', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_3Controller::class, 'store'])->name('ceo.business-plan.kpi-catalog.store');
Route::match(['put', 'patch'], 'ceo/business-plan/kpi-catalog/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_3Controller::class, 'update'])->whereNumber('record')->name('ceo.business-plan.kpi-catalog.update');
Route::delete('ceo/business-plan/kpi-catalog/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_3Controller::class, 'destroy'])->whereNumber('record')->name('ceo.business-plan.kpi-catalog.destroy');
Route::post('ceo/business-plan/kpi-catalog/initialize-defaults', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_3Controller::class, 'initializeDefaults'])->name('ceo.business-plan.kpi-catalog.initialize-defaults');
Route::post('ceo/business-plan/kpi-catalog/bulk-save', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_3Controller::class, 'bulkSave'])->name('ceo.business-plan.kpi-catalog.bulk-save');
Route::redirect('pages/7-1-3-danh-muc-kpi', '/admin/ceo/business-plan/kpi-catalog', 301);
Route::get('ceo/business-plan/revenue-bonus', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_4Controller::class, 'index'])->name('ceo.business-plan.revenue-bonus');
Route::post('ceo/business-plan/revenue-bonus/bulk-save', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_4Controller::class, 'bulkSave'])->name('ceo.business-plan.revenue-bonus.bulk-save');
Route::delete('ceo/business-plan/revenue-bonus/records/{record}', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_4Controller::class, 'destroy'])->whereNumber('record')->name('ceo.business-plan.revenue-bonus.destroy');
Route::post('ceo/business-plan/revenue-bonus/copy-previous', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_4Controller::class, 'copyPrevious'])->name('ceo.business-plan.revenue-bonus.copy-previous');
Route::post('ceo/business-plan/revenue-bonus/lock-period', [\App\Http\Controllers\Admin\Pushsale\Pages\Page7_1_4Controller::class, 'setLocked'])->name('ceo.business-plan.revenue-bonus.lock-period');
Route::redirect('pages/7-1-4-khai-bao-thuong', '/admin/ceo/business-plan/revenue-bonus', 301);
Route::redirect('ld/unit-admin/thiet-lap-thuong-theo-doanh-so', '/admin/ceo/business-plan/revenue-bonus', 301);
Route::redirect('ld/thong-ke/lap-ke-hoach-kinh-doanh', '/admin/ceo/business-plan/yearly', 301);

Route::get('reports/trends', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_4Controller::class, 'index'])->name('reports.trends');
Route::redirect('pages/8-5-4-bieu-do-xu-huong', '/admin/reports/trends', 301);

Route::get('reports/data-allocation', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_5Controller::class, 'index'])->name('reports.data-allocation');
Route::redirect('pages/8-5-5-bang-tong-hop-ket-qua-chia-data-trong-ngay', '/admin/reports/data-allocation', 301);

Route::get('reports/power-dashboard', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_9Controller::class, 'index'])->name('reports.power-dashboard');
Route::redirect('pages/8-5-9-power-dashboard', '/admin/reports/power-dashboard', 301);
Route::redirect('ld/ceo/power-dashboard', '/admin/reports/power-dashboard', 301);

Route::get('reports/repurchase', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_10Controller::class, 'index'])->name('reports.repurchase');
Route::redirect('pages/8-5-10-thong-ke-mua-lai', '/admin/reports/repurchase', 301);

Route::get('reports/repurchase-products', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_11Controller::class, 'index'])->name('reports.repurchase-products');
Route::redirect('pages/8-5-11-thong-ke-mua-lai-theo-so-san-pham', '/admin/reports/repurchase-products', 301);

Route::get('reports/data-allocation-v2', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_15Controller::class, 'index'])->name('reports.data-allocation-v2');
Route::redirect('pages/8-5-15-bang-tong-hop-chia-data-trong-ngay-v2', '/admin/reports/data-allocation-v2', 301);

Route::get('reports/care-orders', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_16Controller::class, 'index'])->name('reports.care-orders');
Route::redirect('pages/8-5-16-bao-cao-care-don', '/admin/reports/care-orders', 301);

Route::get('reports/care-allocation', [\App\Http\Controllers\Admin\Pushsale\Pages\Page8_5_17Controller::class, 'index'])->name('reports.care-allocation');
Route::redirect('pages/8-5-17-bang-tong-hop-chia-so-care-don-trong-ngay', '/admin/reports/care-allocation', 301);

