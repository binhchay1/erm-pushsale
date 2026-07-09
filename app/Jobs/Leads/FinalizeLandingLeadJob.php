<?php

namespace App\Jobs\Leads;

use App\Enums\LeadIngestionStatus;
use App\Models\LandingSession;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Services\Leads\LandingUpsellService;
use App\Services\Leads\LeadIngestionService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kết thúc cửa sổ upsale trang cảm ơn sau khi đơn đã được tạo & chia số.
 *
 * Áp dụng cho CẢ luồng có JS lẫn không JS (cùng mốc hold_seconds / max_hold_seconds):
 *  - Còn hoạt động (nhịp phiên JS, hoặc vừa có gói mới trong hold_seconds) → job tự
 *    gia hạn (re-dispatch) tới trần max_hold_seconds.
 *  - Khách đóng phiên (session.close, chỉ có khi gắn JS) → chốt ngay.
 *  - Hết giờ chờ / chạm trần → bỏ trạng thái "chờ upsale" trên đơn.
 */
class FinalizeLandingLeadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $leadId,
        public ?int $companyId = null,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.webhooks', 'webhooks'));
    }

    public function handle(LeadIngestionService $service, LandingUpsellService $landingUpsell): void
    {
        app(TenantManager::class)->forCompany($this->companyId, function () use ($service, $landingUpsell) {
            $lead = LeadIngestion::query()->find($this->leadId);

            if (! $lead) {
                return;
            }

            $order = $lead->order_id ? Order::query()->find($lead->order_id) : null;

            if ($order?->landing_upsell_hold_until) {
                $this->finalizeOrderHold($lead, $order, $service, $landingUpsell);

                return;
            }

            // Lead chia tay chờ pool — không tự tạo đơn.
            if ($lead->status === LeadIngestionStatus::Pending && ! $lead->order_id) {
                return;
            }

            if ($lead->status !== LeadIngestionStatus::Gathering) {
                return;
            }

            $session = LandingSession::query()
                ->where('lead_ingestion_id', $lead->id)
                ->latest('id')
                ->first();

            $now = now();
            $holdSeconds = $landingUpsell->holdSeconds();
            $maxHold = $landingUpsell->maxHoldSeconds();
            $ageSeconds = $lead->created_at ? $lead->created_at->diffInSeconds($now) : $maxHold;

            $sessionClosed = $session?->isClosed() ?? false;
            $lastActivity = $session?->last_activity_at ?? $lead->updated_at;
            $idleSeconds = $lastActivity ? $lastActivity->diffInSeconds($now) : $holdSeconds;
            $stillActive = $idleSeconds < $holdSeconds;
            $delayHonored = $ageSeconds >= max(1, $holdSeconds - 5);

            if (! $delayHonored) {
                return;
            }

            if (! $sessionClosed && $stillActive && $ageSeconds < $maxHold && $delayHonored) {
                self::dispatch($this->leadId, $this->companyId)->delay($now->copy()->addSeconds($holdSeconds));

                return;
            }

            $service->finalizeGatheringLead($lead);

            if ($session && ! $session->isClosed()) {
                $session->forceFill([
                    'status' => LandingSession::STATUS_CLOSED,
                    'closed_at' => now(),
                    'order_id' => $lead->fresh()->order_id,
                ])->save();
            }
        });
    }

    private function finalizeOrderHold(
        LeadIngestion $lead,
        Order $order,
        LeadIngestionService $service,
        LandingUpsellService $landingUpsell,
    ): void {
        $session = LandingSession::query()
            ->where('lead_ingestion_id', $lead->id)
            ->latest('id')
            ->first();

        $now = now();
        $holdSeconds = $landingUpsell->holdSeconds();
        $maxHold = $landingUpsell->maxHoldSeconds();
        $ageSeconds = $lead->created_at ? $lead->created_at->diffInSeconds($now) : $maxHold;

        $sessionClosed = $session?->isClosed() ?? false;
        $lastActivity = $session?->last_activity_at ?? $order->updated_at ?? $lead->updated_at;
        $idleSeconds = $lastActivity ? $lastActivity->diffInSeconds($now) : $holdSeconds;
        $stillActive = $idleSeconds < $holdSeconds;
        $delayHonored = $ageSeconds >= max(1, $holdSeconds - 5);

        // Queue sync (test/local): job có thể chạy trước delay → không làm gì, chờ lần chạy đúng hạn.
        if (! $delayHonored) {
            return;
        }

        if (! $sessionClosed && $stillActive && $ageSeconds < $maxHold) {
            self::dispatch($this->leadId, $this->companyId)->delay($now->copy()->addSeconds($holdSeconds));

            return;
        }

        $service->releaseLandingUpsellHold($lead);

        if ($session && ! $session->isClosed()) {
            $session->forceFill([
                'status' => LandingSession::STATUS_CLOSED,
                'closed_at' => now(),
                'order_id' => $order->id,
            ])->save();
        }
    }
}
