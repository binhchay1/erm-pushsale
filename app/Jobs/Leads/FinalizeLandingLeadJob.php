<?php

namespace App\Jobs\Leads;

use App\Enums\LeadIngestionStatus;
use App\Models\LandingSession;
use App\Models\LeadIngestion;
use App\Services\Leads\LeadIngestionService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Chốt 1 lead Landing "đang gom" sau thời gian giữ số (chờ upsale trang cảm ơn).
 *
 * Cơ chế giữ số CHỦ ĐỘNG:
 *  - Khách còn thao tác (còn nhịp hoạt động phiên trong hold_seconds) → job tự
 *    gia hạn (re-dispatch) tới trần max_hold_seconds.
 *  - Khách đóng phiên (session.close) → chốt ngay.
 *  - Hết trần → chốt để lead không kẹt.
 */
class FinalizeLandingLeadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $leadId,
        public ?int $companyId = null,
    ) {}

    public function handle(LeadIngestionService $service): void
    {
        app(TenantManager::class)->forCompany($this->companyId, function () use ($service) {
            $lead = LeadIngestion::query()->find($this->leadId);

            if (! $lead || $lead->status !== LeadIngestionStatus::Gathering) {
                return;
            }

            $session = LandingSession::query()
                ->where('lead_ingestion_id', $lead->id)
                ->latest('id')
                ->first();

            $holdSeconds = (int) config('saleops.landing.hold_seconds', 90);
            $maxHold = (int) config('saleops.landing.max_hold_seconds', 300);
            $ageSeconds = $lead->created_at ? now()->diffInSeconds($lead->created_at) : $maxHold;

            $sessionClosed = $session?->isClosed() ?? false;
            $lastActivity = $session?->last_activity_at ?? $lead->updated_at;
            $stillActive = $lastActivity && now()->diffInSeconds($lastActivity) < $holdSeconds;

            // Chưa đóng phiên, còn hoạt động, chưa chạm trần → chờ thêm.
            if (! $sessionClosed && $stillActive && $ageSeconds < $maxHold) {
                self::dispatch($this->leadId, $this->companyId)->delay(now()->addSeconds($holdSeconds));

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
}
