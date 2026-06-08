<?php

namespace App\Services\Leads;

use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                'sale_user_id' => 'Chỉ được phân bổ cho nhân viên telesale.',
            ]);
        }

        $leadIds = array_values(array_unique(array_map('intval', $leadIds)));

        if ($leadIds === []) {
            throw ValidationException::withMessages([
                'lead_ids' => 'Chọn ít nhất một lead.',
            ]);
        }

        $leads = LeadIngestion::query()
            ->whereIn('id', $leadIds)
            ->where('status', LeadIngestionStatus::Pending)
            ->whereNull('order_id')
            ->get();

        if ($leads->count() !== count($leadIds)) {
            throw ValidationException::withMessages([
                'lead_ids' => 'Một hoặc nhiều lead không ở trạng thái Chờ xử lý hoặc đã được phân bổ.',
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
                    'Lead được phân thủ công',
                    trim(($lead->customer_name ?? 'Khách').' · '.($lead->customer_phone ?? '')),
                    '/sales/workspace',
                );

                $allocated++;
            }
        });

        return $allocated;
    }
}
