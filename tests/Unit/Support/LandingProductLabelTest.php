<?php

namespace Tests\Unit\Support;

use App\Integrations\Landing\LandingFormDriver;
use App\Support\LandingProductLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LandingProductLabelTest extends TestCase
{
    #[DataProvider('urlProvider')]
    public function test_detects_landing_urls(string $value, bool $expected): void
    {
        $this->assertSame($expected, LandingProductLabel::looksLikeUrl($value));
    }

    public static function urlProvider(): array
    {
        return [
            ['https://www.shophalinh.click/decalhoasen?utm_source=x', true],
            ['http://tieudung26.shop/botdietco', true],
            ['www.example.com/path', true],
            ['Decal hoa sen 289k', false],
            ['Mua 2 Thỏi serum', false],
        ];
    }

    public function test_driver_does_not_create_item_from_url_products_field(): void
    {
        $normalized = (new LandingFormDriver)->normalize([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'products' => 'https://www.shophalinh.click/decalhoasen?utm_source=2107_NH_8443_2&gad_source=2',
        ]);

        $this->assertSame([], $normalized['items']);
        $this->assertStringContainsString('URL landing', (string) $normalized['product_interest']);
        $this->assertStringNotContainsString('https://', (string) $normalized['product_interest']);
    }

    public function test_driver_still_parses_real_product_labels(): void
    {
        $normalized = (new LandingFormDriver)->normalize([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'products' => 'Decal hoa sen 289k',
        ]);

        $this->assertCount(1, $normalized['items']);
        $this->assertSame('Decal hoa sen 289k', $normalized['items'][0]['product_name']);
        $this->assertGreaterThan(0, $normalized['items'][0]['unit_price']);
    }
}
