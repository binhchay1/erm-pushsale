<?php

namespace App\Services\Operations;

use App\Enums\ClosingStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Models\Order;
use App\Models\User;
use App\Services\Inventory\InventoryDeductionService;
use App\Services\Orders\OrderClosingService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SaleOperationStatusService
{
    public function __construct(
        private readonly OrderClosingService $closing,
        private readonly InventoryDeductionService $inventory,
    ) {}

    public function logCall(Order $order, User $actor): Order
    {
        $this->assertCanAct($order, $actor);

        if (! SaleOperationPolicy::canCall($order)) {
            throw ValidationException::withMessages([
                'order' => 'Đơn này không thể gọi (đã chốt, hủy hoặc thiếu SĐT).',
            ]);
        }

        $order->update([
            'contact_count' => (int) $order->contact_count + 1,
        ]);

        return $order->fresh();
    }

    /**
     * @param  array{operation_result: string, next_operation_at?: string|null, note?: string|null}  $payload
     */
    public function applyStatus(Order $order, User $actor, array $payload): Order
    {
        $this->assertCanAct($order, $actor);

        if (! SaleOperationPolicy::canChangeStatus($order)) {
            throw ValidationException::withMessages([
                'order' => 'Đơn đã chốt hoặc đã kết thúc tác nghiệp — không thể đổi trạng thái.',
            ]);
        }

        $resultValue = $payload['operation_result'];

        if ($resultValue === 'no_answer_auto') {
            $currentStage = OperationStage::tryFrom($order->operation_stage);
            $result = OperationResult::noAnswerForStage($currentStage);
        } else {
            $result = OperationResult::tryFrom($resultValue);
        }

        if (! $result) {
            throw ValidationException::withMessages([
                'operation_result' => 'Kết quả tác nghiệp không hợp lệ.',
            ]);
        }

        if ($result === OperationResult::CallbackScheduled && empty($payload['next_operation_at'])) {
            throw ValidationException::withMessages([
                'next_operation_at' => 'Vui lòng chọn thời gian hẹn gọi lại.',
            ]);
        }

        if ($result === OperationResult::ClosedSuccess) {
            $confirm = (bool) ($payload['confirm_insufficient_stock'] ?? false);
            $this->inventory->assertCanClose($order, $confirm);

            return $this->closing->close($order, $actor, [
                'operation_result' => $result->value,
                'confirm_insufficient_stock' => $confirm,
            ]);
        }

        $nextStage = $this->resolveNextStage($order, $result);
        $nextOperationAt = ! empty($payload['next_operation_at'])
            ? Carbon::parse($payload['next_operation_at'])
            : null;

        $updates = [
            'operation_result' => $result->value,
            'operation_stage' => $nextStage->value,
            'next_operation_at' => $nextOperationAt,
        ];

        if ($result->isTerminal()) {
            $updates['closing_status'] = ClosingStatus::Cancelled->value;
        } elseif ($order->closing_status === null) {
            $updates['closing_status'] = ClosingStatus::Open->value;
        }

        if (! empty($payload['note'])) {
            $updates['customer_note'] = trim($order->customer_note."\n".$payload['note']);
        }

        $order->update($updates);

        return $order->fresh(['items', 'saleUser', 'team', 'marketingSource', 'warehouse']);
    }

    private function resolveNextStage(Order $order, OperationResult $result): OperationStage
    {
        if (str_starts_with($result->value, 'no_answer_')) {
            return $result->nextStage();
        }

        if (in_array($result, [OperationResult::Considering, OperationResult::SentQuote, OperationResult::CallbackScheduled], true)) {
            $current = OperationStage::tryFrom($order->operation_stage) ?? OperationStage::NewCustomer;

            return $this->advanceCallStage($current);
        }

        return $result->nextStage();
    }

    private function advanceCallStage(OperationStage $current): OperationStage
    {
        $sequence = [
            OperationStage::NewCustomer,
            OperationStage::Call2,
            OperationStage::Call3,
            OperationStage::Call4,
            OperationStage::Call5,
            OperationStage::Call6,
        ];

        $index = array_search($current, $sequence, true);

        if ($index === false) {
            return $current;
        }

        return $sequence[min($index + 1, count($sequence) - 1)];
    }

    private function assertCanAct(Order $order, User $actor): void
    {
        if ($actor->isSales() && $order->sale_user_id !== $actor->id) {
            throw ValidationException::withMessages([
                'order' => 'Bạn không có quyền tác nghiệp đơn này.',
            ]);
        }
    }
}
