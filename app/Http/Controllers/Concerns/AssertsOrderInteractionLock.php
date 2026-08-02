<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Order;
use App\Services\Operations\OrderInteractionLockService;
use Illuminate\Http\Request;

trait AssertsOrderInteractionLock
{
    protected function ensureOrderInteractionLock(Request $request, Order $order, string $action = 'mutate'): string
    {
        $token = $request->input('interaction_lock_token')
            ?? $request->header('X-Interaction-Lock-Token');

        return app(OrderInteractionLockService::class)->assertHeldOrAcquire(
            $order,
            $request->user(),
            is_string($token) ? $token : null,
            $action,
        );
    }

    protected function releaseOrderInteractionLock(Request $request, Order $order, ?string $token = null): void
    {
        $token ??= $request->input('interaction_lock_token')
            ?? $request->header('X-Interaction-Lock-Token');

        app(OrderInteractionLockService::class)->release(
            $order,
            $request->user(),
            is_string($token) ? $token : null,
        );
    }
}
