<?php

namespace App\Services\CustomerInteractions;

use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderOperationHistory;
use Illuminate\Support\Collection;

final class OrderOperationHistoryPresenter
{
    /** @return array<string, mixed> */
    public static function toArray(OrderOperationHistory $history): array
    {
        return [
            'id' => (string) $history->id,
            'action' => $history->action,
            'actionLabel' => self::actionLabel($history->action),
            'actorName' => $history->actor_name ?? $history->actor?->name ?? __('operations.customer_interactions.system_actor'),
            'actorRole' => self::roleLabel($history->actor_role),
            'stageBefore' => self::stageLabel($history->operation_stage_before),
            'stageAfter' => self::stageLabel($history->operation_stage_after),
            'result' => self::resultLabel($history->operation_result),
            'nextOperationAt' => $history->next_operation_at?->toIso8601String(),
            'note' => $history->note,
            'metadata' => $history->metadata ?? [],
            'createdAt' => $history->created_at?->toIso8601String(),
            'synthetic' => false,
        ];
    }

    /**
     * @param  Collection<int, OrderOperationHistory>  $histories
     * @return list<array<string, mixed>>
     */
    public static function collection(Collection $histories): array
    {
        return $histories->map(fn (OrderOperationHistory $history) => self::toArray($history))->values()->all();
    }

    /** @return array<string, mixed> */
    public static function currentSnapshot(Order $order): array
    {
        return [
            'id' => 'snapshot-'.$order->id,
            'action' => OrderOperationHistory::ACTION_INITIAL_SNAPSHOT,
            'actionLabel' => self::actionLabel(OrderOperationHistory::ACTION_INITIAL_SNAPSHOT),
            'actorName' => __('operations.customer_interactions.system_actor'),
            'actorRole' => null,
            'stageBefore' => null,
            'stageAfter' => self::stageLabel($order->operation_stage),
            'result' => self::resultLabel($order->operation_result),
            'nextOperationAt' => $order->next_operation_at?->toIso8601String(),
            'note' => __('operations.customer_interactions.history_before_tracking'),
            'metadata' => [
                'contact_count' => (int) $order->contact_count,
                'order_snapshot' => app(OrderOperationHistoryService::class)->orderSnapshot($order),
            ],
            'createdAt' => ($order->updated_at ?? $order->created_at)?->toIso8601String(),
            'synthetic' => true,
        ];
    }

    public static function actionLabel(string $action): string
    {
        $key = 'operations.customer_interactions.history_actions.'.$action;
        $translated = __($key);

        return $translated === $key ? $action : $translated;
    }

    private static function stageLabel(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return OperationStage::tryFrom($value)?->label() ?? $value;
    }

    private static function resultLabel(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return OperationResult::tryFromStored($value)?->label() ?? $value;
    }

    private static function roleLabel(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return UserRole::tryFrom($value)?->label() ?? $value;
    }
}
