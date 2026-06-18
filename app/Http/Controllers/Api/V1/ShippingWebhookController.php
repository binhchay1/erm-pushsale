<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Jobs\Shipping\ProcessShippingWebhookJob;
use App\Models\ShippingPartnerConnection;
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

        $connection = ShippingPartnerConnection::forProvider($provider);
        if (! $connection->is_enabled && ! app()->environment('local')) {
            return $this->error(__('messages.shipping.partner_disabled'), 503);
        }

        $expected = $connection->webhook_secret;
        if ($expected) {
            $provided = $request->header('X-Webhook-Secret')
                ?? $request->header('X-Api-Key')
                ?? $request->query('secret');

            if (! $provided || ! hash_equals($expected, (string) $provided)) {
                return $this->error(__('messages.shipping.unauthorized'), 401);
            }
        }

        ProcessShippingWebhookJob::dispatch($provider, $request->all());

        return $this->success(
            ['queued' => true],
            __('messages.shipping.queued'),
            202,
        );
    }
}
