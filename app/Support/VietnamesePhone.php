<?php

namespace App\Support;

/**
 * Chuẩn hóa & kiểm tra số di động Việt Nam.
 *
 * Chấp nhận:
 * - 9 chữ số (không có 0 đầu): 912345678
 * - 10 chữ số (có 0 đầu): 0912345678
 * - Quốc tế: +84 / 84 / 0084 + 9 chữ số thuê bao
 */
class VietnamesePhone
{
    public static function digits(?string $raw): string
    {
        return preg_replace('/\D+/', '', (string) $raw) ?? '';
    }

    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }

    /** Chuẩn hóa về dạng 0XXXXXXXXX (10 chữ số). Trả null nếu không hợp lệ. */
    public static function normalize(?string $raw): ?string
    {
        $digits = self::digits($raw);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0084')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '84')) {
            $digits = substr($digits, 2);
        }

        $len = strlen($digits);

        if ($len === 9 && preg_match('/^[35789]/', $digits)) {
            $digits = '0'.$digits;
        } elseif ($len !== 10 || ! str_starts_with($digits, '0')) {
            return null;
        }

        return preg_match('/^0[35789]\d{8}$/', $digits) ? $digits : null;
    }
}
