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
 * Đơn đã được tạo và chia sale trước khi job này chạy. Job chỉ đóng cửa sổ
 * gộp đúng tại deadline tuyệt đối (mặc định 90 giây). Heartbeat/session.close
 * dùng để liên kết phiên và quan sát hành vi, không kéo dài hoặc rút ngắn deadline.
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
        $now = now();
        $deadline = $order->landing_upsell_hold_until;

        if (! $deadline) {
            return;
        }

        // Queue sync/test hoặc clock drift có thể làm job chạy sớm. Không đóng sớm:
        // đưa chính job về đúng deadline còn lại.
        if ($deadline->isAfter($now)) {
            self::dispatch($this->leadId, $this->companyId)->delay($deadline);

            return;
        }

        $service->releaseLandingUpsellHold($lead);

        $session = LandingSession::query()
            ->where('lead_ingestion_id', $lead->id)
            ->latest('id')
            ->first();

        if ($session && ! $session->isClosed()) {
            $session->forceFill([
                'status' => LandingSession::STATUS_CLOSED,
                'closed_at' => $now,
                'order_id' => $order->id,
            ])->save();
        }
    }
}

