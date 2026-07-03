<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Chuẩn hóa giá trị tiền tệ text từ landing (Ladipage) về số nguyên VND.
 *
 * Hỗ trợ các dạng phổ biến trên landing Việt Nam:
 *  - "289k", "289 K"           => 289000
 *  - "298.000đ", "298,000 VND" => 298000
 *  - "1tr2", "1.2tr", "2 triệu" => 1200000 / 2000000
 *  - "149000", 149000          => 149000
 */
final class MoneyParser
{
    public static function parse(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_int($value)) {
            return max(0, $value);
        }

        if (is_float($value)) {
            return max(0, (int) round($value));
        }

        $raw = Str::of((string) $value)->lower()->replace(' ', '')->value();

        if ($raw === '') {
            return $default;
        }

        // "triệu" / "tr" — có thể kèm phần lẻ: 1tr2, 1.2tr, 2trieu
        if (preg_match('/^([0-9]+([.,][0-9]+)?)(tr|trieu|triệu)([0-9]*)$/u', $raw, $m)) {
            $whole = (float) str_replace(',', '.', $m[1]);
            $millions = $whole * 1_000_000;
            // Đuôi sau "tr" là phần trăm nghìn: 1tr2 => 1.200.000
            if (($m[4] ?? '') !== '') {
                $millions += ((int) $m[4]) * 100_000;
            }

            return max(0, (int) round($millions));
        }

        // "k" / "nghìn" — 289k => 289000
        if (preg_match('/^([0-9]+([.,][0-9]+)?)(k|nghìn|nghin|ngàn|ngan)$/u', $raw, $m)) {
            $whole = (float) str_replace(',', '.', $m[1]);

            return max(0, (int) round($whole * 1_000));
        }

        // Dạng số có dấu ngăn cách nghìn: 298.000, 298,000, 1.500.000
        $digits = preg_replace('/[^0-9]/', '', $raw);

        if ($digits === '' || $digits === null) {
            return $default;
        }

        return max(0, (int) $digits);
    }
}
