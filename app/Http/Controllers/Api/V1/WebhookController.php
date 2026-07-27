<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InboundEventSource;
use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Concerns\ValidatesIncomingLead;
use App\Http\Controllers\Controller;
use App\Integrations\Facebook\FacebookLeadDriver;
use App\Integrations\IntegrationDriverFactory;
use App\Jobs\Leads\ProcessLeadIngestionJob;
use App\Models\IntegrationConnection;
use App\Models\Scopes\TenantScope;
use App\Services\Inbound\InboundEventRecorder;
use App\Services\Leads\LeadPayloadValidator;
use App\Support\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    use ValidatesIncomingLead;

    public function handle(Request $request, string $platform, ?string $token = null): JsonResponse|Response
    {
        if ($platform === 'ladipage') {
            $platform = IntegrationPlatform::Landing->value;
        }

        $enum = IntegrationPlatform::tryFromWebhookPath($platform);

        if (! $enum) {
            return $this->error(__('messages.webhook.platform_unsupported'), 404);
        }

        $contentLength = (int) ($request->header('Content-Length') ?: strlen($request->getContent()));
        if ($contentLength > (int) config('security.webhook.max_payload_kb', 512) * 1024) {
            return $this->error(__('messages.webhook.payload_too_large'), 413);
        }

        if ($request->isMethod('GET') && $enum === IntegrationPlatform::Facebook) {
            $driver = IntegrationDriverFactory::make($enum);
            $fb = $driver instanceof FacebookLeadDriver ? $driver : new FacebookLeadDriver;
            $challenge = $fb->challengeResponse($request);

            return $challenge
                ? response($challenge, 200)->header('Content-Type', 'text/plain')
                : $this->error(__('messages.webhook.verify_token_invalid'), 403);
        }

        $event = app(InboundEventRecorder::class)->record(
            $request,
            InboundEventSource::LeadWebhook,
            $enum->value,
            null,
            $request->all(),
        );

        $connection = $this->resolveConnection($enum, $token);

        if (! $connection) {
            $event->markRejected(404, __('messages.webhook.platform_unsupported'));

            return $this->error(__('messages.webhook.platform_unsupported'), 404);
        }

        $event->update(['company_id' => $connection->company_id]);

        app(TenantManager::class)->set($connection->company_id);

        $driver = IntegrationDriverFactory::make($enum);

        if (! $connection->is_enabled && ! app()->environment('local')) {
            $event->markRejected(503, __('messages.webhook.platform_disabled'));

            return $this->error(__('messages.webhook.platform_disabled'), 503);
        }

        if (! $driver->verifyWebhook($request)) {
            $event->markRejected(401, __('messages.webhook.unauthorized'));

            return $this->error(__('messages.webhook.unauthorized'), 401);
        }

        if (app(LeadPayloadValidator::class)->requiresSyncValidation($enum)) {
            if ($response = $this->validateIncomingLeadOrError($driver, $request->all(), $event)) {
                return $response;
            }
        }

        $event->markQueued();

        ProcessLeadIngestionJob::dispatch(
            $enum->value,
            $request->all(),
            null,
            $connection->company_id,
            $event->id,
        );

        $message = $enum === IntegrationPlatform::Facebook
            ? __('messages.webhook.facebook_queued')
            : __('messages.webhook.queued');

        return $this->success(['queued' => true, 'correlation_id' => $event->correlation_id], $message, 202);
    }

    private function resolveConnection(IntegrationPlatform $enum, ?string $token): ?IntegrationConnection
    {
        if ($token) {
            return IntegrationConnection::query()
                ->withoutGlobalScope(TenantScope::class)
                ->where('platform', $enum->value)
                ->where('webhook_token', $token)
                ->first();
        }

        $matches = IntegrationConnection::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('platform', $enum->value)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
