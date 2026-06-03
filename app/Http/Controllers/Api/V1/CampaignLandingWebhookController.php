<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IntegrationPlatform;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LeadIngestionResource;
use App\Http\Traits\ApiResponds;
use App\Integrations\IntegrationDriverFactory;
use App\Models\MarketingSource;
use App\Services\Leads\LeadIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignLandingWebhookController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected LeadIngestionService $ingestionService,
    ) {}

    public function receive(Request $request, string $token): JsonResponse
    {
        $campaign = MarketingSource::query()
            ->whereNull('parent_id')
            ->where('webhook_token', $token)
            ->first();

        if (! $campaign) {
            return $this->error('Không tìm thấy nguồn Landing / chiến dịch', 404);
        }

        if (! $campaign->is_active) {
            return $this->error('Chiến dịch đã tạm dừng nhận lead', 403);
        }

        $driver = IntegrationDriverFactory::make(IntegrationPlatform::Landing);

        $ingestion = $this->ingestionService->ingestForCampaign(
            $driver,
            $campaign,
            $request->all(),
        );

        return $this->created(
            new LeadIngestionResource($ingestion->load('order')),
            $campaign->is_approved
                ? 'Lead đã nhận và chia số cho Sale'
                : 'Lead đã nhận — chờ Admin duyệt chiến dịch mới chia số Sale',
        );
    }
}
