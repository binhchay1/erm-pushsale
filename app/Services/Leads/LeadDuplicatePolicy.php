<?php

namespace App\Services\Leads;

use App\Models\LandingConnection;
use App\Models\MarketingSource;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quy tắc trùng số của ERM Pushsale.
 *
 * Pushsale gốc thường coi trùng theo số điện thoại toàn hệ thống, nhưng luồng
 * landing mới cần chặt hơn: cùng khách có thể đi qua hai kết nối landing khác
 * nhau và phải tạo hai đơn độc lập. Chỉ những lần submit lặp lại trong CÙNG
 * kết nối landing/nguồn marketing mới là duplicate lead cần review.
 */
class LeadDuplicatePolicy
{
    public function findDuplicateOrder(string $phone, ?MarketingSource $source, int $windowDays): ?Order
    {
        $query = Order::query()
            ->where('customer_phone', $phone)
            ->where('created_at', '>=', now()->subDays($windowDays));

        $this->applySourceScope($query, $source);

        return $query
            ->latest('id')
            ->first();
    }

    public function countsAsDuplicate(?Order $order): bool
    {
        return $order !== null;
    }

    protected function applySourceScope(Builder $query, ?MarketingSource $source): void
    {
        if (! $source) {
            // Lead nhập tay/không có nguồn vẫn giữ quy tắc cũ để vận hành biết khách đã có đơn gần đây.
            return;
        }

        $connection = $this->landingConnection($source);

        if ($connection) {
            $query->where(function (Builder $scoped) use ($source, $connection): void {
                // Dữ liệu mới có landing_connection_id; dữ liệu cũ có thể chỉ có marketing_source_id.
                $scoped->where('landing_connection_id', $connection->id)
                    ->orWhere(function (Builder $legacy) use ($source): void {
                        $legacy->whereNull('landing_connection_id')
                            ->where('marketing_source_id', $source->id);
                    });
            });

            return;
        }

        // Các nguồn không phải Landing Connection: duplicate chỉ cùng đúng nguồn đó,
        // không chặn một đơn khác từ nguồn/kênh khác của cùng số điện thoại.
        $query->where('marketing_source_id', $source->id);
    }

    protected function landingConnection(MarketingSource $source): ?LandingConnection
    {
        if ($source->relationLoaded('landingConnection')) {
            return $source->landingConnection;
        }

        return $source->landingConnection()->first();
    }
}
