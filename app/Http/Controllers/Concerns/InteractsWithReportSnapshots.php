<?php

namespace App\Http\Controllers\Concerns;

use App\Data\ReportFilterData;
use App\Services\Reports\ReportSnapshotCache;
use Illuminate\Http\Request;

trait InteractsWithReportSnapshots
{
    /**
     * @template T
     *
     * @param  callable(): T  $compute
     * @return array{data: T, cachedAt: ?string, fromCache: bool, storage: string, isFinal: bool}
     */
    protected function maybeCachedReport(
        Request $request,
        string $reportKey,
        ReportFilterData $filter,
        callable $compute,
        bool $useCache = true,
    ): array {
        if (! $useCache) {
            return [
                'data' => $compute(),
                'cachedAt' => null,
                'fromCache' => false,
                'storage' => 'live',
                'isFinal' => true,
            ];
        }

        return app(ReportSnapshotCache::class)->remember(
            $reportKey,
            $request->user(),
            $filter,
            $compute,
            $request->boolean('refresh'),
        );
    }
}
