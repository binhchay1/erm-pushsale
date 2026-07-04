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
 * Áp dụng cho CẢ luồng có JS lẫn không JS (cùng mốc hold_seconds / max_hold_seconds):
 *  - Còn hoạt động (nhịp phiên JS, hoặc vừa có gói mới trong hold_seconds) → job tự
 *    gia hạn (re-dispatch) tới trần max_hold_seconds.
 *  - Khách đóng phiên (session.close, chỉ có khi gắn JS) → chốt ngay.
 *  - Hết giờ chờ / chạm trần → chốt để lead không kẹt.
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

            $now = now();
            $holdSeconds = (int) config('saleops.landing.hold_seconds', 90);
            $maxHold = (int) config('saleops.landing.max_hold_seconds', 300);
            // diffInSeconds theo thứ tự (mốc_cũ)->diffInSeconds(now) để luôn ra số DƯƠNG
            // trên cả Carbon 2 & 3 (Carbon 3 trả giá trị có dấu).
            $ageSeconds = $lead->created_at ? $lead->created_at->diffInSeconds($now) : $maxHold;

            $sessionClosed = $session?->isClosed() ?? false;
            $lastActivity = $session?->last_activity_at ?? $lead->updated_at;
            $idleSeconds = $lastActivity ? $lastActivity->diffInSeconds($now) : $holdSeconds;
            $stillActive = $idleSeconds < $holdSeconds;

            // Job chạy SỚM hơn lịch (queue sync khi chạy test/seed) → chốt luôn, không
            // gia hạn để tránh lặp vô hạn. Trên production (queue thật) delay được tôn
            // trọng nên age luôn ≥ hold khi job chạy.
            $delayHonored = $ageSeconds >= max(1, $holdSeconds - 5);

            // Chưa đóng phiên, còn hoạt động, chưa chạm trần → chờ thêm.
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
}
