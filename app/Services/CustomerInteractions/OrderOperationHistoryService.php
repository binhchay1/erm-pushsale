<?php

namespace App\Services\CustomerInteractions;

use App\Models\Order;
use App\Models\OrderOperationHistory;
use App\Models\User;

final class OrderOperationHistoryService
{
    /** @return array<string, mixed> */
    public function snapshot(Order $order): array
    {
        return [
            'operation_stage' => $order->operation_stage,
            'operation_result' => $order->operation_result,
            'next_operation_at' => $order->next_operation_at,
            'contact_count' => (int) $order->contact_count,
            'closing_status' => $order->closing_status,
            'closed_at' => $order->closed_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Order $order,
        User $actor,
        string $action,
        array $before,
        array $after,
        ?string $note = null,
        array $metadata = [],
    ): OrderOperationHistory {
        return OrderOperationHistory::query()->create([
            'company_id' => $order->company_id ?? $actor->company_id,
            'order_id' => $order->id,
            'actor_user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role?->value,
            'action' => $action,
            'operation_stage_before' => $before['operation_stage'] ?? null,
            'operation_stage_after' => $after['operation_stage'] ?? null,
            'operation_result' => $after['operation_result'] ?? null,
            'next_operation_at' => $after['next_operation_at'] ?? null,
            'note' => filled($note) ? trim((string) $note) : null,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => now(),
        ]);
    }
}
