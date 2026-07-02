<?php

namespace App\Enums;

enum CampaignLeadAllocation: string
{
    case Inherit = 'inherit';
    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return __('enums.campaign_lead_allocation.'.$this->value);
    }

    public function resolvesAuto(LeadAllocationMode $globalMode): bool
    {
        return match ($this) {
            self::Manual => false,
            self::Auto => true,
            self::Inherit => $globalMode->isAuto(),
        };
    }
}
