<?php

namespace App\Casts;

use App\Enums\LeadPacketType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast packet_type chấp nhận cả giá trị lịch sử trước khi enum LeadPacketType được chuẩn hoá
 * ('base'/'main'/'primary' → lead, 'upsale'/'late_upsale'/'orphan_upsale' → upsell tương ứng).
 *
 * Dữ liệu cũ vẫn nằm trong DB (xem App\Support\MarketingPacketMetrics), cast gốc sẽ ném
 * ValueError và làm hỏng cả trang danh sách lead.
 *
 * @implements CastsAttributes<LeadPacketType|null, LeadPacketType|string|null>
 */
class LegacyLeadPacketType implements CastsAttributes
{
    private const LEGACY_MAP = [
        'base' => LeadPacketType::Lead,
        'main' => LeadPacketType::Lead,
        'primary' => LeadPacketType::Lead,
        'upsale' => LeadPacketType::Upsell,
        'late_upsale' => LeadPacketType::LateUpsell,
        'orphan_upsale' => LeadPacketType::OrphanUpsell,
    ];

    public function get(Model $model, string $key, mixed $value, array $attributes): ?LeadPacketType
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof LeadPacketType) {
            return $value;
        }

        $value = (string) $value;

        return LeadPacketType::tryFrom($value) ?? self::LEGACY_MAP[strtolower($value)] ?? null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof LeadPacketType) {
            return $value->value;
        }

        $value = (string) $value;

        return (LeadPacketType::tryFrom($value) ?? self::LEGACY_MAP[strtolower($value)] ?? null)?->value;
    }
}
