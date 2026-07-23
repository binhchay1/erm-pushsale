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
    { file: 'pushsale-v70-page-polish.css', layer: 'page-fix', load: () => import('../../css/pushsale-v70-page-polish.css') },
    { file: 'pushsale-v71-combo-page.css', layer: 'page-fix', load: () => import('../../css/pushsale-v71-combo-page.css') },
    { file: 'pushsale-v72-login-history.css', layer: 'page-fix', load: () => import('../../css/pushsale-v72-login-history.css') },
    { file: 'pushsale-v73-operation-categories.css', layer: 'page-fix', load: () => import('../../css/pushsale-v73-operation-categories.css') },
    { file: 'pushsale-v74-users-frame-toast.css', layer: 'page-fix', load: () => import('../../css/pushsale-v74-users-frame-toast.css') },
    { file: 'pushsale-v75-teams-page.css', layer: 'page-fix', load: () => import('../../css/pushsale-v75-teams-page.css') },
    { file: 'pushsale-v76-operations-polish.css', layer: 'operations', load: () => import('../../css/pushsale-v76-operations-polish.css') },
    { file: 'pushsale-v77-accounting-operations.css', layer: 'operations', load: () => import('../../css/pushsale-v77-accounting-operations.css') },
    { file: 'pushsale-v78-shared-filters-actions.css', layer: 'shared-controls', load: () => import('../../css/pushsale-v78-shared-filters-actions.css') },
    { file: 'pushsale-v79-content-shell-actions.css', layer: 'content-shell', load: () => import('../../css/pushsale-v79-content-shell-actions.css') },
    { file: 'pushsale-v80-menu-isolation.css', layer: 'sidebar-isolation', load: () => import('../../css/pushsale-v80-menu-isolation.css') },
    { file: 'pushsale-v81-dialog-pagination-products.css', layer: 'shared-controls', load: () => import('../../css/pushsale-v81-dialog-pagination-products.css') },
    { file: 'pushsale-v82-page-contract-products.css', layer: 'page-contract', load: () => import('../../css/pushsale-v82-page-contract-products.css') },
    { file: 'pushsale-v83-universal-page-contract.css', layer: 'page-contract', load: () => import('../../css/pushsale-v83-universal-page-contract.css') },
    { file: 'pushsale-v84-stability-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-v84-stability-contract.css') },
    { file: 'pushsale-v85-page-shell-menu-contract.css', layer: 'final-contract', load: () => import('../../css/pushsale-v85-page-shell-menu-contract.css') },
    { file: 'pushsale-v86-header-menu-dialog-polish.css', layer: 'final-contract', load: () => import('../../css/pushsale-v86-header-menu-dialog-polish.css') },
    { file: 'pushsale-v87-pagination-actions-upsale.css', layer: 'final-contract', load: () => import('../../css/pushsale-v87-pagination-actions-upsale.css') },
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
