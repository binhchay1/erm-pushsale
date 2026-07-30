<?php

namespace Tests\Unit;

use App\Support\ReportExportIdentity;
use Tests\TestCase;

class ReportExportIdentityTest extends TestCase
{
    public function test_brand_never_contains_pushsale(): void
    {
        config(['app.name' => 'Pushsale Demo']);
        config(['saleops.brand.name' => 'ERM SaleOps']);
        config(['saleops.brand.short' => 'SaleOps']);

        $this->assertSame('ERM SaleOps', ReportExportIdentity::brand());
        $this->assertStringNotContainsString('pushsale', strtolower(ReportExportIdentity::brand()));
    }

    public function test_basename_strips_legacy_pushsale_prefix(): void
    {
        config(['app.name' => 'ERM SaleOps']);
        config(['saleops.brand.short' => 'SaleOps']);

        $name = ReportExportIdentity::basename('pushsale-2-7-1', '20260728-120000');

        $this->assertStringStartsWith('erm-saleops-', $name);
        $this->assertStringNotContainsString('pushsale', strtolower($name));
        $this->assertStringContainsString('2-7-1', $name);
    }

    public function test_sanitize_filename_rewrites_pushsale_token(): void
    {
        config(['app.name' => 'ERM SaleOps']);

        $safe = ReportExportIdentity::sanitizeFilename('pushsale-marketing-1.xls');

        $this->assertSame('erm-saleops-marketing-1', $safe);
    }
}
