<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Integrations\Facebook\FacebookLeadDriver;
use App\Integrations\IntegrationDriverFactory;
use App\Jobs\Leads\ProcessLeadIngestionJob;
use App\Models\IntegrationConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    use ApiResponds;

    public function handle(Request $request, string $platform): JsonResponse|Response
    {
        if ($platform === 'ladipage') {
            $platform = IntegrationPlatform::Landing->value;
        }

        $enum = IntegrationPlatform::tryFromWebhookPath($platform);

        if (! $enum) {
            return $this->error(__('messages.webhook.platform_unsupported'), 404);
        }

        $driver = IntegrationDriverFactory::make($enum);

        if ($request->isMethod('GET') && $enum === IntegrationPlatform::Facebook) {
            $fb = $driver instanceof FacebookLeadDriver ? $driver : new FacebookLeadDriver;
            $challenge = $fb->challengeResponse($request);

            return $challenge
                ? response($challenge, 200)->header('Content-Type', 'text/plain')
                : $this->error(__('messages.webhook.verify_token_invalid'), 403);
        }

        $connection = IntegrationConnection::forPlatform($enum);

        if (! $connection->is_enabled && ! app()->environment('local')) {
            return $this->error(__('messages.webhook.platform_disabled'), 503);
        }

        if (! $driver->verifyWebhook($request)) {
            return $this->error(__('messages.webhook.unauthorized'), 401);
        }

        ProcessLeadIngestionJob::dispatch($enum->value, $request->all());

        $message = $enum === IntegrationPlatform::Facebook
            ? __('messages.webhook.facebook_queued')
            : __('messages.webhook.queued');

        return $this->success(['queued' => true], $message, 202);
    }
}
