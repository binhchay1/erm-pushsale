<?php

namespace App\Repositories;

use App\Enums\LeadIngestionStatus;
use App\Models\LeadIngestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LeadIngestionRepository
{
    /** Nhật ký lead về, lọc theo nền tảng / trạng thái. */
    public function paginatedLog(?string $platform, ?string $status, int $perPage = 25): LengthAwarePaginator
    {
        return LeadIngestion::query()
            ->with('order:id,order_code,customer_name,sale_user_id')
            ->when($platform, fn ($q) => $q->where('platform', $platform))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Danh sách lead cho API. */
    public function paginatedApiList(?string $platform, ?string $status, int $perPage): LengthAwarePaginator
    {
        return LeadIngestion::query()
            ->with('order')
            ->when($platform, fn ($q, $p) => $q->where('platform', $p))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
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
