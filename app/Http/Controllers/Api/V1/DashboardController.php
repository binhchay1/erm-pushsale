<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Repositories\LeadIngestionRepository;
use App\Repositories\OrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use ApiResponds;

    public function summary(
        Request $request,
        OrderRepository $orders,
        LeadIngestionRepository $leads,
    ): JsonResponse {
        $user = $request->user();
        $saleUserId = $user->isSales() ? $user->id : null;
        $ttl = max(30, (int) config('reporting.snapshot_live_ttl_seconds', 300));
        $bucket = (int) floor(now()->timestamp / $ttl);
        $cacheKey = "dashboard:api-summary:{$user->company_id}:{$user->id}:{$bucket}";

        $payload = Cache::remember($cacheKey, $ttl + 15, fn (): array => [
            'orders_today' => $orders->arrivedSinceCount(now()->startOfDay(), $saleUserId),
            'orders_total' => $orders->total($saleUserId),
            'leads_today' => $leads->countToday(),
            'leads_pending' => $leads->countPending(),
            'generated_at' => now()->toIso8601String(),
        ]);

        return $this->success($payload);
    }
}
