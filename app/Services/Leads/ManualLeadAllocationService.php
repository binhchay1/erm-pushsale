<?php

namespace App\Services\Leads;

use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Events\LeadPoolChanged;
use App\Events\SaleWorkspaceChanged;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManualLeadAllocationService
{
    public function __construct(
        private readonly LeadOrderFactory $orderFactory,
        private readonly LeadIngestionService $ingestionService,
    ) {}

    /**
     * @param  list<int>  $leadIds
     */
    public function allocate(array $leadIds, User $saleUser, User $actor): int
    {
        if ($saleUser->role !== UserRole::Sales) {
            throw ValidationException::withMessages([
                'sale_user_id' => __('messages.lead_allocation.only_telesale'),
            ]);
        }

        $leadIds = array_values(array_unique(array_map('intval', $leadIds)));

        if ($leadIds === []) {
            throw ValidationException::withMessages([
                'lead_ids' => __('messages.lead_allocation.select_one'),
            ]);
        }

        $allocated = 0;
        $landingOrders = [];

        DB::transaction(function () use ($leadIds, $saleUser, &$allocated, &$landingOrders) {
            // Khoá hàng lead để tránh đua với chia tự động / phiên chia tay khác.
            $leads = LeadIngestion::query()
                ->whereIn('id', $leadIds)
                ->where('counts_as_lead', true)
                ->where('status', LeadIngestionStatus::Pending)
                ->whereNull('order_id')
                ->lockForUpdate()
                ->get();

            // Chỉ cần 1 lead đã bị xử lý (auto-assign / người khác chia) → huỷ cả lô để đồng bộ.
            if ($leads->count() !== count($leadIds)) {
                throw ValidationException::withMessages([
                    'lead_ids' => __('messages.lead_allocation.invalid_status'),
                ]);
            }

            foreach ($leads as $lead) {
                $normalized = $this->orderFactory->normalizedFromLead($lead);
                $order = $this->orderFactory->createFromLead($lead, $normalized, $saleUser);

                $lead->update([
                    'status' => LeadIngestionStatus::Processed,
                    'order_id' => $order->id,
                    'processed_at' => now(),
                ]);

                if ($lead->platform === 'landing' && $lead->marketing_source_id) {
                    $landingOrders[] = [
                        'lead_id' => (int) $lead->id,
                        'order_id' => (int) $order->id,
                        'campaign_id' => (int) $lead->marketing_source_id,
                    ];
                }

                NotificationService::push(
                    $saleUser->id,
                    'lead',
                    null,
                    null,
                    '/sales/workspace',
                    [
                        'variant' => 'manual',
                        'customer_name' => $lead->customer_name,
                        'customer_phone' => $lead->customer_phone,
                    ],
                );

                $allocated++;
            }
        });

        // Gộp/review packet landing sau khi transaction chia số đã commit.
        // Chia tay sau 90 giây không mở lại cửa sổ; packet đến muộn vẫn liên kết
        // đúng order và sale thay vì trở thành một dòng thiếu thông tin.
        foreach ($landingOrders as $link) {
            try {
                $lead = LeadIngestion::query()->find($link['lead_id']);
                $order = Order::query()->find($link['order_id']);
                $campaign = MarketingSource::query()->find($link['campaign_id']);

                if ($lead && $order && $campaign) {
                    $this->ingestionService->reconcileLandingOrder($lead, $order, $campaign);
                }
            } catch (Throwable $e) {
                Log::error('Landing packet reconciliation failed after manual allocation', [
                    ...$link,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($allocated > 0) {
            // Realtime ping — không để lỗi broadcaster làm hỏng việc chia lead.
            try {
                event(new SaleWorkspaceChanged($saleUser->id));
                event(new LeadPoolChanged);
            } catch (Throwable $e) {
                Log::warning('Realtime broadcast failed (allocation)', ['error' => $e->getMessage()]);
            }
        }

        return $allocated;
    }
}
