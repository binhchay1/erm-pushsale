<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Models\ShippingPartnerConnection;
use App\Services\Shipping\ShippingWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingWebhookController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected ShippingWebhookService $service,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        if (! array_key_exists($provider, config('shipping_partners.providers', []))) {
            return $this->error('Đơn vị vận chuyển không hỗ trợ', 404);
        }

        $connection = ShippingPartnerConnection::forProvider($provider);
        if (! $connection->is_enabled && ! app()->environment('local')) {
            return $this->error('Đối tác vận chuyển chưa được bật', 503);
        }

        $expected = $connection->webhook_secret;
        if ($expected) {
            $provided = $request->header('X-Webhook-Secret')
                ?? $request->header('X-Api-Key')
                ?? $request->query('secret');

            if (! $provided || ! hash_equals($expected, (string) $provided)) {
                return $this->error('Webhook vận chuyển không hợp lệ', 401);
            }
        }

        $event = $this->service->process($provider, $request->all());

        return $this->success([
            'event_id' => $event->id,
            'result' => $event->result,
            'order_id' => $event->order_id,
            'cod_mismatch' => $event->is_cod_mismatch,
        ], 'Shipping webhook processed');
    }
}
