<?php

namespace App\Services\Leads;

use App\Enums\CampaignLeadAllocation;
use App\Models\MarketingSource;

class LeadAllocationResolver
{
    public function __construct(
        private readonly LeadAllocationModeService $globalMode,
    ) {}

    /** Lead từ chiến dịch / webhook có nên chia tự động cho sale không? */
    public function shouldAutoAssign(?MarketingSource $campaign): bool
    {
        if ($campaign === null) {
            return $this->globalMode->isAuto();
        }

        $mode = $campaign->lead_allocation instanceof CampaignLeadAllocation
            ? $campaign->lead_allocation
            : (CampaignLeadAllocation::tryFrom((string) ($campaign->lead_allocation ?? ''))
                ?? CampaignLeadAllocation::Inherit);

        return $mode->resolvesAuto($this->globalMode->current());
    }
}
