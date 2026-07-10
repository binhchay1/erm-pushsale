<?php

namespace App\Jobs\Leads;

use App\Models\LeadIngestion;
use App\Services\Leads\LeadIngestionService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Resolve packet upsell đến trước packet chính.
 *
 * Job retry ngắn trong cửa sổ gộp 90 giây. Nếu form chính không bao giờ tới,
 * packet được chuyển sang hàng "cần kiểm tra" thay vì tự tạo/chia một đơn mới.
 */
class FinalizeLandingSupplementPacketJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $leadIngestionId,
        public ?int $companyId = null,
    ) {
        $this->onConnection('redis');
        $this->onQueue(config('saleops.queues.webhooks', 'webhooks'));
    }

    public function handle(LeadIngestionService $service): void
    {
        app(TenantManager::class)->forCompany($this->companyId, function () use ($service): void {
            $packet = LeadIngestion::query()->find($this->leadIngestionId);

            if ($packet) {
                $service->resolvePendingSupplementPacket($packet);
            }
        });
    }
}
