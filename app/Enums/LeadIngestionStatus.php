<?php

namespace App\Enums;

enum LeadIngestionStatus: string
{
    case Gathering = 'gathering';
    case Pending = 'pending';
    case Processed = 'processed';
    case Duplicate = 'duplicate';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';

    public function label(): string
    {
        return __('enums.lead_ingestion_status.'.$this->value);
    }
}
