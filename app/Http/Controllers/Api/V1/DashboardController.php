<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Repositories\LeadIngestionRepository;
use App\Repositories\OrderRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponds;

    public function summary(
        Request $request,
        OrderRepository $orders,
        LeadIngestionRepository $leads,
    ): JsonResponse {
        $saleUserId = $request->user()->isSales() ? $request->user()->id : null;

        return $this->success([
            'orders_today' => $orders->arrivedSinceCount(now()->startOfDay(), $saleUserId),
            'orders_total' => $orders->total($saleUserId),
            'leads_today' => $leads->countToday(),
            'leads_pending' => $leads->countPending(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
