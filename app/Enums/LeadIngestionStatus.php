<?php

namespace App\Enums;

enum LeadIngestionStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Duplicate = 'duplicate';
    case Failed = 'failed';

    public function label(): string
    {
        return __('enums.lead_ingestion_status.'.$this->value);
    }
}
