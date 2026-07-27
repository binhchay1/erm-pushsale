<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PushsaleUnifiedShellContractTest extends TestCase
{
    public function test_unified_page_shell_css_is_loaded_last_among_page_shell_contracts(): void
    {
        $registry = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/pushsaleStyleRegistry.js');
        $this->assertIsString($registry);

        $lastContract = strrpos($registry, 'pushsale-unified-page-shell-contract.css');
        $legacyShell = strrpos($registry, 'pushsale-page-shell-menu-contract.css');
        $warehouseFlow = strrpos($registry, 'pushsale-warehouse-flow-contract.css');

        $this->assertNotFalse($lastContract);
        $this->assertGreaterThan($legacyShell, $lastContract);
        $this->assertGreaterThan($warehouseFlow, $lastContract);
    }

    public function test_sidebar_canonical_css_is_loaded_absolutely_last(): void
    {
        $registry = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/pushsaleStyleRegistry.js');
        $this->assertIsString($registry);

        $menuCanonical = strrpos($registry, 'pushsale-sidebar-canonical-contract.css');
        $pageFrame = strrpos($registry, 'pushsale-page-frame-contract.css');
        $adminlteCanonical = strrpos($registry, 'pushsale-adminlte-canonical-contract.css');
        $unifiedShell = strrpos($registry, 'pushsale-unified-page-shell-contract.css');

        $this->assertNotFalse($menuCanonical);
        $this->assertNotFalse($pageFrame);
        $this->assertGreaterThan($pageFrame, $menuCanonical);
        $this->assertGreaterThan($adminlteCanonical, $pageFrame);
        $this->assertGreaterThan($unifiedShell, $menuCanonical);

        $modulesTail = substr($registry, strpos($registry, 'export const PUSHSALE_CSS_MODULES'));
        $lastCss = null;
        if (preg_match_all("/file:\s*'([^']+\.css)'/", $modulesTail, $matches)) {
            $lastCss = end($matches[1]);
        }
        $this->assertSame('pushsale-sidebar-canonical-contract.css', $lastCss);
    }

    public function test_page_header_css_sits_between_page_frame_and_sidebar_canonical(): void
    {
        $registry = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/pushsaleStyleRegistry.js');
        $this->assertIsString($registry);

        $pageHeader = strrpos($registry, 'pushsale-page-header-contract.css');
        $pageFrame = strrpos($registry, 'pushsale-page-frame-contract.css');
        $sidebarCanonical = strrpos($registry, 'pushsale-sidebar-canonical-contract.css');

        $this->assertNotFalse($pageHeader);
        $this->assertGreaterThan($pageFrame, $pageHeader);
        $this->assertGreaterThan($pageHeader, $sidebarCanonical);
    }

    public function test_shared_page_header_renders_pushsale_markup_and_owns_a_single_slot(): void
    {
        $header = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/layout/PageHeader.jsx');
        $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/layouts/AppLayout.jsx');

        $this->assertIsString($header);
        $this->assertIsString($layout);

        $this->assertStringContainsString('m-header-wrap ps-page-header', $header);
        $this->assertStringContainsString('m-header ps-page-header__row', $header);
        $this->assertStringContainsString('ps-page-extra-filters', $header);
        $this->assertStringContainsString('createPortal', $header);

        $this->assertStringContainsString('PageHeaderProvider', $layout);
        $this->assertStringContainsString('<PageHeaderOutlet', $layout);
    }

    public function test_page_header_contract_keeps_shadow_without_bottom_border(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/pushsale-page-header-contract.css');
        $this->assertIsString($css);

        $this->assertStringContainsString('--ps-header-shadow', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.ps-page-header[^{]*\{[^}]*border-bottom:\s*1px/s',
            $css,
            'Header dùng chung không được kẻ viền dưới, chỉ dùng đổ bóng.'
        );
    }

    public function test_shared_contracts_include_phone_breakpoints_for_header_and_topbar(): void
    {
        $pageHeader = file_get_contents(dirname(__DIR__, 2).'/resources/css/pushsale-page-header-contract.css');
        $shellMenu = file_get_contents(dirname(__DIR__, 2).'/resources/css/pushsale-page-shell-menu-contract.css');
        $canonical = file_get_contents(dirname(__DIR__, 2).'/resources/css/pushsale-adminlte-canonical-contract.css');

        $this->assertStringContainsString('@media (max-width: 767px)', $pageHeader);
        $this->assertStringContainsString('ps-page-extra-filters .ps-filter-row', $pageHeader);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $pageHeader);

        $this->assertStringContainsString('@media (max-width: 767px)', $shellMenu);
        $this->assertStringContainsString('pushsale-header-brand', $shellMenu);
        $this->assertStringContainsString('pushsale-header-icon', $shellMenu);
        $this->assertStringContainsString('display: none !important', $shellMenu);

        $this->assertStringContainsString('min-width: 0 !important', $canonical);
        $this->assertStringContainsString('pushsale-header-brand', $canonical);
    }

    public function test_page_shell_exposes_shared_frame_slots(): void
    {
        $shell = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/layout/PushsalePageShell.jsx');
        $this->assertIsString($shell);
        // Header đã gộp về PageHeader dùng chung; shell chỉ còn notice → toolbar → body.
        $this->assertStringContainsString('PageHeader', $shell);
        $this->assertStringContainsString('primaryFilters', $shell);
        $this->assertStringContainsString('advancedFilters', $shell);
        $this->assertStringContainsString('ps-page-shell__notice', $shell);
        $this->assertStringContainsString('ps-page-shell__toolbar', $shell);
        $this->assertStringContainsString('ps-page-shell__body', $shell);
        $this->assertStringNotContainsString('<header', $shell);
    }

    public function test_key_pages_use_shared_page_shell_frame(): void
    {
        $company = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/Admin/Company/Profile.jsx');
        $users = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/Admin/Users/Index.jsx');
        $marketing = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/Admin/Marketing/Dashboard.jsx');
        $saleFilters = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/operations/pushsale/SaleWorkspaceFilters.jsx');

        $this->assertStringContainsString('PushsalePageShell', $company);
        $this->assertStringContainsString('Thông tin đơn vị', $company);
        $this->assertStringContainsString('advancedFilters', $users);
        $this->assertStringContainsString('toolbar', $users);
        $this->assertStringContainsString('PushsalePageShell', $marketing);
        $this->assertStringContainsString('primaryFilters', $marketing);
        $this->assertStringContainsString('PushsalePageShell', $saleFilters);
        $this->assertStringContainsString('Sale tác nghiệp', $saleFilters);
    }

    public function test_sidebar_component_has_no_runtime_hover_hacks(): void
    {
        $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/layout/AppSidebar.jsx');
        $hook = file_get_contents(dirname(__DIR__, 2).'/resources/js/hooks/usePushsaleSidebarMenu.js');

        $this->assertIsString($sidebar);
        $this->assertIsString($hook);
        $this->assertStringNotContainsString('SidebarHoverRuntimeStyle', $sidebar);
        $this->assertStringNotContainsString('forceSecondLevelHover', $sidebar);
        $this->assertStringNotContainsString('style.setProperty', $sidebar);
        $this->assertStringNotContainsString('data-ps-second-hover', $sidebar);
        $this->assertStringContainsString('usePushsaleSidebarMenu', $sidebar);
        $this->assertStringContainsString('pushsale-third-menu', $sidebar);
        $this->assertStringContainsString('is-visible', $sidebar);
    }

    public function test_captured_templates_are_normalized_before_rendering_rows(): void
    {
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/pushsale/BusinessPage.jsx');
        $this->assertIsString($page);
        $this->assertStringContainsString('pushsale-primary-header-wrap', $page);
        $this->assertStringContainsString('pushsale-composite-filter-row', $page);
        $this->assertStringContainsString('pushsale-nested-content-wrapper', $page);
    }
}
