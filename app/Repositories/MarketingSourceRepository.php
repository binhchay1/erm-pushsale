<?php

namespace App\Repositories;

use App\Enums\DeliveryStatus;
use App\Models\MarketingSource;
use Illuminate\Support\Collection;

class MarketingSourceRepository
{
    /** Chiến dịch gốc của một nhân viên marketing, kèm số đơn + doanh thu. */
    public function ownedCampaignsWithStats(int $userId): Collection
    {
        return MarketingSource::query()
            ->whereNull('parent_id')
            ->where('created_by_user_id', $userId)
            ->with(['product:id,name,sku', 'marketer:id,name'])
            ->withCount('orders')
            ->withSum(['orders as revenue' => function ($q) {
                $q->whereIn('delivery_status', DeliveryStatus::revenueEligible());
            }], 'total')
            ->latest('id')
            ->get();
    }

    /** Tìm chiến dịch gốc theo webhook token (nhận lead từ Landing). */
    public function findRootByWebhookToken(string $token): ?MarketingSource
    {
        return MarketingSource::query()
            ->whereNull('parent_id')
            ->where('webhook_token', $token)
            ->first();
    }

    /** Toàn bộ chiến dịch gốc cho màn duyệt của admin. */
    public function rootCampaignsForApproval(): Collection
    {
        return MarketingSource::query()
            ->whereNull('parent_id')
            ->with(['product:id,name', 'marketer:id,name', 'creator:id,name'])
            ->latest('id')
            ->get();
    }
}
