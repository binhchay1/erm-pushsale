<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PushsaleUnifiedShellContractTest extends TestCase
{
    public function test_unified_page_shell_css_is_loaded_last(): void
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

    public function test_captured_templates_are_normalized_before_rendering_rows(): void
    {
        $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/Pushsale/BusinessPage.jsx');
        $this->assertIsString($page);
        $this->assertStringContainsString('pushsale-primary-header-wrap', $page);
        $this->assertStringContainsString('pushsale-composite-filter-row', $page);
        $this->assertStringContainsString('pushsale-nested-content-wrapper', $page);
    }
}
