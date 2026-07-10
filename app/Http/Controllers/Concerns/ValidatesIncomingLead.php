<?php

namespace App\Http\Controllers\Concerns;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Http\Traits\ApiResponds;
use App\Models\InboundEvent;
use App\Services\Leads\LeadPayloadValidator;
use Illuminate\Http\JsonResponse;

trait ValidatesIncomingLead
{
    use ApiResponds;

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validateIncomingLeadOrError(
        LeadPayloadNormalizer $driver,
        array $payload,
        ?InboundEvent $event = null,
    ): ?JsonResponse {
        $result = app(LeadPayloadValidator::class)->validate($driver, $payload);

        if ($result['valid']) {
            return null;
        }

        $message = __('messages.webhook.validation_failed');

        $event?->markRejected(422, $message);

        return $this->error($message, 422, $result['errors']);
    }
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function validateIncomingUpsellOrError(
        LeadPayloadNormalizer $driver,
        array $payload,
        ?InboundEvent $event = null,
    ): ?JsonResponse {
        $result = app(LeadPayloadValidator::class)->validateUpsell($driver, $payload);

        if ($result['valid']) {
            return null;
        }

        $message = __('messages.webhook.validation_failed');
        $event?->markRejected(422, $message);

        return $this->error($message, 422, $result['errors']);
    }

}
