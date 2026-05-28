<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LeadIngestionResource;
use App\Http\Traits\ApiResponds;
use App\Integrations\Facebook\FacebookLeadDriver;
use App\Integrations\IntegrationDriverFactory;
use App\Models\IntegrationConnection;
use App\Services\Integrations\IntegrationConfigService;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected LeadIngestionService $ingestionService,
        protected IntegrationConfigService $configService,
    ) {}

    public function handle(Request $request, string $platform): JsonResponse|Response
    {
        if ($platform === 'ladipage') {
            $platform = IntegrationPlatform::Landing->value;
        }

        $enum = IntegrationPlatform::tryFromWebhookPath($platform);

        if (! $enum) {
            return $this->error('Nền tảng không hỗ trợ', 404);
        }

        $driver = IntegrationDriverFactory::make($enum);

        if ($request->isMethod('GET') && $enum === IntegrationPlatform::Facebook) {
            $fb = $driver instanceof FacebookLeadDriver ? $driver : new FacebookLeadDriver;
            $challenge = $fb->challengeResponse($request);

            return $challenge
                ? response($challenge, 200)->header('Content-Type', 'text/plain')
                : $this->error('Verify token không hợp lệ', 403);
        }

        $connection = IntegrationConnection::forPlatform($enum);

        if (! $connection->is_enabled && ! app()->environment('local')) {
            return $this->error('Nền tảng chưa được bật trong admin', 503);
        }

        if (! $driver->verifyWebhook($request)) {
            return $this->error('Webhook không được xác thực', 401);
        }

        $processed = 0;

        if ($enum === IntegrationPlatform::Facebook) {
            foreach ($request->input('entry', []) as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if (($change['field'] ?? '') === 'leadgen') {
                        $this->ingestionService->ingest($driver, [
                            'entry' => [['changes' => [$change]]],
                        ]);
                        $processed++;
                    }
                }
            }

            if ($processed > 0) {
                $this->configService->touchSynced($enum);
            }

            return $this->success(['processed' => $processed], 'Facebook webhook processed');
        }

        $ingestion = $this->ingestionService->ingest($driver, $request->all());
        $this->configService->touchSynced($enum);

        return $this->created(
            new LeadIngestionResource($ingestion->load('order')),
            'Webhook processed'
        );
    }
}
