<?php

namespace App\Repositories;

use App\Enums\LeadIngestionStatus;
use App\Models\LeadIngestion;
use App\Support\LeadContactMetrics;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeadIngestionRepository
{
    /**
     * @param  array{
     *     platform?: ?string,
     *     status?: ?string,
     *     packet_type?: ?string,
     *     review?: ?string,
     *     bucket?: ?string,
     *     marketing_source_id?: ?int,
     *     marketer_user_id?: ?int,
     *     search?: ?string,
     *     date_from?: ?string,
     *     date_to?: ?string,
     * }  $filters
     */
    public function paginatedLog(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = LeadIngestion::query()
            ->with([
                'order.saleUser:id,name',
                'order.team:id,name',
                'order.items:id,order_id,product_name,quantity,unit_price',
                'order.marketingSource:id,name',
                'order.shipments:id,order_id',
                'relatedOrder.saleUser:id,name',
                'relatedOrder.team:id,name',
                'relatedOrder.items:id,order_id,product_name,quantity,unit_price',
                'relatedOrder.marketingSource:id,name',
                'relatedOrder.shipments:id,order_id',
                'parentIngestion:id,external_id,order_id',
                'marketingSource:id,name',
            ]);

        if (! empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        // Nhóm "ngoại lệ cần kiểm soát": các case hệ thống không tự xử lý được.
        if (($filters['bucket'] ?? null) === 'exceptions') {
            $query->where(function ($exception): void {
                $exception->whereIn('status', self::exceptionStatuses())
                    ->orWhere(fn ($review) => $review->where('requires_review', true)->whereNull('reviewed_at'));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['packet_type'])) {
            $query->where('packet_type', $filters['packet_type']);
        }

        if (($filters['review'] ?? null) === 'pending') {
            $query->where('requires_review', true)->whereNull('reviewed_at');
        } elseif (($filters['review'] ?? null) === 'reviewed') {
            $query->whereNotNull('reviewed_at');
        }

        if (! empty($filters['marketing_source_id'])) {
            $query->where('marketing_source_id', (int) $filters['marketing_source_id']);
        }

        if (! empty($filters['marketer_user_id'])) {
            $marketerId = (int) $filters['marketer_user_id'];
            $query->whereHas('marketingSource', fn ($q) => $q->where('marketer_user_id', $marketerId));
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('customer_phone', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('product_interest', 'like', $term)
                    ->orWhere('external_id', 'like', $term)
                    ->orWhere('packet_type', 'like', $term)
                    ->orWhereHas('order', fn ($order) => $order->where('order_code', 'like', $term))
                    ->orWhereHas('relatedOrder', fn ($order) => $order->where('order_code', 'like', $term));
            });
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /** Danh sách lead cho API. */
    public function paginatedApiList(?string $platform, ?string $status, int $perPage): LengthAwarePaginator
    {
        return LeadIngestion::query()
            ->with(['order', 'relatedOrder', 'parentIngestion:id,external_id,order_id'])
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Trạng thái lead thuộc nhóm "ngoại lệ" — hệ thống không tự xử lý được,
     * cần bộ phận vận hành xem & xử lý tay.
     *
     * @return list<string>
     */
    public static function exceptionStatuses(): array
    {
        return [LeadIngestionStatus::Duplicate->value, LeadIngestionStatus::Failed->value];
    }

    /** Đếm số lead ngoại lệ (trùng số / lỗi) để cảnh báo trên giao diện. */
    public function exceptionCount(): int
    {
        return LeadIngestion::query()
            ->where(function ($outer): void {
                $outer->where(function ($query): void {
                    $query->whereIn('status', [LeadIngestionStatus::Duplicate->value, LeadIngestionStatus::Failed->value])
                        ->where('counts_as_lead', true);
                })->orWhere(function ($review): void {
                    $review->where('requires_review', true)->whereNull('reviewed_at');
                });
            })
            ->count();
    }

    public function countToday(): int
    {
        return LeadContactMetrics::countToday();
    }

    public function countPending(): int
    {
        return LeadIngestion::query()->where('counts_as_lead', true)->where('status', LeadIngestionStatus::Pending)->count();
    }

    /** Top nguồn lead trong ngày (cho biểu đồ). */
    public function todaySourceBreakdown(int $limit = 4): Collection
    {
        return LeadContactMetrics::countableQuery(new \App\Data\ReportFilterData(
            dateFrom: now()->startOfDay(),
            dateTo: now()->endOfDay(),
        ))
            ->selectRaw('platform as name, count(*) as value')
            ->groupBy('platform')
            ->orderByDesc('value')
            ->limit($limit)
            ->get();
    }
}
