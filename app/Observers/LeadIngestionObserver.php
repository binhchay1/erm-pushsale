<?php

namespace App\Observers;

use App\Models\LeadIngestion;
use App\Observers\Concerns\DispatchesSqlDailyFacts;

class LeadIngestionObserver
{
    use DispatchesSqlDailyFacts;

    public function created(LeadIngestion $lead): void
    {
        $this->dispatchAffectedDates($lead);
    }

    public function updated(LeadIngestion $lead): void
    {
        $watched = [
            'company_id',
            'marketing_source_id',
            'landing_connection_id',
            'landing_connection_source_id',
            'utm_campaign',
            'status',
            'packet_type',
            'counts_as_lead',
            'requires_review',
            'order_id',
            'related_order_id',
            'created_at',
        ];

        if (! $lead->wasChanged($watched)) {
            return;
        }

        $this->dispatchAffectedDates($lead);
    }

    public function deleted(LeadIngestion $lead): void
    {
        $this->dispatchAffectedDates($lead);
    }

    private function dispatchAffectedDates(LeadIngestion $lead): void
    {
        $companyId = (int) ($lead->company_id ?: $lead->getOriginal('company_id'));
        $this->dispatchCurrentAndOriginalDate($lead, $companyId, 'created_at');
    }
}
