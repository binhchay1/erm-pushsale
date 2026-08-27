<?php

namespace App\Services\Shipping\Gateways\NetShip;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Map tên Tỉnh/Huyện/Xã (shipping_geo) → ID số NetShip.
 */
final class NetShipAddressResolver
{
    public function __construct(private readonly NetShipApiClient $client) {}

    /**
     * @return array{provinceId: int, districtId: int, wardId: int}
     */
    public function resolve(string $provinceName, string $districtName, string $wardName): array
    {
        $province = $this->matchOne($this->provinces(), $provinceName, 'tỉnh/thành');
        $district = $this->matchOne(
            $this->districts((int) $province['id']),
            $districtName,
            'quận/huyện',
        );
        $ward = $this->matchOne(
            $this->wards((int) $district['id']),
            $wardName,
            'phường/xã',
        );

        return [
            'provinceId' => (int) $province['id'],
            'districtId' => (int) $district['id'],
            'wardId' => (int) $ward['id'],
        ];
    }

    /** @return list<array{id: int|string, name: string}> */
    public function provinces(): array
    {
        return $this->cachedList('provinces', fn () => $this->client->listProvinces());
    }

    /** @return list<array{id: int|string, name: string}> */
    public function districts(int $provinceId): array
    {
        return $this->cachedList("districts:{$provinceId}", fn () => $this->client->listDistricts($provinceId));
    }

    /** @return list<array{id: int|string, name: string}> */
    public function wards(int $districtId): array
    {
        return $this->cachedList("wards:{$districtId}", fn () => $this->client->listWards($districtId));
    }

    /**
     * @param  list<array{id: int|string, name: string}>  $items
     * @return array{id: int|string, name: string}
     */
    private function matchOne(array $items, string $needle, string $label): array
    {
        $needleNorm = $this->normalize($needle);
        if ($needleNorm === '' || $items === []) {
            throw ValidationException::withMessages([
                'shipping_geo' => __('messages.shipping_actions.netship_address_unmapped', ['part' => $label, 'value' => $needle]),
            ]);
        }

        foreach ($items as $item) {
            if ($this->normalize((string) ($item['name'] ?? '')) === $needleNorm) {
                return $item;
            }
        }

        // Khớp chứa tên (vd. "Quận 1" vs "1")
        $contains = [];
        foreach ($items as $item) {
            $name = $this->normalize((string) ($item['name'] ?? ''));
            if ($name !== '' && (str_contains($name, $needleNorm) || str_contains($needleNorm, $name))) {
                $contains[] = $item;
            }
        }
        if (count($contains) === 1) {
            return $contains[0];
        }

        throw ValidationException::withMessages([
            'shipping_geo' => __('messages.shipping_actions.netship_address_unmapped', ['part' => $label, 'value' => $needle]),
        ]);
    }

    /**
     * @param  callable(): list<array{id: int|string, name: string}>  $loader
     * @return list<array{id: int|string, name: string}>
     */
    private function cachedList(string $suffix, callable $loader): array
    {
        $key = 'netship:address:'.$this->client->cacheKeyPrefix().':'.$suffix;

        return Cache::remember($key, now()->addDay(), function () use ($loader): array {
            $raw = $loader();
            $list = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = $row['id'] ?? $row['provinceId'] ?? $row['districtId'] ?? $row['wardId'] ?? null;
                $name = $row['name'] ?? $row['provinceName'] ?? $row['districtName'] ?? $row['wardName'] ?? null;
                if ($id === null || ! filled($name)) {
                    continue;
                }
                $list[] = ['id' => $id, 'name' => (string) $name];
            }

            return $list;
        });
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $map = [
            'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
            'ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e','ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
            'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
            'ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o','ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
            'ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
            'đ'=>'d',
        ];
        $value = strtr($value, $map);
        $value = preg_replace('/^(tinh|thanh pho|tp\.?|quan|huyen|thi xa|phuong|xa|thi tran)\s+/u', '', $value) ?? $value;

        return trim($value);
    }
}
