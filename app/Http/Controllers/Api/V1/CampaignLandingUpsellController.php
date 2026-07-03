<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InboundEventSource;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Jobs\Leads\ProcessLeadIngestionJob;
use App\Repositories\MarketingSourceRepository;
use App\Services\Inbound\InboundEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Nhận upsale từ TRANG CẢM ƠN của Ladipage.
 *
 * Khác với /receive: dữ liệu ở đây được CỘNG THÊM vào đơn đã tạo trước đó của
 * cùng khách (theo mã đơn hoặc SĐT) thay vì tạo đơn mới → tránh trùng số khi chia.
 */
class CampaignLandingUpsellController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected MarketingSourceRepository $sources,
    ) {}

    public function receive(Request $request, string $token): JsonResponse
    {
        $event = app(InboundEventRecorder::class)->record(
            $request,
            InboundEventSource::LandingWebhook,
            'landing-upsell',
            null,
            $request->all(),
        );

        $campaign = $this->sources->findRootByWebhookToken($token);

        if (! $campaign) {
            $event->markRejected(404, __('messages.webhook.landing_not_found'));

            return $this->error(__('messages.webhook.landing_not_found'), 404);
        }

        $event->update(['company_id' => $campaign->company_id, 'channel' => $campaign->name.' (upsale)']);

        if (! $campaign->is_active) {
            $event->markRejected(403, __('messages.webhook.campaign_paused'));

            return $this->error(__('messages.webhook.campaign_paused'), 403);
        }

        $event->markQueued();

        ProcessLeadIngestionJob::dispatch(
            'landing',
            $request->all(),
            $campaign->id,
            $campaign->company_id,
            $event->id,
            true,
        );

        return $this->success(
            ['queued' => true, 'correlation_id' => $event->correlation_id],
            __('messages.webhook.landing_queued'),
            202,
        );
    }
}
