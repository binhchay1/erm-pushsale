<?php

namespace App\Services\Operations;

use App\Models\CustomerInternalMessage;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use App\Services\CustomerInteractions\CustomerIdentity;
use App\Services\CustomerInteractions\OrderOperationHistoryService;
use App\Services\Leads\LandingUpsellService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaleOperationNoteService
{
    public function __construct(
        private readonly LandingUpsellService $landingUpsell,
        private readonly OrderOperationHistoryService $history,
    ) {}

    public function update(Order $order, User $actor, ?string $note): Order
    {
        if ($actor->isSales() && (int) $order->sale_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['order' => __('messages.sale_ops.no_permission_operate')]);
        }

        return DB::transaction(function () use ($order, $actor, $note): Order {
            $before = $this->history->snapshot($order);
            $this->landingUpsell->lockFromSaleAction($order);

            $order->update(['sale_operation_note' => filled($note) ? trim((string) $note) : null]);
            $fresh = $order->fresh();

            $noteText = trim((string) ($fresh->sale_operation_note ?? ''));
            if ($noteText !== '') {
                CustomerInternalMessage::query()->create([
                    'company_id' => $fresh->company_id ?? $actor->company_id,
                    'order_id' => $fresh->id,
                    'author_user_id' => $actor->id,
                    'author_name' => $actor->name,
                    'author_role' => $actor->role?->value,
                    'customer_phone' => CustomerIdentity::phoneKey($fresh),
                    'message' => $noteText,
                ]);
            }

            ActivityLogger::log(
                ActivityLogger::ORDER_UPDATED,
                $fresh,
                ['changed_fields' => ['sale_operation_note']],
                $fresh->order_code ?? ('#'.$fresh->id),
                $actor,
            );

            $this->history->record(
                $fresh,
                $actor,
                OrderOperationHistory::ACTION_NOTE_UPDATED,
                $before,
                $this->history->snapshot($fresh),
                $fresh->sale_operation_note,
            );

            return $fresh;
        });
    }
}
