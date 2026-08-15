<?php

namespace Tests\Unit\Support;

use App\Support\VietnamesePhone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VietnamesePhoneTest extends TestCase
{
    #[DataProvider('validPhones')]
    public function test_normalizes_valid_vietnamese_mobile_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, VietnamesePhone::normalize($input));
    }

    #[DataProvider('invalidPhones')]
    public function test_rejects_invalid_phone_numbers(string $input): void
    {
        $this->assertNull(VietnamesePhone::normalize($input));
    }

    public static function validPhones(): array
    {
        return [
            '10 digits with leading 0' => ['0901234567', '0901234567'],
            '9 digits without leading 0' => ['901234567', '0901234567'],
            'formatted with dots' => ['091.222.3333', '0912223333'],
            'international +84' => ['+84912223333', '0912223333'],
            'international 84 prefix' => ['84912223333', '0912223333'],
            'international 0084 prefix' => ['0084912223333', '0912223333'],
            'vinaphone 9 digits' => ['358240295', '0358240295'],
        ];
    }

    public static function invalidPhones(): array
    {
        return [
            '11 digits with leading 0' => ['03582402952'],
            'too short' => ['0912345'],
            'landline style 11 digits' => ['02438247247'],
            'empty' => [''],
            'letters only' => ['abcdefghij'],
        ];
    }

    public function test_lookup_variants_cover_common_vietnamese_formats(): void
    {
        $variants = VietnamesePhone::lookupVariants('+84912223333');

        $this->assertContains('0912223333', $variants);
        $this->assertContains('912223333', $variants);
        $this->assertContains('84912223333', $variants);
        $this->assertContains('+84912223333', $variants);
    }
}
