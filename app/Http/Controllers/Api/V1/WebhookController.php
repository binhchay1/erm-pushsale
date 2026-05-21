<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LeadIngestionResource;
use App\Http\Traits\ApiResponds;
use App\Integrations\Facebook\FacebookLeadDriver;
use App\Integrations\IntegrationDriverFactory;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected LeadIngestionService $ingestionService,
    ) {}

    public function handle(Request $request, string $platform): JsonResponse|Response
    {
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

        if (! $driver->verifyWebhook($request)) {
            return $this->error('Webhook không được xác thực', 401);
        }

        $payload = $request->all();
        if ($enum === IntegrationPlatform::Facebook) {
            foreach ($request->input('entry', []) as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if (($change['field'] ?? '') === 'leadgen') {
                        $this->ingestionService->ingest($driver, [
                            'entry' => [['changes' => [$change]]],
                        ]);
                    }
                }
            }

            return $this->success(message: 'Facebook webhook processed');
        }

        $ingestion = $this->ingestionService->ingest($driver, $payload);

        return $this->created(
            new LeadIngestionResource($ingestion->load('order')),
            'Webhook processed'
        );
    }
}
