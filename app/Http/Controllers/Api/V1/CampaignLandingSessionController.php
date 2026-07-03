<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadIngestionStatus;
use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Jobs\Leads\FinalizeLandingLeadJob;
use App\Models\LandingSession;
use App\Models\LeadIngestion;
use App\Repositories\MarketingSourceRepository;
use App\Support\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Vòng đời PHIÊN Landing (do JS nhúng trên Ladipage gọi).
 *
 * Mục tiêu: giữ số CHỦ ĐỘNG — biết khi nào khách bắt đầu / còn đang xem trang
 * cảm ơn / đã đóng phiên, để không phải chờ cứng 60–120s cho mọi lead.
 */
class CampaignLandingSessionController extends Controller
{
    use ApiResponds;

    public function __construct(
        protected MarketingSourceRepository $sources,
    ) {}

    /** Khách mở landing → tạo phiên. */
    public function start(Request $request, string $token): JsonResponse
    {
        $campaign = $this->sources->findRootByWebhookToken($token);

        if (! $campaign) {
            return $this->error(__('messages.webhook.landing_not_found'), 404);
        }

        $key = $this->cleanKey($request->input('session_id')) ?? Str::lower(Str::random(32));

        return app(TenantManager::class)->forCompany($campaign->company_id, function () use ($campaign, $key) {
            $session = LandingSession::query()->firstOrCreate(
                ['session_key' => $key],
                [
                    'company_id' => $campaign->company_id,
                    'marketing_source_id' => $campaign->id,
                    'status' => LandingSession::STATUS_OPEN,
                    'last_activity_at' => now(),
                ],
            );

            return $this->success(['session_id' => $session->session_key], 'ok', 202);
        });
    }

    /** Nhịp hoạt động / chuyển sang trang cảm ơn. */
    public function ping(Request $request, string $token): JsonResponse
    {
        $campaign = $this->sources->findRootByWebhookToken($token);

        if (! $campaign) {
            return $this->error(__('messages.webhook.landing_not_found'), 404);
        }

        $key = $this->cleanKey($request->input('session_id'));

        if (! $key) {
            return $this->success(['ok' => true], 'ok', 202);
        }

        app(TenantManager::class)->forCompany($campaign->company_id, function () use ($request, $key, $campaign) {
            $session = LandingSession::query()->where('session_key', $key)->first();

            if (! $session) {
                return;
            }

            $stage = $request->input('stage');
            $session->forceFill([
                'status' => $session->isClosed()
                    ? LandingSession::STATUS_CLOSED
                    : ($stage === 'thankyou' ? LandingSession::STATUS_THANKYOU : $session->status),
                'marketing_source_id' => $session->marketing_source_id ?: $campaign->id,
                'last_activity_at' => now(),
            ])->save();
        });

        return $this->success(['ok' => true], 'ok', 202);
    }

    /** Khách đóng tab / rời trang / hết phân vân → chốt phiên ngay. */
    public function close(Request $request, string $token): JsonResponse
    {
        $campaign = $this->sources->findRootByWebhookToken($token);

        if (! $campaign) {
            return $this->error(__('messages.webhook.landing_not_found'), 404);
        }

        $key = $this->cleanKey($request->input('session_id'));

        if (! $key) {
            return $this->success(['ok' => true], 'ok', 202);
        }

        app(TenantManager::class)->forCompany($campaign->company_id, function () use ($key) {
            $session = LandingSession::query()->where('session_key', $key)->first();

            if (! $session || $session->isClosed()) {
                return;
            }

            $session->forceFill([
                'status' => LandingSession::STATUS_CLOSED,
                'closed_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            // Có lead đang gom → chốt & chia ngay (không chờ hết giờ giữ số).
            if ($session->lead_ingestion_id) {
                $lead = LeadIngestion::query()->find($session->lead_ingestion_id);

                if ($lead && $lead->status === LeadIngestionStatus::Gathering) {
                    FinalizeLandingLeadJob::dispatch($lead->id, $session->company_id);
                }
            }
        });

        return $this->success(['ok' => true], 'ok', 202);
    }

    protected function cleanKey(mixed $key): ?string
    {
        if (! is_scalar($key)) {
            return null;
        }

        $key = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $key) ?? '';

        return $key !== '' ? substr($key, 0, 64) : null;
    }
}
