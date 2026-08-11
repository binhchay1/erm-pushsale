<?php

namespace App\Observers;

use App\Enums\InboundEventSource;
use App\Models\InboundEvent;
use App\Observers\Concerns\DispatchesSqlDailyFacts;

class InboundEventObserver
{
    use DispatchesSqlDailyFacts;

    public function created(InboundEvent $event): void
    {
        $this->dispatchAffectedDates($event);
    }

    public function updated(InboundEvent $event): void
    {
        $watched = ['source', 'channel', 'status', 'http_status', 'created_at'];
        if (! $event->wasChanged($watched)) {
            return;
        }

        $this->dispatchAffectedDates($event);
    }

    public function deleted(InboundEvent $event): void
    {
        $this->dispatchAffectedDates($event);
    }

    private function dispatchAffectedDates(InboundEvent $event): void
    {
        $source = $event->source instanceof InboundEventSource ? $event->source->value : (string) $event->source;
        $originalSource = $event->getOriginal('source');
        $originalSource = $originalSource instanceof InboundEventSource ? $originalSource->value : (string) $originalSource;

        if ($source !== InboundEventSource::LandingWebhook->value && $originalSource !== InboundEventSource::LandingWebhook->value) {
            return;
        }

        $companyId = (int) ($event->company_id ?: $event->getOriginal('company_id'));
        if ($companyId <= 0) {
            $companyId = $this->companyIdFromLandingChannel((string) ($event->channel ?: $event->getOriginal('channel')));
        }

        $this->dispatchCurrentAndOriginalDate($event, $companyId, 'created_at');
    }
}
