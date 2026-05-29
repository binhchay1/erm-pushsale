<?php

namespace App\Services\Reports;

use App\Contracts\Repositories\OrderRepositoryInterface;
use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\RevenueMetricsCalculator;

/**
 * Template Method: dùng chung cho báo cáo doanh số Marketing & Sale.
 */
class RevenueReportService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
    ) {}

    /** @return array<string, mixed> */
    public function forMarketers(ReportFilterData $filter): array
    {
        $users = User::query()->where('role', UserRole::Marketing)->get();

        return $this->buildGrouped($filter, $users, 'marketer_user_id', 'marketerId', 'marketerName');
    }

    /** @return array<string, mixed> */
    public function forSales(ReportFilterData $filter): array
    {
        $users = User::query()->where('role', UserRole::Sales)->get();

        return $this->buildGrouped($filter, $users, 'sale_user_id', 'saleId', 'saleName');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, User>  $users
     * @return array<string, mixed>
     */
    private function buildGrouped(
        ReportFilterData $filter,
        $users,
        string $foreignKey,
        string $idKey,
        string $nameKey,
    ): array {
        $all = $this->orders->allFiltered($filter);
        $rows = [];
        $totalMetrics = RevenueMetricsCalculator::build($all);

        $rows[] = array_merge(
            ['stt' => 0, $idKey => 'total', $nameKey => 'Tổng', 'isTotalRow' => true],
            $totalMetrics,
        );

        foreach ($users as $index => $user) {
            $subset = $all->where($foreignKey, $user->id);
            $metrics = RevenueMetricsCalculator::build($subset);
            $rows[] = array_merge(
                [
                    'stt' => $index + 1,
                    $idKey => (string) $user->id,
                    $nameKey => $user->name,
                    'saleUsername' => strstr($user->email, '@', true),
                    'isTotalRow' => false,
                ],
                $metrics,
            );
        }

        return ['rows' => $rows, 'formulaLegend' => $this->formulaLegend()];
    }

    /** @return list<array{key: string, label: string}> */
    private function formulaLegend(): array
    {
        return [
            ['key' => '1', 'label' => 'Đơn chốt'],
            ['key' => '2', 'label' => 'Xác nhận giao hàng'],
            ['key' => '3', 'label' => 'Hủy vận đơn'],
            ['key' => '4', 'label' => 'Chuyển ĐVGH'],
            ['key' => '5', 'label' => 'Đã hoàn'],
            ['key' => '10', 'label' => '% Đã hoàn = (5)/(4)'],
            ['key' => '15', 'label' => 'Tỷ lệ chốt = Đơn chốt / Contact'],
        ];
    }
}
