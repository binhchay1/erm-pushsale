<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Biểu đồ thống kê theo khung giờ (0h–23h).
 *
 * Đo lượng contact về theo giờ (data_arrived_at) và số đơn chốt + doanh số
 * theo giờ chốt (closed_at) để biết khung giờ nào hiệu quả nhất — phục vụ điều
 * phối lịch trực & gọi. Báo cáo nhẹ (chỉ đếm theo giờ) nên chạy realtime.
 */
class HourlyStatsService
{
    public function __construct(
        private readonly ReportScopeResolver $scope,
    ) {}

    /** @return array{rows: list<array<string, mixed>>, totals: array<string, int>, peak: array<string, int|null>} */
    public function build(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetch($user, $filter);

        $contacts = array_fill(0, 24, 0);
        $closed = array_fill(0, 24, 0);
        $revenue = array_fill(0, 24, 0);

        foreach ($orders as $order) {
            if ($order->data_arrived_at) {
                $contacts[(int) $order->data_arrived_at->format('G')]++;
            }

            if ((string) $order->closing_status === 'closed' && $order->closed_at) {
                $hour = (int) $order->closed_at->format('G');
                $closed[$hour]++;
                $revenue[$hour] += $order->netRevenue();
            }
        }

        $rows = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $rows[] = [
                'hour' => $hour,
                'label' => sprintf('%02dh', $hour),
                'contacts' => $contacts[$hour],
                'closed' => $closed[$hour],
                'revenue' => $revenue[$hour],
                'rate' => $contacts[$hour] > 0 ? round($closed[$hour] / $contacts[$hour] * 100, 1) : null,
            ];
        }

        $peakContactHour = $this->argMax($contacts);
        $peakClosedHour = $this->argMax($closed);

        return [
            'rows' => $rows,
            'totals' => [
                'contacts' => array_sum($contacts),
                'closed' => array_sum($closed),
                'revenue' => array_sum($revenue),
            ],
            'peak' => [
                'contact_hour' => $peakContactHour,
                'closed_hour' => $peakClosedHour,
            ],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Order> */
    private function fetch(User $user, ReportFilterData $filter)
    {
        return Order::query()
            ->when(
                $filter->dateFrom && $filter->dateTo,
                fn ($q) => $q->whereBetween('data_arrived_at', [$filter->dateFrom, $filter->dateTo]),
            )
            ->when($this->saleIds($user, $filter) !== null, fn ($q) => $q->whereIn('sale_user_id', $this->saleIds($user, $filter)))
            ->when($this->marketerIds($user, $filter) !== null, fn ($q) => $q->whereIn('marketer_user_id', $this->marketerIds($user, $filter)))
            ->when($filter->productId, fn ($q) => $q->where('product_id', $filter->productId))
            ->get([
                'id', 'sale_user_id', 'marketer_user_id', 'data_arrived_at', 'closed_at', 'closing_status',
                'subtotal', 'discount', 'total', 'carrier_service_fee', 'cod_fee', 'shipping_support_fee', 'cod_support',
            ]);
    }

    /** @return array<int, int>|null */
    private function saleIds(User $user, ReportFilterData $filter): ?array
    {
        if ($user->role === UserRole::Sales) {
            if ($user->org_level === OrgLevel::Head) {
                return null;
            }

            return $user->is_team_leader ? $this->scope->allowedSaleIds($user) : [$user->id];
        }

        if ($user->role === UserRole::Admin && $filter->saleId) {
            return [$filter->saleId];
        }

        return null;
    }

    /** @return array<int, int>|null */
    private function marketerIds(User $user, ReportFilterData $filter): ?array
    {
        if ($user->role === UserRole::Marketing) {
            if ($user->org_level === OrgLevel::Head) {
                return null;
            }

            return $user->is_team_leader ? $this->scope->allowedMarketerIds($user) : [$user->id];
        }

        if ($user->role === UserRole::Admin && $filter->marketerId) {
            return [$filter->marketerId];
        }

        return null;
    }

    /** @param  array<int, int>  $values */
    private function argMax(array $values): ?int
    {
        if (array_sum($values) === 0) {
            return null;
        }

        return (int) array_keys($values, max($values))[0];
    }
}
