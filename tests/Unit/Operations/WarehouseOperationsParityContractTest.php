<?php

namespace Tests\Unit\Operations;

use PHPUnit\Framework\TestCase;

class WarehouseOperationsParityContractTest extends TestCase
{
    public function test_warehouse_operations_css_is_registered_before_canonical_tail(): void
    {
        $registry = file_get_contents(dirname(__DIR__, 3).'/resources/js/lib/pushsaleStyleRegistry.js');
        $this->assertIsString($registry);
        $this->assertStringContainsString('pushsale-warehouse-operations-contract.css', $registry);

        $ops = strpos($registry, 'pushsale-warehouse-operations-contract.css');
        $sidebar = strrpos($registry, 'pushsale-sidebar-canonical-contract.css');
        $this->assertNotFalse($ops);
        $this->assertNotFalse($sidebar);
        $this->assertLessThan($sidebar, $ops);
    }

    public function test_warehouse_operations_css_contains_pushsale_level_and_cell_rules(): void
    {
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/pushsale-warehouse-operations-contract.css');
        $this->assertIsString($css);
        $this->assertStringContainsString('.flag.level-1', $css);
        $this->assertStringContainsString('#a6ffa8', $css);
        $this->assertStringContainsString('.flag.level-4', $css);
        $this->assertStringContainsString('orangered', $css);
        $this->assertStringContainsString('.nha-mang', $css);
        $this->assertStringContainsString('textarea.txt-mof', $css);
        $this->assertStringContainsString('.ttgh35', $css);
        $this->assertStringContainsString('fam-primary', $css);
        $this->assertStringContainsString('.ps-wh-floating-actions:hover .hidden-actions', $css);
    }

    public function test_sale_operations_css_is_registered(): void
    {
        $registry = file_get_contents(dirname(__DIR__, 3).'/resources/js/lib/pushsaleStyleRegistry.js');
        $this->assertStringContainsString('pushsale-sale-operations-contract.css', $registry);
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/pushsale-sale-operations-contract.css');
        $this->assertStringContainsString('.flag.level-4', $css);
        $this->assertStringContainsString('orangered', $css);
        $this->assertStringContainsString('.td-message', $css);
        $this->assertStringContainsString('textarea.txt-mof', $css);
        $this->assertFileExists(dirname(__DIR__, 3).'/docs/reference/pushsale-sale-operations.html');
        $this->assertFileExists(dirname(__DIR__, 3).'/docs/reference/pushsale-warehouse-operations.html');
    }
}
