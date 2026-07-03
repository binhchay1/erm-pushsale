<?php

namespace Tests\Unit\Support;

use App\Support\MoneyParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyParserTest extends TestCase
{
    #[DataProvider('moneyProvider')]
    public function test_parses_vietnamese_money(mixed $input, int $expected): void
    {
        $this->assertSame($expected, MoneyParser::parse($input));
    }

    /** @return array<string, array{0: mixed, 1: int}> */
    public static function moneyProvider(): array
    {
        return [
            'k suffix' => ['289k', 289_000],
            'k with space' => ['149 K', 149_000],
            'dong separators' => ['298.000đ', 298_000],
            'comma vnd' => ['298,000 VND', 298_000],
            'plain digits' => ['149000', 149_000],
            'integer' => [149_000, 149_000],
            'trieu' => ['2 triệu', 2_000_000],
            'trieu fraction' => ['1tr2', 1_200_000],
            'empty' => ['', 0],
            'null' => [null, 0],
        ];
    }
}
