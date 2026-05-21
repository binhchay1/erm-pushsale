<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponds;
use App\Models\LeadIngestion;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponds;

    public function summary(Request $request): JsonResponse
    {
        $orderQuery = Order::query();
        if ($request->user()->isSales()) {
            $orderQuery->where('sale_user_id', $request->user()->id);
        }

        $today = now()->startOfDay();

        return $this->success([
            'orders_today' => (clone $orderQuery)->where('data_arrived_at', '>=', $today)->count(),
            'orders_total' => (clone $orderQuery)->count(),
            'leads_today' => LeadIngestion::query()->where('created_at', '>=', $today)->count(),
            'leads_pending' => LeadIngestion::query()->where('status', 'pending')->count(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
