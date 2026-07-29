<?php

/**
 * URL đời Pushsale cũ (/ld/..., /bao-cao/..., các URL phẳng) → 301 về URL canonical.
 *
 * Đây là nơi DUY NHẤT chứa redirect legacy. Không rải sang route file nghiệp vụ.
 * Khi tất cả bookmark/backlink đã chuyển hết, xóa nguyên file này và bỏ dòng
 * require trong routes/web.php.
 *
 * Required from routes/web.php inside:
 *   Route::middleware(['auth','tenant','permissions'])->group(...)
 */

use Illuminate\Support\Facades\Route;

$legacyRedirects = [
    // 1. Quản trị đơn vị
    'ld/unit-admin/cau-hinh-chuc-nang' => '/admin/settings/features',
    'ld/unit-admin/phone-blacklist' => '/admin/security/phone-blacklist',
    'ld/unit-admin/danh-sach-cau-hinh-hddt' => '/admin/unit/electronic-invoice-configs',
    'ld/unit-admin/cau-hinh-giao-hang' => '/admin/shipping-partners',

    // 2. Marketing
    'ld/unit-admin/ket-noi-landing-website' => '/admin/marketing/landing-connections',
    'ld/unit-admin/ket-noi-landing-website/c1654/ThemNguonDuLieu/mode/normal' => '/admin/marketing/landing-connections',
    'ld/facebook/tao-nguon-du-lieu' => '/admin/marketing/website-connections',
    'admin/marketing/facebook-source' => '/admin/marketing/website-connections',
    'ld/facebook/ket-noi' => '/admin/marketing/facebook/connect',
    'ld/facebook/quan-ly' => '/admin/marketing/facebook/connect',
    'ld/facebook/danh-sach-post' => '/admin/marketing/facebook/posts',
    'ld/facebook/cau-hinh-don-vi' => '/admin/integrations/facebook-pages',
    'ld/marketing/nhap-contact' => '/admin/marketing/leads/manual',
    'ld/marketing/import-excel' => '/admin/marketing/leads/import',
    'ld/marketing/thong-ke-truong-nhom' => '/admin/reports/team-leaders',
    'ld/thong-ke/bao-cao-cong-viec-mkt' => '/admin/marketing/reports/work',
    'ld/thong-ke/bao-cao-up-sale' => '/admin/marketing/reports/upsale',
    'bao-cao/bao-cao-doanh-so-chi-tiet-marketing' => '/admin/marketing/reports/revenue-detail',

    // 3. Khách hàng
    'ld/customers/list-customers' => '/admin/customer-management',

    // 4. Sale
    'ld/sale/sale-kpi' => '/admin/sales/reports/sale-kpi',
    'ld/sale/bang-tong-hop-ban-hang' => '/admin/sales/reports/closing-summary',
    'ld/thong-ke/ty-le-chot-don-theo-tac-nghiep' => '/admin/sales/reports/operation-conversion',
    'ld/sale/bao-cao/bao-cao-cong-viec-sale' => '/admin/sales/reports/work',
    'ld/sale/thong-ke-truong-nhom-sale' => '/admin/sales/reports/teams',
    'ld/sale/bao-cao-data-sale' => '/admin/sales/reports/data',
    'ld/sale/toi-uu-sale' => '/admin/sales/reports/optimization',
    'ld/sale/bao-cao-doanh-so-chi-tiet' => '/admin/sales/reports/revenue-detail',
    'ld/sale/bao-cao/bao-cao-doanh-so' => '/admin/sales/reports/revenue',
    'ld/sale/bao-cao/bao-cao-doanh-so-v2' => '/admin/sales/reports/revenue-v2',
    'ld/thong-ke/bao-cao-lich-hen-telesales' => '/admin/sales/reports/appointments',

    // 7. CEO
    'ld/unit-admin/thiet-lap-kpi' => '/admin/ceo/business-plan/monthly',
    'ld/thong-ke/lap-ke-hoach-kinh-doanh' => '/admin/ceo/business-plan/yearly',
    'ld/unit-admin/danh-muc-kpi' => '/admin/ceo/business-plan/kpi-catalog',
    'ld/unit-admin/thiet-lap-thuong-theo-doanh-so' => '/admin/ceo/business-plan/revenue-bonus',
    'ld/ceo/power-dashboard' => '/admin/reports/power-dashboard',

    // 8. Báo cáo hệ thống
    'ld/thong-ke' => '/admin/reports/hourly',
    'ld/thong-ke/bao-cao-kinh-doanh-he-thong' => '/admin/reports/system-business',

    // Sàn thương mại điện tử
    'connect-shop-list' => '/admin/ecommerce/connect-shops',
    'ld/ecommerce/e-connect-shop-list' => '/admin/ecommerce/connect-shops',
    'connect-product-list' => '/admin/ecommerce/connect-products',
    'ld/ecommerce/e-connect-product-list' => '/admin/ecommerce/connect-products',
    'error-order-list' => '/admin/ecommerce/sync-errors',
    'ld/ecommerce/e-order-sync-error-list' => '/admin/ecommerce/sync-errors',

    // Duyệt landing: gộp về một URL canonical.
    'admin/landing-approvals' => '/admin/marketing/landing-approvals',
];

foreach ($legacyRedirects as $legacyPath => $canonicalPath) {
    Route::redirect($legacyPath, $canonicalPath, 301);
}
