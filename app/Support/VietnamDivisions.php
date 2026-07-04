<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Dữ liệu hành chính Việt Nam (Tỉnh/TP → Quận/Huyện → Phường/Xã).
 *
 * Nguồn: resources/data/vn-divisions.json (rút gọn từ provinces.open-api.vn).
 * Cache in-memory qua Cache::rememberForever để tránh đọc/parse file mỗi request.
 */
class VietnamDivisions
{
    public const MODE_OLD = 'old';

    public const MODE_NEW = 'new'; // Đơn vị hành chính 2 cấp từ 01/07/2025 (bỏ quận/huyện).

    private const CACHE_KEY = 'vn_divisions_v1';

    private const CACHE_KEY_NEW = 'vn_divisions_2025_v1';

    /** @return array{provinces: list<array{code:int,name:string}>, districts: array<string, list<array{code:int,name:string}>>, wards: array<string, list<array{code:int,name:string}>>} */
    private static function data(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $path = resource_path('data/vn-divisions.json');

            if (! is_file($path)) {
                return ['provinces' => [], 'districts' => [], 'wards' => []];
            }

            return json_decode((string) file_get_contents($path), true) ?: [
                'provinces' => [], 'districts' => [], 'wards' => [],
            ];
        });
    }

    /**
     * Dữ liệu hành chính 2 cấp 2025 (Tỉnh/TP → Phường/Xã, không còn Quận/Huyện).
     *
     * @return array{provinces: list<array{code:string,name:string}>, wards: array<string, list<array{code:string,name:string}>>}
     */
    private static function data2025(): array
    {
        return Cache::rememberForever(self::CACHE_KEY_NEW, function (): array {
            $path = resource_path('data/vn-divisions-2025.json');

            if (! is_file($path)) {
                return ['provinces' => [], 'wards' => []];
            }

            return json_decode((string) file_get_contents($path), true) ?: [
                'provinces' => [], 'wards' => [],
            ];
        });
    }

    /** @return list<array{code:string,name:string}> */
    public static function newProvinces(): array
    {
        return self::data2025()['provinces'] ?? [];
    }

    /** @return list<array{code:string,name:string}> */
    public static function newWards(int|string $provinceCode): array
    {
        return self::data2025()['wards'][(string) $provinceCode] ?? [];
    }

    public static function newProvinceName(int|string|null $code): ?string
    {
        return self::nameOf(self::newProvinces(), $code);
    }

    public static function newWardName(int|string|null $provinceCode, int|string|null $code): ?string
    {
        return self::nameOf(self::newWards((string) $provinceCode), $code);
    }

    /** @return list<array{code:int,name:string}> */
    public static function provinces(): array
    {
        return self::data()['provinces'] ?? [];
    }

    /** @return list<array{code:int,name:string}> */
    public static function districts(int|string $provinceCode): array
    {
        return self::data()['districts'][(string) $provinceCode] ?? [];
    }

    /** @return list<array{code:int,name:string}> */
    public static function wards(int|string $districtCode): array
    {
        return self::data()['wards'][(string) $districtCode] ?? [];
    }

    public static function provinceName(int|string|null $code): ?string
    {
        return self::nameOf(self::provinces(), $code);
    }

    public static function districtName(int|string|null $provinceCode, int|string|null $code): ?string
    {
        return self::nameOf(self::districts((string) $provinceCode), $code);
    }

    public static function wardName(int|string|null $districtCode, int|string|null $code): ?string
    {
        return self::nameOf(self::wards((string) $districtCode), $code);
    }

    /** @param list<array{code:int,name:string}> $list */
    private static function nameOf(array $list, int|string|null $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        foreach ($list as $item) {
            if ((string) $item['code'] === (string) $code) {
                return $item['name'];
            }
        }

        return null;
    }
}
