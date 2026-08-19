<?php

namespace App\Services\Customers;

use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\CustomerInternalMessage;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;
use App\Services\CustomerInteractions\OrderOperationHistoryService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CustomerReallocationService
{
    public function __construct(
        private readonly CustomerPhoneAssignmentService $phoneAssignment,
        private readonly OrderOperationHistoryService $history,
    ) {}

    /**
     * @param  Collection<int, Order>  $orders
     * @param  array{
     *     sale_user_id: int,
     *     hide_locked_sales?: bool,
     *     hide_sales_not_receiving?: bool,
     *     delete_operation_history?: bool,
     *     delete_internal_messages?: bool,
     *     operation_stage?: string|null
     * }  $options
     */
    public function reallocate(Collection $orders, User $actor, array $options): int
    {
        $sale = $this->resolveSale((int) ($options['sale_user_id'] ?? 0), $options);

        $stage = $this->normalizeStage($options['operation_stage'] ?? null);
        $deleteHistory = (bool) ($options['delete_operation_history'] ?? false);
        $deleteMessages = (bool) ($options['delete_internal_messages'] ?? false);
        $count = 0;

        foreach ($orders as $order) {
            $before = $this->history->snapshot($order);

            if (filled($order->customer_phone)) {
                $this->phoneAssignment->attachOrder($order, $sale, 'customer_reallocated_now');
            }

            $order->forceFill([
                'sale_user_id' => $sale->id,
                'team_id' => $sale->team_id,
                'assigned_at' => now(),
            ]);

            if ($stage !== null) {
                $order->operation_stage = $stage;
            }

            $order->save();

            if ($deleteHistory) {
                OrderOperationHistory::query()->where('order_id', $order->id)->delete();
            }

            if ($deleteMessages) {
                CustomerInternalMessage::query()->where('order_id', $order->id)->delete();
            }

            $this->history->record(
                $order,
                $actor,
                'customer_reallocated_now',
                $before,
                $this->history->snapshot($order),
                __('messages.customers.reallocate_history', ['sale' => $sale->name]),
                ['sale_user_id' => $sale->id],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveSale(int $saleUserId, array $options): User
    {
        if ($saleUserId < 1) {
            throw ValidationException::withMessages([
                'sale_user_id' => __('messages.customers.reallocate_sale_required'),
            ]);
        }

        $sale = User::query()
            ->with('operationalProfile:id,user_id,receive_data,is_locked')
            ->where('role', UserRole::Sales->value)
            ->find($saleUserId);

        if (! $sale) {
            throw ValidationException::withMessages([
                'sale_user_id' => __('messages.customers.reallocate_sale_invalid'),
            ]);
        }

        $profile = $sale->operationalProfile;
        $hideLocked = (bool) ($options['hide_locked_sales'] ?? true);
        $hideNotReceiving = (bool) ($options['hide_sales_not_receiving'] ?? true);

        if ($hideLocked && (bool) ($profile?->is_locked ?? false)) {
            throw ValidationException::withMessages([
                'sale_user_id' => __('messages.customers.reallocate_sale_locked'),
            ]);
        }

        if ($hideNotReceiving && $profile?->receive_data === false) {
            throw ValidationException::withMessages([
                'sale_user_id' => __('messages.customers.reallocate_sale_not_receiving'),
            ]);
        }

        return $sale;
    }

    private function normalizeStage(mixed $stage): ?string
    {
        $value = trim((string) $stage);
        if ($value === '' || $value === 'keep') {
            return null;
        }

        $enum = OperationStage::tryFrom($value);

        return $enum?->value;
    }
}
