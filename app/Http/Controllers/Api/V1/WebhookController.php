<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Integrations\Facebook\FacebookLeadDriver;
use App\Integrations\IntegrationDriverFactory;
use App\Jobs\Leads\ProcessLeadIngestionJob;
use App\Models\IntegrationConnection;
use App\Models\Scopes\TenantScope;
use App\Support\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    use ApiResponds;

    public function handle(Request $request, string $platform, ?string $token = null): JsonResponse|Response
    {
        if ($platform === 'ladipage') {
            $platform = IntegrationPlatform::Landing->value;
        }

        $enum = IntegrationPlatform::tryFromWebhookPath($platform);

        if (! $enum) {
            return $this->error(__('messages.webhook.platform_unsupported'), 404);
        }

        $connection = $this->resolveConnection($enum, $token);

        if (! $connection) {
            return $this->error(__('messages.webhook.platform_unsupported'), 404);
        }

        // Gắn ngữ cảnh công ty để driver/forPlatform đọc đúng kết nối của doanh nghiệp.
        app(TenantManager::class)->set($connection->company_id);

        $driver = IntegrationDriverFactory::make($enum);

        if ($request->isMethod('GET') && $enum === IntegrationPlatform::Facebook) {
            $fb = $driver instanceof FacebookLeadDriver ? $driver : new FacebookLeadDriver;
            $challenge = $fb->challengeResponse($request);

            return $challenge
                ? response($challenge, 200)->header('Content-Type', 'text/plain')
                : $this->error(__('messages.webhook.verify_token_invalid'), 403);
        }

        if (! $connection->is_enabled && ! app()->environment('local')) {
            return $this->error(__('messages.webhook.platform_disabled'), 503);
        }

        if (! $driver->verifyWebhook($request)) {
            return $this->error(__('messages.webhook.unauthorized'), 401);
        }

        ProcessLeadIngestionJob::dispatch($enum->value, $request->all(), null, $connection->company_id);

        $message = $enum === IntegrationPlatform::Facebook
            ? __('messages.webhook.facebook_queued')
            : __('messages.webhook.queued');

        return $this->success(['queued' => true], $message, 202);
    }

    /**
     * Webhook không có phiên đăng nhập → tìm kết nối theo token (đa doanh nghiệp)
     * hoặc tự suy ra khi chỉ có duy nhất 1 doanh nghiệp cấu hình nền tảng đó.
     */
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
