<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Jobs\Leads\ProcessLeadIngestionJob;
use App\Repositories\MarketingSourceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignLandingWebhookController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected MarketingSourceRepository $sources,
    ) {}

    public function receive(Request $request, string $token): JsonResponse
    {
        $campaign = $this->sources->findRootByWebhookToken($token);

        if (! $campaign) {
            return $this->error(__('messages.webhook.landing_not_found'), 404);
        }

        if (! $campaign->is_active) {
            return $this->error(__('messages.webhook.campaign_paused'), 403);
        }

        ProcessLeadIngestionJob::dispatch('landing', $request->all(), $campaign->id, $campaign->company_id);

        return $this->success(
            ['queued' => true],
            __('messages.webhook.landing_queued'),
            202,
        );
    }
}
