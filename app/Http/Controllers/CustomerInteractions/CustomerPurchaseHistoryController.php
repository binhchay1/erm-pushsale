<?php

namespace App\Http\Controllers\CustomerInteractions;

use App\Enums\ClosingStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CustomerInteractions\CustomerIdentity;
use App\Services\CustomerInteractions\CustomerPurchaseHistoryPresenter;
use App\Support\VietnamesePhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPurchaseHistoryController extends Controller
{
    public function index(Request $request, Order $order): JsonResponse
    {
        $phoneKey = CustomerIdentity::phoneKey($order);
        $closedOnly = $request->boolean('closed_only');

        $orders = Order::query()
            ->with([
                'items:id,order_id,product_id,product_name,item_type,quantity,unit_price,discount_amount',
                'saleUser:id,name',
                'team:id,name',
                'marketingSource:id,name',
                'warehouse:id,name',
            ])
            ->where(function (Builder $query) use ($phoneKey): void {
                $this->applyPhoneMatch($query, $phoneKey);
            })
            ->when($closedOnly, function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->whereNotNull('closed_at')
                        ->orWhere('closing_status', ClosingStatus::Closed->value);
                });
            })
            ->latest('data_arrived_at')
            ->latest('id')
            ->limit(250)
            ->get()
            ->filter(fn (Order $candidate) => CustomerIdentity::samePhone($candidate->customer_phone, $phoneKey))
            ->take(100)
            ->values();

        $closedOrders = $orders->filter(fn (Order $item) => $item->closed_at !== null
            || $item->closing_status === ClosingStatus::Closed->value);

        return response()->json([
            'customer' => [
                'name' => $order->customer_name,
                'phone' => $order->customer_phone,
                'address' => $order->effectiveShippingAddress(),
                'selectedOrderCode' => $order->order_code,
            ],
            'summary' => [
                'orderCount' => $orders->count(),
                'closedOrderCount' => $closedOrders->count(),
                'totalQuantity' => (int) $orders->sum(fn (Order $item) => $item->items->sum('quantity')),
                'totalValue' => (int) $closedOrders->sum(fn (Order $item) => $item->effectiveRevenue()),
                'firstOrderAt' => $orders->sortBy('data_arrived_at')->first()?->data_arrived_at?->toIso8601String(),
                'latestOrderAt' => $orders->first()?->data_arrived_at?->toIso8601String(),
            ],
            'orders' => CustomerPurchaseHistoryPresenter::collection($orders, $order->id),
            'limited' => $orders->count() >= 100,
        ]);
    }

    /**
     * Fast path: exact / prefix matches on common VN formats (index-friendly).
     * Slow path: digit-stripped contains for spaced/punctuated phones (still capped by limit).
     *
     * @param  Builder<\App\Models\Order>  $query
     */
    private function applyPhoneMatch(Builder $query, string $phoneKey): void
    {
        $local10 = VietnamesePhone::normalize($phoneKey);
        $digits = preg_replace('/\D+/', '', $phoneKey) ?: '';
        $variants = $this->phoneLookupVariants($local10 ?? $phoneKey);
        $national9 = $local10 ? substr($local10, 1) : (strlen($digits) >= 9 ? substr($digits, -9) : '');

        $query->where(function (Builder $inner) use ($variants, $national9, $local10): void {
            if ($variants !== []) {
                $inner->whereIn('customer_phone', $variants);

                foreach ($variants as $variant) {
                    $inner->orWhere('customer_phone', 'like', $variant.'%');
                }
            }

            // Spaced / punctuated values e.g. "+84 912 345 678" — strip separators, match national 9 digits.
            if ($national9 !== '') {
                $inner->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '+', ''), '-', ''), '.', ''), '(', '') LIKE ?",
                    ['%'.$national9.'%']
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    private function phoneLookupVariants(string $phoneKey): array
    {
        $digits = preg_replace('/\D+/', '', $phoneKey) ?: '';
        if ($digits === '') {
            return [];
        }

        $local10 = VietnamesePhone::normalize($digits);
        if ($local10 === null && strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $local10 = $digits;
        }
        if ($local10 === null && strlen($digits) === 9) {
            $local10 = VietnamesePhone::normalize('0'.$digits);
        }

        if ($local10 === null) {
            return array_values(array_unique(array_filter([$phoneKey, $digits])));
        }

        $national9 = substr($local10, 1);

        return array_values(array_unique(array_filter([
            $local10,
            $national9,
            '84'.$national9,
            '+84'.$national9,
            '0084'.$national9,
        ])));
    }
}
