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
        $contentLength = (int) ($request->header('Content-Length') ?: strlen($request->getContent()));
        if ($contentLength > (int) config('security.webhook.max_payload_kb', 512) * 1024) {
            return $this->error(__('messages.webhook.payload_too_large'), 413);
        }
        if (! array_key_exists($provider, config('shipping_partners.providers', []))) {
            return $this->error(__('messages.shipping.provider_unsupported'), 404);
        }

        $payload = $request->all();
        $providedSecret = $request->header('X-Webhook-Secret')
            ?? $request->header('X-Api-Key')
            ?? $request->query('secret');

        $connections = ShippingPartnerConnection::query()->withoutTenant()
            ->where('provider', $provider)
            ->where('is_enabled', true)
            ->get();

        $connection = $connections->first(function (ShippingPartnerConnection $item) use ($providedSecret, $request): bool {
            $expected = $item->webhook_secret;
            if (! filled($expected)) {
                return false;
            }
            if ($providedSecret && hash_equals((string) $expected, (string) $providedSecret)) {
                return true;
            }

            $settings = $item->settings ?? [];
            $header = (string) ($settings['webhook_signature_header'] ?? 'X-Signature');
            $signature = $request->header($header);
            if (! $signature) {
                return false;
            }
            $algo = (string) ($settings['webhook_signature_algorithm'] ?? 'sha256');
            $calculated = hash_hmac($algo, $request->getContent(), (string) $expected);
            return hash_equals($calculated, preg_replace('/^sha256=/i', '', (string) $signature));
        });

        // Chỉ cho phép webhook không secret ở local/testing để hỗ trợ phát triển.
        if (! $connection
            && app()->environment(['local', 'testing'])
            && $connections->count() === 1
            && ! filled($connections->first()->webhook_secret)) {
            $connection = $connections->first();
        }

        if (! $connection) {
            return $this->error(__('messages.shipping.unauthorized'), $connections->isEmpty() ? 503 : 401);
        }

        $event = app(InboundEventRecorder::class)->record(
            $request,
            InboundEventSource::ShippingWebhook,
            $provider,
            null,
            $payload,
        );
        $event->update(['company_id' => $connection->company_id]);
        $event->markQueued();

        ProcessShippingWebhookJob::dispatch($provider, $payload, $event->id, $connection->company_id);

        return $this->success(
            ['queued' => true, 'correlation_id' => $event->correlation_id],
            __('messages.shipping.queued'),
            202,
        );
    }
}
