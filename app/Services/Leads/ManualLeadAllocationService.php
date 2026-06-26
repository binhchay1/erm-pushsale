<?php

namespace App\Services\Leads;

use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Events\LeadPoolChanged;
use App\Events\SaleWorkspaceChanged;
use App\Models\LeadIngestion;
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

        $leads = LeadIngestion::query()
            ->whereIn('id', $leadIds)
            ->where('status', LeadIngestionStatus::Pending)
            ->whereNull('order_id')
            ->get();

        if ($leads->count() !== count($leadIds)) {
            throw ValidationException::withMessages([
                'lead_ids' => __('messages.lead_allocation.invalid_status'),
            ]);
        }

        $allocated = 0;

        DB::transaction(function () use ($leads, $saleUser, &$allocated) {
            foreach ($leads as $lead) {
                $normalized = $this->orderFactory->normalizedFromLead($lead);
                $order = $this->orderFactory->createFromLead($lead, $normalized, $saleUser);

                $lead->update([
                    'status' => LeadIngestionStatus::Processed,
                    'order_id' => $order->id,
                    'processed_at' => now(),
                ]);

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
