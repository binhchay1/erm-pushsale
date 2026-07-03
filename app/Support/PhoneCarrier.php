<?php

namespace App\Support;

/**
 * Suy ra nhà mạng di động Việt Nam từ ĐẦU SỐ (chuẩn 10 số sau chuyển đổi 2018).
 * Hiển thị đúng theo số điện thoại thay vì dựa vào giá trị lưu rời rạc.
 */
class PhoneCarrier
{
    /** @var array<string, array{0: string, 1: list<string>}> key => [tên hiển thị, danh sách đầu số 3 chữ] */
    private const PREFIXES = [
        'viettel' => ['Viettel', ['032', '033', '034', '035', '036', '037', '038', '039', '086', '096', '097', '098']],
        'vinaphone' => ['VinaPhone', ['081', '082', '083', '084', '085', '088', '091', '094']],
        'mobifone' => ['MobiFone', ['070', '076', '077', '078', '079', '089', '090', '093']],
        'vietnamobile' => ['Vietnamobile', ['052', '056', '058', '092']],
        'gmobile' => ['Gmobile', ['059', '099']],
        'itel' => ['iTel', ['087']],
    ];

    /** @return array{key: string, label: string}|null */
    public static function resolve(?string $phone): ?array
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Chuẩn hoá về dạng nội địa bắt đầu bằng 0 (bỏ +84 / 84).
        if (str_starts_with($digits, '84')) {
            $digits = '0'.substr($digits, 2);
        }

        if (strlen($digits) < 3 || $digits[0] !== '0') {
            return null;
        }

        $prefix = substr($digits, 0, 3);

        foreach (self::PREFIXES as $key => [$label, $prefixes]) {
            if (in_array($prefix, $prefixes, true)) {
                return ['key' => $key, 'label' => $label];
            }
        }

        return null;
    }

    public static function label(?string $phone): ?string
    {
        return self::resolve($phone)['label'] ?? null;
    }

    public static function key(?string $phone): ?string
    {
        return self::resolve($phone)['key'] ?? null;
    }
}
