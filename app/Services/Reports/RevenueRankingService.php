<?php

namespace App\Services\Reports;

use App\Enums\RankingDepartment;
use App\Enums\RankingPeriod;
use App\Models\User;

/**
 * Xếp hạng doanh số chốt theo từng ban (Telesale / Marketing) trong kỳ.
 *
 * Doanh số = tổng `total` của các đơn đã chốt (closed_at) thuộc kỳ,
 * quy về nhân sự qua cột phụ trách của ban tương ứng.
 */
class RevenueRankingService
{
    private const TOP_LIMIT = 50;

    /**
     * Bảng xếp hạng cho tất cả ban có áp dụng.
     *
     * @return list<array<string, mixed>>
     */
    public function build(RankingPeriod $period): array
    {
        return collect(RankingDepartment::cases())
            ->map(fn (RankingDepartment $dept) => $this->forDepartment($dept, $period))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forDepartment(RankingDepartment $dept, RankingPeriod $period, int $limit = self::TOP_LIMIT): array
    {
        [$start, $end] = $period->range();
        $column = $dept->orderColumn();

        $rows = User::query()
            ->where('users.role', $dept->role()->value)
            ->leftJoin('orders', function ($join) use ($column, $start, $end) {
                $join->on('orders.'.$column, '=', 'users.id')
                    ->whereNotNull('orders.closed_at')
                    ->whereBetween('orders.closed_at', [$start, $end]);
            })
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->groupBy('users.id', 'users.name', 'users.email', 'teams.name')
            ->select('users.id', 'users.name', 'users.email', 'teams.name as team_name')
            ->selectRaw('count(orders.id) as orders_count')
            ->selectRaw('coalesce(sum(orders.total), 0) as revenue')
            ->orderByDesc('revenue')
            ->orderByDesc('orders_count')
            ->orderBy('users.name')
            ->limit($limit)
            ->get()
            ->values()
            ->map(fn (User $user, int $index) => [
                'rank' => $index + 1,
                'id' => $user->id,
                'name' => $user->name,
                'username' => strstr((string) $user->email, '@', true) ?: $user->email,
                'team' => $user->team_name,
                'orders' => (int) $user->orders_count,
                'revenue' => (int) $user->revenue,
            ])
            ->all();

        return [
            'key' => $dept->value,
            'label' => $dept->label(),
            'items' => $rows,
        ];
    }
}
