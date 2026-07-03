<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cache báo cáo tổng hợp nặng — TTL đến cuối ngày.
 * Tác nghiệp / dashboard realtime không qua lớp này.
 */
class ReportSnapshotCache
{
    /** @return list<string> */
    public static function heavyExtraKeys(): array
    {
        return ['sale-3', 'marketing-1', 'marketing-2', 'marketing-3', 'marketing-4'];
    }

    public static function isHeavyExtra(string $key): bool
    {
        return in_array($key, self::heavyExtraKeys(), true);
    }

    /** @return list<string> */
    public static function heavyPageKeys(): array
    {
        return ['team-leaders', 'marketing-dashboard'];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $compute
     * @return array{data: T, cachedAt: ?string, fromCache: bool}
     */
    public function remember(
        string $reportKey,
        User $user,
        ReportFilterData $filter,
        callable $compute,
        bool $forceRefresh = false,
    ): array {
        $cacheKey = $this->cacheKey($reportKey, $user, $filter);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $hit = Cache::has($cacheKey);
        $ttl = max(300, (int) now()->diffInSeconds(now()->copy()->endOfDay()));

        $data = Cache::remember($cacheKey, $ttl, function () use ($compute) {
            return ['payload' => $compute(), 'stored_at' => now()->toIso8601String()];
        });

        return [
            'data' => $data['payload'],
            'cachedAt' => $data['stored_at'] ?? null,
            'fromCache' => $hit && ! $forceRefresh,
        ];
    }

    public function forgetAllForCompany(int $companyId): void
    {
        // File/database cache không hỗ trợ tag — warm command sẽ ghi đè từng key.
    }

    private function cacheKey(string $reportKey, User $user, ReportFilterData $filter): string
    {
        $companyId = (int) ($user->company_id ?? 0);
        $filterHash = md5(json_encode($filter->toInertia()));

        return "report_snap:{$companyId}:{$user->id}:{$reportKey}:{$filterHash}";
    }

    /** Thời điểm snapshot cuối ngày chạy (dùng cho scheduler). */
    public static function dailyWarmAt(): Carbon
    {
        return now()->endOfDay()->subMinutes(5);
    }
}
