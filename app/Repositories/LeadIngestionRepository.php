<?php

namespace App\Repositories;

use App\Enums\LeadIngestionStatus;
use App\Models\LeadIngestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LeadIngestionRepository
{
    /**
     * @param  array{
     *     platform?: ?string,
     *     status?: ?string,
     *     marketing_source_id?: ?int,
     *     search?: ?string,
     *     date_from?: ?string,
     *     date_to?: ?string,
     * }  $filters
     */
    public function paginatedLog(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = LeadIngestion::query()
            ->with([
                'order:id,order_code,customer_name,sale_user_id',
                'marketingSource:id,name',
            ]);

        if (! empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['marketing_source_id'])) {
            $query->where('marketing_source_id', (int) $filters['marketing_source_id']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('customer_phone', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('product_interest', 'like', $term);
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
            ->with('order')
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function countToday(): int
    {
        return LeadIngestion::query()->where('created_at', '>=', now()->startOfDay())->count();
    }

    public function countPending(): int
    {
        return LeadIngestion::query()->where('status', LeadIngestionStatus::Pending)->count();
    }

    /** Top nguồn lead trong ngày (cho biểu đồ). */
    public function todaySourceBreakdown(int $limit = 4): Collection
    {
        return LeadIngestion::query()
            ->whereDate('created_at', today())
            ->selectRaw('platform as name, count(*) as value')
            ->groupBy('platform')
            ->orderByDesc('value')
            ->limit($limit)
            ->get();
    }
}
