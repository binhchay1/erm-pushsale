/**
 * Single source of truth for the Pushsale/AdminLTE shell CSS cascade.
 *
 * Rules:
 * 1) Vendor CSS must load before app/page CSS.
 * 2) Runtime CSS modules are explicit and ordered by layer.
 * 3) Page-specific CSS must be scoped by a page/root class; never use broad
 *    selectors that can override sidebar/header globally.
 * 4) Only this registry should be updated when adding/removing a Pushsale CSS file.
 */
export const PUSHSALE_VENDOR_STYLES = [
    { href: '/vendor/adminlte2/bootstrap/css/bootstrap.min.css', id: 'pushsale-bootstrap', layer: 'vendor' },
    { href: '/vendor/font-awesome/css/font-awesome.min.css', id: 'pushsale-font-awesome', layer: 'vendor' },
    { href: '/vendor/adminlte2/dist/css/AdminLTE.min.css', id: 'pushsale-adminlte', layer: 'vendor' },
    { href: '/vendor/adminlte2/dist/css/skins/skin-blue-light.min.css', id: 'pushsale-adminlte-skin', layer: 'vendor' },
    { href: '/vendor/adminlte2/plugins/select2/select2.min.css', id: 'pushsale-select2', layer: 'vendor' },
    { href: '/vendor/adminlte2/plugins/datepicker/datepicker3.css', id: 'pushsale-datepicker', layer: 'vendor' },
];

