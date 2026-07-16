<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Models\User;
use App\Services\Reporting\ReportSnapshotStore;
use Illuminate\Support\Carbon;

/**
 * Router snapshot báo cáo V18.
 *
 * - Kỳ có hôm nay: Redis TTL ngắn để vẫn live.
 * - Kỳ quá khứ đã đóng: snapshot DB bền vững, không query lại raw tables.
 * - Khi webhook/đơn/COD/hàng hoàn sửa ngày cũ, observer đánh dấu dirty và xóa snapshot giao nhau.
 */
class ReportSnapshotCache
{
    public function __construct(
        private readonly ReportSnapshotStore $store,
    ) {}

    /** @return list<string> */
    public static function heavyExtraKeys(): array
    {
        return [
            'sale-1', 'sale-2', 'sale-3', 'sale-4', 'sale-5',
            'marketing-1', 'marketing-2', 'marketing-3', 'marketing-4',
            'kho-1', 'warehouse-sales-summary', 'warehouse-sales-v2', 'product-conversion', 'kho-2',
        ];
    }

    public static function isHeavyExtra(string $key): bool
    {
        return in_array($key, self::heavyExtraKeys(), true);
    }

    /** @return list<string> */
    public static function heavyPageKeys(): array
    {
        return ['team-leaders', 'marketing-dashboard', 'admin-dashboard', 'sales-dashboard', 'warehouse-dashboard', 'accounting-dashboard', 'allocator-dashboard'];
    }

    /**
     * @template T
     * @param callable(): T $compute
     * @return array{data:T,cachedAt:?string,fromCache:bool,storage:string,isFinal:bool}
     */
    public function remember(
        string $reportKey,
        User $user,
        ReportFilterData $filter,
        callable $compute,
        bool $forceRefresh = false,
    ): array {
        return $this->store->remember($reportKey, $user, $filter, $compute, $forceRefresh);
    }

    public function forgetAllForCompany(int $companyId): void
    {
        app(\App\Services\Reporting\ReportDateDirtyTracker::class)->bumpCompanyRevision($companyId);
        \App\Models\Reporting\ReportQuerySnapshot::query()->where('company_id', $companyId)->delete();
    }

    public static function dailyWarmAt(): Carbon
    {
        return now()->endOfDay()->subMinutes(5);
    }
}
