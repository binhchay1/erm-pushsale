<?php

namespace App\Repositories;

use App\Models\ShippingWebhookEvent;
use Illuminate\Support\Collection;

class ShippingWebhookEventRepository
{
    /** @return array{callbacks_today: int, matched_today: int, unmatched_today: int, cod_mismatch_today: int} */
    public function todayStats(): array
    {
        $today = now()->startOfDay();
        $base = fn () => ShippingWebhookEvent::query()->where('created_at', '>=', $today);

        return [
            'callbacks_today' => $base()->count(),
            'matched_today' => $base()->whereNotNull('order_id')->count(),
            'unmatched_today' => $base()->whereNull('order_id')->count(),
            'cod_mismatch_today' => $base()->where('is_cod_mismatch', true)->count(),
        ];
    }

    /** Sự kiện cần xử lý: chưa khớp đơn hoặc lệch COD. */
    public function latestIssues(int $limit = 100): Collection
    {
        return ShippingWebhookEvent::query()
            ->with('order:id,order_code,delivery_status,reconciliation_status')
            ->where(function ($q) {
                $q->whereNull('order_id')->orWhere('is_cod_mismatch', true);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function codMismatchTotal(): int
    {
        return ShippingWebhookEvent::query()->where('is_cod_mismatch', true)->count();
    }
}
