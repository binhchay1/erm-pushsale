<?php

namespace App\Http\Controllers\Api\V1\Pancake;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Jobs\Pancake\ProcessPancakeMessageWebhookJob;
use App\Models\IntegrationConnection;
use App\Models\Scopes\TenantScope;
use App\Services\Inbound\InboundEventRecorder;
use App\Enums\InboundEventSource;
use App\Support\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PancakeMessageWebhookController extends Controller
{
    public function __invoke(Request $request, string $token): JsonResponse
    {
        if ($request->getContentLength() > (int) config('security.webhook.max_payload_kb', 512) * 1024) {
            return response()->json([
                'message' => __('messages.webhook.payload_too_large'),
            ], 413);
        }

        $connection = IntegrationConnection::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('platform', IntegrationPlatform::Pancake->value)
            ->where('webhook_token', $token)
            ->first();

        if (! $connection) {
            return response()->json([
                'message' => __('messages.webhook.platform_unsupported'),
            ], 404);
        }

        app(TenantManager::class)->set($connection->company_id);

        $event = app(InboundEventRecorder::class)->record(
            $request,
            InboundEventSource::PancakeChatWebhook,
            IntegrationPlatform::Pancake->value,
            null,
            $request->all(),
        );
        $event->update(['company_id' => $connection->company_id]);

        if (! $connection->is_enabled && ! app()->environment('local')) {
            $event->markRejected(503, __('messages.webhook.platform_disabled'));

            return response()->json([
                'message' => __('messages.webhook.platform_disabled'),
            ], 503);
        }

        if (! $this->verifySignature($request, $connection)) {
            $event->markRejected(401, __('messages.webhook.unauthorized'));

            return response()->json([
                'message' => __('messages.webhook.unauthorized'),
            ], 401);
        }

        $event->markQueued();

        ProcessPancakeMessageWebhookJob::dispatch(
            connectionId: $connection->id,
            payload: $request->all(),
            correlationId: $event->correlation_id,
        );

        return response()->json([
            'queued' => true,
            'correlation_id' => $event->correlation_id,
        ], 202);
    }

    private function verifySignature(Request $request, IntegrationConnection $connection): bool
    {
        $secret = $connection->webhook_secret
            ?: config('integrations.webhook.global_secret');

        // URL token is the baseline authentication. If no secret is configured,
        // accept the tokened endpoint so Pancake installations that cannot sign
        // webhooks still work.
        if (! filled($secret)) {
            return true;
        }

        $timestamp = $request->header('X-SaleOps-Timestamp')
            ?? $request->header('X-Pancake-Timestamp')
            ?? $request->header('X-Webhook-Timestamp');

        if ($timestamp && abs(now()->timestamp - (int) $timestamp) > (int) config('integrations.webhook.tolerance_seconds', 300)) {
            return false;
        }

        $signature = $request->header('X-SaleOps-Signature')
            ?? $request->header('X-Pancake-Signature')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Webhook-Signature');

        $apiKey = $request->header('X-Api-Key')
            ?? $request->bearerToken()
            ?? $request->query('api_key');

        if ($apiKey && hash_equals((string) $secret, (string) $apiKey)) {
            return true;
        }

        if (! $signature) {
            return false;
        }

        $raw = $request->getContent();
        $payloads = [$raw];
        if ($timestamp) {
            $payloads[] = $timestamp.'.'.$raw;
        }

        foreach ($payloads as $payload) {
            $expected = hash_hmac('sha256', $payload, (string) $secret);
            if (hash_equals($expected, (string) $signature)
                || hash_equals('sha256='.$expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }
}
