<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\CustomerInteractions\CustomerIdentity;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDataViewHistoryController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        $phone = CustomerIdentity::phoneKey($order);

        ActivityLogger::log(
            action: 'customer.data_viewed',
            subject: $order,
            properties: [
                'customer_phone' => $phone,
                'order_code' => $order->order_code,
                'source' => 'customer_profile',
            ],
            actor: $request->user(),
        );

        $from = $request->date('date_from')?->startOfDay() ?? now()->subDays(29)->startOfDay();
        $to = $request->date('date_to')?->endOfDay() ?? now()->endOfDay();
        $userId = $request->integer('user_id') ?: null;

        $query = ActivityLog::query()
            ->with('actor:id,name,email')
            ->where('action', 'customer.data_viewed')
            ->where('properties->customer_phone', $phone)
            ->whereBetween('created_at', [$from, $to]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $logs = (clone $query)
            ->latest('created_at')
            ->latest('id')
            ->limit(500)
            ->get();

        return response()->json([
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'orderCode' => $order->order_code,
                'orderId' => (string) $order->id,
            ],
            'filters' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
                'user_id' => $userId,
            ],
            'users' => $logs->pluck('actor')->filter()->unique('id')->map(fn ($user) => [
                'value' => (string) $user->id,
                'label' => $user->name,
            ])->values(),
            'logs' => $logs->map(fn (ActivityLog $log) => [
                'id' => (string) $log->id,
                'orderCode' => $log->properties['order_code'] ?? $log->subject_label,
                'action' => 'Xem thông tin số',
                'userName' => $log->actor?->name ?? 'Hệ thống',
                'userEmail' => $log->actor?->email,
                'createdAt' => $log->created_at?->toIso8601String(),
            ])->values(),
            'counts' => $logs->groupBy('user_id')->map(function ($items) {
                $first = $items->first();

                return [
                    'userId' => $first?->user_id ? (string) $first->user_id : null,
                    'userName' => $first?->actor?->name ?? 'Hệ thống',
                    'count' => $items->count(),
                ];
            })->sortByDesc('count')->values(),
            'limitedToDays' => 30,
        ]);
    }
}
