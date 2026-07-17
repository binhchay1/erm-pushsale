<?php

namespace App\Services\Reports;

use App\Data\RankingFilterData;
use App\Enums\ClosingStatus;
use App\Enums\DiscountMode;
use App\Enums\RankingDepartment;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Xếp hạng doanh số chốt theo từng ban (Telesale / Marketing).
 */
class RevenueRankingService
{
    private const TOP_LIMIT = 50;

    private const CHART_LIMIT = 15;

    /**
     * @return list<array<string, mixed>>
     */
    public function buildForRole(UserRole $role, RankingFilterData $filter): array
    {
        $department = RankingDepartment::forUserRole($role);

        if ($department === null) {
            return [];
        }

        return [$this->forDepartment($department, $filter)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function build(RankingFilterData $filter): array
    {
        return collect(RankingDepartment::cases())
            ->map(fn (RankingDepartment $dept) => $this->forDepartment($dept, $filter))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forDepartment(RankingDepartment $dept, RankingFilterData $filter, int $limit = self::TOP_LIMIT): array
    {
        $rows = $this->queryDepartment($dept, $filter, $limit)
            ->get()
            ->values()
            ->map(fn (User $user, int $index) => $this->presentRow($user, $index + 1))
            ->all();

        return [
            'key' => $dept->value,
            'label' => $dept->label(),
            'items' => $rows,
            'chartItems' => array_slice($rows, 0, self::CHART_LIMIT),
        ];
    }

    /** @return Builder<User> */
    private function queryDepartment(RankingDepartment $dept, RankingFilterData $filter, int $limit): Builder
    {
        $column = $dept->orderColumn();
        $revenueSql = $this->revenueExpression($filter->discountMode);

        return User::query()
            ->where('users.role', $dept->role()->value)
            ->when($filter->teamId, fn (Builder $q) => $q->where('users.team_id', $filter->teamId))
            ->when($filter->teamLeaderId, fn (Builder $q) => $q->where('users.manager_user_id', $filter->teamLeaderId))
            ->leftJoin('orders', function ($join) use ($column, $filter) {
                $join->on('orders.'.$column, '=', 'users.id')
                    ->where(function ($closed): void {
                        $closed->whereNotNull('orders.closed_at')
                            ->orWhere('orders.closing_status', ClosingStatus::Closed->value);
                    })
                    ->where(function ($dated) use ($filter): void {
                        $dated->whereBetween('orders.closed_at', [$filter->dateFrom, $filter->dateTo])
                            ->orWhere(function ($legacy) use ($filter): void {
                                $legacy->whereNull('orders.closed_at')
                                    ->where('orders.closing_status', ClosingStatus::Closed->value)
                                    ->whereBetween('orders.data_arrived_at', [$filter->dateFrom, $filter->dateTo]);
                            })
                            ->orWhere(function ($legacy) use ($filter): void {
                                $legacy->whereNull('orders.closed_at')
                                    ->where('orders.closing_status', ClosingStatus::Closed->value)
                                    ->whereNull('orders.data_arrived_at')
                                    ->whereBetween('orders.updated_at', [$filter->dateFrom, $filter->dateTo]);
                            });
                    });

                if ($filter->operationStage) {
                    $join->where('orders.operation_stage', $filter->operationStage);
                }
            })
            ->leftJoin('teams', 'teams.id', '=', 'users.team_id')
            ->groupBy('users.id', 'users.name', 'users.email', 'teams.name')
            ->select('users.id', 'users.name', 'users.email', 'teams.name as team_name')
            ->selectRaw('count(orders.id) as orders_count')
            ->selectRaw("{$revenueSql} as revenue")
            ->orderByDesc('revenue')
            ->orderByDesc('orders_count')
            ->orderBy('users.name')
            ->limit($limit);
    }

    private function revenueExpression(DiscountMode $mode): string
    {
        return match ($mode) {
            DiscountMode::BeforeDiscount => 'coalesce(sum(orders.subtotal), 0)',
            DiscountMode::AfterDiscount => 'coalesce(sum(orders.total - orders.discount), 0)',
        };
    }

    /** @return array<string, mixed> */
    private function presentRow(User $user, int $rank): array
    {
        $orders = (int) $user->orders_count;
        $revenue = (int) $user->revenue;

        return [
            'rank' => $rank,
            'id' => $user->id,
            'name' => $user->name,
            'initials' => $this->initials($user->name),
            'username' => strstr((string) $user->email, '@', true) ?: $user->email,
            'team' => $user->team_name,
            'orders' => $orders,
            'revenue' => $revenue,
            'avgOrderValue' => $orders > 0 ? (int) round($revenue / $orders) : 0,
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? 'N', 0, 1);
        $last = mb_substr($parts[count($parts) - 1] ?? '', 0, 1);

        return mb_strtoupper($first.$last);
    }
}
