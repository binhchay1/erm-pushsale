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
        $adminlteCanonical = strrpos($registry, 'pushsale-adminlte-canonical-contract.css');
        $unifiedShell = strrpos($registry, 'pushsale-unified-page-shell-contract.css');
        $legacyMenu = strrpos($registry, 'pushsale-sidebar-menu-contract.css');

        $this->assertNotFalse($menuCanonical);
        $this->assertGreaterThan($adminlteCanonical, $menuCanonical);
        $this->assertGreaterThan($unifiedShell, $menuCanonical);
        $this->assertGreaterThan($legacyMenu, $menuCanonical);

        $modulesTail = substr($registry, strpos($registry, 'export const PUSHSALE_CSS_MODULES'));
        $lastCss = null;
        if (preg_match_all("/file:\s*'([^']+\.css)'/", $modulesTail, $matches)) {
            $lastCss = end($matches[1]);
        }
        $this->assertSame('pushsale-sidebar-canonical-contract.css', $lastCss);
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
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/Pushsale/BusinessPage.jsx');
        $this->assertIsString($page);
        $this->assertStringContainsString('pushsale-primary-header-wrap', $page);
        $this->assertStringContainsString('pushsale-composite-filter-row', $page);
        $this->assertStringContainsString('pushsale-nested-content-wrapper', $page);
    }
}
