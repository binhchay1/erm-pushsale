<?php

namespace App\Services\Leads;

use App\Enums\LeadAllocationMode;
use App\Models\AppSetting;

class LeadAllocationModeService
{
    public const SETTING_KEY = 'lead_allocation_mode';

    public function current(): LeadAllocationMode
    {
        return LeadAllocationMode::tryFrom(
            (string) AppSetting::get(self::SETTING_KEY, LeadAllocationMode::Auto->value)
        ) ?? LeadAllocationMode::Auto;
    }

    public function isAuto(): bool
    {
        return $this->current()->isAuto();
    }

    public function set(LeadAllocationMode $mode): void
    {
        AppSetting::set(self::SETTING_KEY, $mode->value);
    }
}
