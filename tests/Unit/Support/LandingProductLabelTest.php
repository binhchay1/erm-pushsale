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
        $this->assertNull($normalized['product_interest']);
    }

    public function test_driver_parses_form_item_array_as_upsell_text(): void
    {
        $normalized = (new LandingFormDriver)->normalize([
            'name' => 'Nguyenanhhuy',
            'phone' => '0944767989',
            'utm_source' => '2107_NH_8443_2',
            'link' => 'https://www.shophalinh.click/decalhoasen?utm_source=2107_NH_8443_2',
            'form_item[2]' => ['Mua 1 Tấm 169K + 30K Ship'],
        ]);

        $this->assertSame('Nguyenanhhuy', $normalized['customer_name']);
        $this->assertCount(1, $normalized['items']);
        $this->assertSame('Mua 1 Tấm 169K + 30K Ship', $normalized['items'][0]['product_name']);
        $this->assertSame('combo', $normalized['items'][0]['item_type']);
        $this->assertSame(169000, $normalized['items'][0]['unit_price']);
    }

    public function test_driver_does_not_scrape_bare_digits_as_price(): void
    {
        $normalized = (new LandingFormDriver)->normalize([
            'name' => 'Huynh Van Thanh',
            'phone' => '0918253158',
            'products' => 'Goi dac biet ma 1785354232768',
        ]);

        $this->assertCount(1, $normalized['items']);
        $this->assertSame(0, $normalized['items'][0]['unit_price']);
        $this->assertTrue($normalized['items'][0]['meta']['text_only']);
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

    public function test_driver_keeps_bundle_price_when_label_has_free_ship(): void
    {
        $normalized = (new LandingFormDriver)->normalize([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'products' => 'MUA 4 GÓI + Tặng 5 Đồng xu ngũ sắc: 649k + Miễn Phí Ship',
        ]);

        $this->assertCount(1, $normalized['items']);
        $this->assertSame(649000, $normalized['items'][0]['unit_price']);
    }

    public function test_driver_skips_ship_fee_but_keeps_product_price(): void
    {
        $normalized = (new LandingFormDriver)->normalize([
            'name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'products' => 'MUA 1 GÓI: 179k + 30k Phí Ship',
        ]);

        $this->assertCount(1, $normalized['items']);
        $this->assertSame(179000, $normalized['items'][0]['unit_price']);
    }
}