export const PUSHSALE_CSS_MODULES = [
    { file: 'pushsale.css', layer: 'legacy-base', load: () => import('../../css/pushsale.css') },
    { file: 'pushsale-page-polish.css', layer: 'page-fix', load: () => import('../../css/pushsale-page-polish.css') },
    { file: 'pushsale-combo-page.css', layer: 'page-fix', load: () => import('../../css/pushsale-combo-page.css') },
    { file: 'pushsale-discount-cod-page.css', layer: 'page-fix', load: () => import('../../css/pushsale-discount-cod-page.css') },
    { file: 'pushsale-login-history.css', layer: 'page-fix', load: () => import('../../css/pushsale-login-history.css') },
    { file: 'pushsale-operation-categories.css', layer: 'page-fix', load: () => import('../../css/pushsale-operation-categories.css') },
    { file: 'pushsale-users-frame-toast.css', layer: 'page-fix', load: () => import('../../css/pushsale-users-frame-toast.css') },
    { file: 'pushsale-teams-page.css', layer: 'page-fix', load: () => import('../../css/pushsale-teams-page.css') },
    { file: 'pushsale-operations-polish.css', layer: 'operations', load: () => import('../../css/pushsale-operations-polish.css') },
    { file: 'pushsale-accounting-operations.css', layer: 'operations', load: () => import('../../css/pushsale-accounting-operations.css') },
    { file: 'pushsale-shared-filters-actions.css', layer: 'shared-controls', load: () => import('../../css/pushsale-shared-filters-actions.css') },
    { file: 'pushsale-content-shell-actions.css', layer: 'content-shell', load: () => import('../../css/pushsale-content-shell-actions.css') },
    { file: 'pushsale-dialog-pagination-products.css', layer: 'shared-controls', load: () => import('../../css/pushsale-dialog-pagination-products.css') },
    { file: 'pushsale-product-page-contract.css', layer: 'page-contract', load: () => import('../../css/pushsale-product-page-contract.css') },
    { file: 'pushsale-universal-page-contract.css', layer: 'page-contract', load: () => import('../../css/pushsale-universal-page-contract.css') },
    { file: 'pushsale-stability-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-stability-contract.css') },
    { file: 'pushsale-page-shell-menu-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-page-shell-menu-contract.css') },
    { file: 'pushsale-header-menu-dialog-polish.css', layer: 'final-contract', load: () => import('../../css/pushsale-header-menu-dialog-polish.css') },
    { file: 'pushsale-pagination-actions-upsale.css', layer: 'final-contract', load: () => import('../../css/pushsale-pagination-actions-upsale.css') },
    { file: 'pushsale-filter-info-upsale-actions.css', layer: 'final-contract', load: () => import('../../css/pushsale-filter-info-upsale-actions.css') },
    { file: 'pushsale-page-pagination-menu-fixes.css', layer: 'final-contract', load: () => import('../../css/pushsale-page-pagination-menu-fixes.css') },
    { file: 'pushsale-report-feature-ecommerce-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-report-feature-ecommerce-contract.css') },
    { file: 'pushsale-customer-operation-money-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-customer-operation-money-contract.css') },
    { file: 'pushsale-dashboard-menu-sales-report.css', layer: 'final-contract', load: () => import('../../css/pushsale-dashboard-menu-sales-report.css') },
    { file: 'pushsale-revenue-detail-report-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-revenue-detail-report-contract.css') },
    { file: 'pushsale-marketing-sales-report-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-marketing-sales-report-contract.css') },
    { file: 'pushsale-marketing-work-leader-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-marketing-work-leader-contract.css') },
    { file: 'pushsale-marketing-upsale-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-marketing-upsale-contract.css') },
    { file: 'pushsale-warehouse-flow-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-warehouse-flow-contract.css') },
    { file: 'pushsale-security-report-header-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-security-report-header-contract.css') },
    { file: 'pushsale-sale-kpi-closing-summary-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-sale-kpi-closing-summary-contract.css') },
    { file: 'pushsale-page-spacing-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-page-spacing-contract.css') },
    { file: 'pushsale-operation-conversion-report.css', layer: 'final-contract', load: () => import('../../css/pushsale-operation-conversion-report.css') },
    { file: 'pushsale-sale-work-report-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-sale-work-report-contract.css') },
    { file: 'pushsale-sales-leader-reports-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-sales-leader-reports-contract.css') },
    { file: 'pushsale-report-toolbar-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-report-toolbar-contract.css') },
    { file: 'pushsale-filter-toolbar-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-filter-toolbar-contract.css') },
    { file: 'pushsale-dashboard-toolbar-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-dashboard-toolbar-contract.css') },
    { file: 'pushsale-product-source-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-product-source-contract.css') },
    { file: 'pushsale-import-distribution-feature-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-import-distribution-feature-contract.css') },
    { file: 'pushsale-form-controls-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-form-controls-contract.css') },
    { file: 'pushsale-product-taxonomy-dialog-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-product-taxonomy-dialog-contract.css') },
    { file: 'pushsale-page-scroll-filter-width-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-page-scroll-filter-width-contract.css') },
    { file: 'pushsale-ceo-report-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-ceo-report-contract.css') },
    { file: 'pushsale-report-spacing-table-ranking-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-report-spacing-table-ranking-contract.css') },
    { file: 'pushsale-page-header-spacing-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-page-header-spacing-contract.css') },
    { file: 'pushsale-warehouse-delivery-handover-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-warehouse-delivery-handover-contract.css') },
    { file: 'pushsale-warehouse-report-chrome-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-warehouse-report-chrome-contract.css') },
    { file: 'pushsale-warehouse-menu-report-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-warehouse-menu-report-contract.css') },
    { file: 'pushsale-warehouse-operations-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-warehouse-operations-contract.css') },
    { file: 'pushsale-warehouse-bulk-update-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-warehouse-bulk-update-contract.css') },
    { file: 'pushsale-customer-profile-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-customer-profile-contract.css') },
    { file: 'pushsale-customer360-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-customer360-contract.css') },
    { file: 'pushsale-ops-shared-controls-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-ops-shared-controls-contract.css') },
    { file: 'pushsale-facebook-unit-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-facebook-unit-contract.css') },
    { file: 'pushsale-manual-lead-page.css', layer: 'final-contract', load: () => import('../../css/pushsale-manual-lead-page.css') },
    { file: 'pushsale-monthly-kpi-plan.css', layer: 'final-contract', load: () => import('../../css/pushsale-monthly-kpi-plan.css') },
    { file: 'pushsale-yearly-business-plan.css', layer: 'final-contract', load: () => import('../../css/pushsale-yearly-business-plan.css') },
    { file: 'pushsale-kpi-catalog.css', layer: 'final-contract', load: () => import('../../css/pushsale-kpi-catalog.css') },
    { file: 'pushsale-revenue-bonus-714.css', layer: 'final-contract', load: () => import('../../css/pushsale-revenue-bonus-714.css') },
    { file: 'pushsale-landing-connections.css', layer: 'final-contract', load: () => import('../../css/pushsale-landing-connections.css') },
    { file: 'pushsale-partner-connections-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-partner-connections-contract.css') },
    { file: 'pushsale-marketing-dashboard-filter-grid-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-marketing-dashboard-filter-grid-contract.css') },
    { file: 'pushsale-blacklist-invoice-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-blacklist-invoice-contract.css') },
    { file: 'pushsale-accounting-expenses-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-accounting-expenses-contract.css') },
    { file: 'pushsale-system-monitor-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-system-monitor-contract.css') },
    { file: 'pushsale-operation-columns-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-operation-columns-contract.css') },
    { file: 'pushsale-sale-operations-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-sale-operations-contract.css') },
    { file: 'pushsale-ecommerce-new-flow-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-ecommerce-new-flow-contract.css') },
    { file: 'pushsale-shipping-partners-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-shipping-partners-contract.css') },
    { file: 'pushsale-shipping-label-print-page.css', layer: 'final-contract', load: () => import('../../css/pushsale-shipping-label-print-page.css') },
    { file: 'pushsale-work-shifts-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-work-shifts-contract.css') },
    { file: 'pushsale-hr-distribution-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-hr-distribution-contract.css') },
    { file: 'pushsale-activity-product-report-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-activity-product-report-contract.css') },
    { file: 'pushsale-power-dashboard-859.css', layer: 'final-contract', load: () => import('../../css/pushsale-power-dashboard-859.css') },
    { file: 'pushsale-hourly-statistics-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-hourly-statistics-contract.css') },
    { file: 'pushsale-admin-85-reports-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-admin-85-reports-contract.css') },
    { file: 'pushsale-system-settings-admin.css', layer: 'final-contract', load: () => import('../../css/pushsale-system-settings-admin.css') },
    { file: 'pushsale-platform-companies-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-platform-companies-contract.css') },
    { file: 'pushsale-unified-page-shell-contract.css', layer: 'final-contract-last', load: () => import('../../css/pushsale-unified-page-shell-contract.css') },
    { file: 'pushsale-adminlte-canonical-contract.css', layer: 'canonical-last', load: () => import('../../css/pushsale-adminlte-canonical-contract.css') },
    // Page frame before sidebar so menu chrome still wins conflicts.
    { file: 'pushsale-page-frame-contract.css', layer: 'page-frame-last', load: () => import('../../css/pushsale-page-frame-contract.css') },
    // Single source of truth for the page title bar (.m-header-wrap > .m-header).
    { file: 'pushsale-page-header-contract.css', layer: 'page-header-last', load: () => import('../../css/pushsale-page-header-contract.css') },
    // Absolute last: single source of truth for sidebar + L3 flyout chrome.
    { file: 'pushsale-sidebar-canonical-contract.css', layer: 'menu-canonical-last', load: () => import('../../css/pushsale-sidebar-canonical-contract.css') },
];

export function assetMatchersForCssFile(file) {
    const basename = file.replace(/\.css$/i, '');

    return [
        `/resources/css/${file}`,
        `/build/assets/${basename}-`,
];
}

export function isPushsaleRuntimeStylesheet(href = '') {
    return PUSHSALE_CSS_MODULES.some((entry) => assetMatchersForCssFile(entry.file).some((pattern) => href.includes(pattern)))
        || href.includes('/build/assets/app-')
        || href.includes('/resources/css/app.css');
}
