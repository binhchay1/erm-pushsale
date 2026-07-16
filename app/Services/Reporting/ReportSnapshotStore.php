<?php

namespace App\Services\Reporting;

use App\Data\ReportFilterData;
use App\Models\Reporting\ReportDailyClosure;
use App\Models\Reporting\ReportQuerySnapshot;
use App\Models\User;
use App\Services\Reports\ReportScopeResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class ReportSnapshotStore
{
    public function __construct(
        private readonly ReportDateDirtyTracker $dirtyTracker,
        private readonly ReportScopeResolver $scopeResolver,
    ) {}

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
        return $this->rememberPayload(
            $reportKey,
            $user,
            $this->canonicalPayload($filter->toInertia()),
            $filter->dateFrom,
            $filter->dateTo,
            $filter->dateType->value,
            $compute,
            $forceRefresh,
        );
    }

    /**
     * Snapshot chung cho các report filter không dùng ReportFilterData (Marketing Dashboard, export, API chart…).
     *
     * @template T
     * @param array<string,mixed> $filterPayload
     * @param callable(): T $compute
     * @return array{data:T,cachedAt:?string,fromCache:bool,storage:string,isFinal:bool}
     */
    public function rememberPayload(
        string $reportKey,
        User $user,
        array $filterPayload,
        CarbonInterface|string|null $dateFrom,
        CarbonInterface|string|null $dateTo,
        ?string $dateType,
        callable $compute,
        bool $forceRefresh = false,
    ): array {
        if (! config('reporting.enabled')) {
            return ['data' => $compute(), 'cachedAt' => null, 'fromCache' => false, 'storage' => 'none', 'isFinal' => false];
        }

        $companyId = (int) ($user->company_id ?? 0);
        $payload = $this->canonicalPayload(array_merge($filterPayload, [
            '_report_scope' => $this->scopeFingerprint($user),
        ]));
        $filterHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $from = $dateFrom ? CarbonImmutable::parse($dateFrom, config('reporting.timezone'))->startOfDay() : null;
        $to = $dateTo ? CarbonImmutable::parse($dateTo, config('reporting.timezone'))->endOfDay() : null;
        $isFinal = $this->isClosedDateRange($companyId, $from, $to);

        $snapshotQuery = ReportQuerySnapshot::query()
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->where('report_key', $reportKey)
            ->where('filter_hash', $filterHash);

        if ($forceRefresh) {
            (clone $snapshotQuery)->delete();
        }

        if ($isFinal && ! $forceRefresh) {
            $snapshot = $this->findUsableFinalSnapshot(clone $snapshotQuery);
            if ($snapshot) {
                return $this->finalSnapshotResponse($snapshot, true);
            }
        }

        if (! $isFinal) {
            return $this->rememberLive(
                $reportKey,
                $companyId,
                (int) $user->id,
                $filterHash,
                $compute,
                $forceRefresh,
            );
        }

        $lockKey = "reporting:final-lock:{$companyId}:{$user->id}:{$reportKey}:{$filterHash}";

        try {
            return Cache::lock($lockKey, 900)->block(60, function () use (
                $snapshotQuery,
                $companyId,
                $user,
                $reportKey,
                $filterHash,
                $from,
                $to,
                $dateType,
                $payload,
                $compute,
            ): array {
                $existing = $this->findUsableFinalSnapshot(clone $snapshotQuery);
                if ($existing) {
                    return $this->finalSnapshotResponse($existing, true);
                }

                return $this->storeFinalSnapshot(
                    $companyId,
                    (int) $user->id,
                    $reportKey,
                    $filterHash,
                    $from,
                    $to,
                    $dateType,
                    $payload,
                    $compute(),
                );
            });
        } catch (LockTimeoutException) {
            // Không để một cache lock lỗi làm hỏng trang báo cáo. Kiểm tra lần cuối rồi mới tự tính.
            $existing = $this->findUsableFinalSnapshot(clone $snapshotQuery);
            if ($existing) {
                return $this->finalSnapshotResponse($existing, true);
            }

            return $this->storeFinalSnapshot(
                $companyId,
                (int) $user->id,
                $reportKey,
                $filterHash,
                $from,
                $to,
                $dateType,
                $payload,
                $compute(),
            );
        }
    }

    public function isClosedRange(int $companyId, ReportFilterData $filter): bool
    {
        return $this->isClosedDateRange($companyId, $filter->dateFrom, $filter->dateTo);
    }

    /**
     * @template T
     * @param callable(): T $compute
     * @return array{data:T,cachedAt:?string,fromCache:bool,storage:string,isFinal:bool}
     */
    private function rememberLive(
        string $reportKey,
        int $companyId,
        int $userId,
        string $filterHash,
        callable $compute,
        bool $forceRefresh,
    ): array {
        $ttl = max(30, (int) config('reporting.snapshot_live_ttl_seconds', 300));
        // Dùng time bucket thay vì data revision. Nếu mỗi webhook tạo một key mới thì report lớn
        // sẽ bị query lại liên tục dưới tải cao. Bucket vẫn bảo đảm số liệu tự làm mới theo SLA live.
        $bucket = (int) floor(now()->timestamp / $ttl);
        $cacheKey = "reporting:live:{$companyId}:{$userId}:{$bucket}:{$reportKey}:{$filterHash}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && array_key_exists('payload', $cached)) {
            return [
                'data' => $cached['payload'],
                'cachedAt' => $cached['stored_at'] ?? null,
                'fromCache' => true,
                'storage' => 'redis-live',
                'isFinal' => false,
            ];
        }

        $lockKey = $cacheKey.':lock';
        try {
            return Cache::lock($lockKey, max(120, $ttl * 2))->block(30, function () use ($cacheKey, $ttl, $compute): array {
                $cached = Cache::get($cacheKey);
                if (is_array($cached) && array_key_exists('payload', $cached)) {
                    return [
                        'data' => $cached['payload'],
                        'cachedAt' => $cached['stored_at'] ?? null,
                        'fromCache' => true,
                        'storage' => 'redis-live',
                        'isFinal' => false,
                    ];
                }

                $storedAt = now()->toIso8601String();
                $data = $compute();
                Cache::put($cacheKey, ['payload' => $data, 'stored_at' => $storedAt], $ttl + 15);

                return [
                    'data' => $data,
                    'cachedAt' => $storedAt,
                    'fromCache' => false,
                    'storage' => 'redis-live',
                    'isFinal' => false,
                ];
            });
        } catch (LockTimeoutException) {
            // Một request khác có thể đang build report. Chờ lock timeout xong ưu tiên cache vừa sinh.
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && array_key_exists('payload', $cached)) {
                return [
                    'data' => $cached['payload'],
                    'cachedAt' => $cached['stored_at'] ?? null,
                    'fromCache' => true,
                    'storage' => 'redis-live',
                    'isFinal' => false,
                ];
            }

            $storedAt = now()->toIso8601String();
            return [
                'data' => $compute(),
                'cachedAt' => $storedAt,
                'fromCache' => false,
                'storage' => 'live-lock-fallback',
                'isFinal' => false,
            ];
        }
    }

    /** @param \Illuminate\Database\Eloquent\Builder<ReportQuerySnapshot> $query */
    private function findUsableFinalSnapshot($query): ?ReportQuerySnapshot
    {
        return $query
            ->where('is_final', true)
            ->where(function ($expires): void {
                $expires->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    /** @return array{data:mixed,cachedAt:?string,fromCache:bool,storage:string,isFinal:bool} */
    private function finalSnapshotResponse(ReportQuerySnapshot $snapshot, bool $fromCache): array
    {
        return [
            'data' => $snapshot->decodePayload(),
            'cachedAt' => $snapshot->updated_at?->toIso8601String(),
            'fromCache' => $fromCache,
            'storage' => 'database-final',
            'isFinal' => true,
        ];
    }

    /** @return array{data:mixed,cachedAt:?string,fromCache:bool,storage:string,isFinal:bool} */
    private function storeFinalSnapshot(
        int $companyId,
        int $userId,
        string $reportKey,
        string $filterHash,
        ?CarbonImmutable $from,
        ?CarbonImmutable $to,
        ?string $dateType,
        array $payload,
        mixed $data,
    ): array {
        $encoded = ReportQuerySnapshot::encodePayload($data);
        $sourceWatermark = ReportDailyClosure::query()
            ->where('company_id', $companyId)
            ->when($from && $to, fn ($q) => $q->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()]))
            ->max('source_watermark_at');

        $snapshot = ReportQuerySnapshot::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'user_id' => $userId,
                'report_key' => $reportKey,
                'filter_hash' => $filterHash,
            ],
            [
                'date_from' => $from?->toDateString(),
                'date_to' => $to?->toDateString(),
                'date_type' => $dateType,
                'filter_payload' => $payload,
                'payload' => $encoded['payload'],
                'encoding' => $encoded['encoding'],
                'data_revision' => $this->dirtyTracker->companyRevision($companyId),
                'is_final' => true,
                'source_watermark_at' => $sourceWatermark,
                'expires_at' => now()->addDays((int) config('reporting.snapshot_history_ttl_days', 730)),
            ],
        );

        return [
            'data' => $data,
            'cachedAt' => $snapshot->updated_at?->toIso8601String(),
            'fromCache' => false,
            'storage' => 'database-final',
            'isFinal' => true,
        ];
    }

    private function isClosedDateRange(
        int $companyId,
        CarbonInterface|string|null $dateFrom,
        CarbonInterface|string|null $dateTo,
    ): bool {
        if (! $dateFrom || ! $dateTo) {
            return false;
        }

        $timezone = config('reporting.timezone');
        $from = CarbonImmutable::parse($dateFrom, $timezone)->startOfDay();
        $to = CarbonImmutable::parse($dateTo, $timezone)->startOfDay();
        $today = CarbonImmutable::now($timezone)->startOfDay();

        if ($to->greaterThanOrEqualTo($today)) {
            return false;
        }

        $expected = $from->diffInDays($to) + 1;
        $closed = ReportDailyClosure::query()
            ->where('company_id', $companyId)
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'closed')
            ->count();

        return $closed === $expected;
    }

    /** @return array<string,mixed> */
    private function scopeFingerprint(User $user): array
    {
        $permissions = $user->permissionsMap();
        ksort($permissions);

        return [
            'company_id' => (int) $user->company_id,
            'role' => $user->role->value,
            'org_level' => $user->org_level?->value,
            'team_id' => $user->team_id ? (int) $user->team_id : null,
            'manager_user_id' => $user->manager_user_id ? (int) $user->manager_user_id : null,
            'is_team_leader' => (bool) $user->is_team_leader,
            'permissions' => $permissions,
            'allowed_sale_ids' => array_values($this->scopeResolver->allowedSaleIds($user)),
            'allowed_marketer_ids' => array_values($this->scopeResolver->allowedMarketerIds($user)),
        ];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function canonicalPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = array_is_list($value)
                    ? array_map(fn ($item) => is_array($item) ? $this->canonicalPayload($item) : $item, $value)
                    : $this->canonicalPayload($value);
            }
        }

        ksort($payload);

        return $payload;
    }
}
