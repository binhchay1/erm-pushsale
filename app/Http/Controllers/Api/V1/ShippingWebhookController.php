<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InboundEventSource;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Jobs\Shipping\ProcessShippingWebhookJob;
use App\Models\ShippingPartnerConnection;
use App\Services\Inbound\InboundEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingWebhookController extends Controller
{
    use ApiResponds;

    public function handle(Request $request, string $provider): JsonResponse
    {
        if (! array_key_exists($provider, config('shipping_partners.providers', []))) {
            return $this->error(__('messages.shipping.provider_unsupported'), 404);
        }

        $event = app(InboundEventRecorder::class)->record(
            $request,
            InboundEventSource::ShippingWebhook,
            $provider,
            null,
            $request->all(),
        );

        $connection = ShippingPartnerConnection::forProvider($provider);

        if (! $connection->is_enabled && ! app()->environment('local')) {
            $event->markRejected(503, __('messages.shipping.partner_disabled'));

            return $this->error(__('messages.shipping.partner_disabled'), 503);
        }

        $expected = $connection->webhook_secret;
        if ($expected) {
            $provided = $request->header('X-Webhook-Secret')
                ?? $request->header('X-Api-Key')
                ?? $request->query('secret');

            if (! $provided || ! hash_equals($expected, (string) $provided)) {
                $event->markRejected(401, __('messages.shipping.unauthorized'));

                return $this->error(__('messages.shipping.unauthorized'), 401);
            }
        }

        if ($connection->company_id) {
            $event->update(['company_id' => $connection->company_id]);
        }

        $event->markQueued();

        ProcessShippingWebhookJob::dispatch($provider, $request->all(), $event->id);

        return $this->success(
            ['queued' => true, 'correlation_id' => $event->correlation_id],
            __('messages.shipping.queued'),
            202,
        );
    }
}
