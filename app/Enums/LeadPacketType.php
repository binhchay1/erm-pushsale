<?php

namespace App\Enums;

enum LeadPacketType: string
{
    case Lead = 'lead';
    case FollowUp = 'follow_up';
    case Upsell = 'upsell';
    case LateUpsell = 'late_upsell';
    case OrphanUpsell = 'orphan_upsell';

    public function label(): string
    {
        return __('enums.lead_packet_type.'.$this->value);
    }

    public function isSupplemental(): bool
    {
        return $this !== self::Lead;
    }
}
