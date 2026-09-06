<?php

namespace App\Services\Shops;

use App\Data\ReportFilterData;
use App\Models\Order;
use App\Models\Product;
use App\Models\Scopes\ShopScope;
use App\Models\Shop;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Support\LeadContactMetrics;
use App\Support\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ShopOverviewService
{
    /**
     * So sánh các shop trong company (bypass ShopScope).
     *
     * @return array{
     *   shops: list<array<string,mixed>>,
     *   product_matrix: list<array<string,mixed>>,
     *   totals: array<string,mixed>,
     *   period: array{from:string,to:string}
     * }
     */
    public function compare(User $user, ReportFilterData $filter): array
    {
        $companyId = (int) $user->company_id;
        abort_unless($companyId > 0, 403);

        $from = $filter->dateFrom ?? now()->startOfMonth();
        $to = $filter->dateTo ?? now()->endOfDay();

        return app(TenantManager::class)->forCompany($companyId, function () use ($companyId, $from, $to, $user) {
            $shops = Shop::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();

            if (! app(ShopProvisioningService::class)->canAccessAllShops($user)) {
                $allowedIds = collect(app(ShopProvisioningService::class)->accessibleShopsFor($user))->pluck('id')->all();
                $shops = $shops->whereIn('id', $allowedIds)->values();
            }

            $shopIds = $shops->pluck('id')->map(fn ($id) => (int) $id)->all();

            $orders = Order::query()
                ->withoutGlobalScope(ShopScope::class)
                ->where('company_id', $companyId)
                ->whereIn('shop_id', $shopIds)
                ->where(function ($query) use ($from, $to): void {
                    $query->whereBetween('data_arrived_at', [$from, $to])
                        ->orWhereBetween('closed_at', [$from, $to])
                        ->orWhereBetween('assigned_at', [$from, $to]);
                })
                ->with(['items'])
                ->get();

            $contactOrderIds = LeadContactMetrics::contactOrderIds($orders);
            $rows = [];
            $productBuckets = [];

            foreach ($shops as $shop) {
                $shopOrders = $orders->where('shop_id', (int) $shop->id);
                $contactOrders = $shopOrders->filter(fn (Order $o) => $contactOrderIds->contains((int) $o->id));
                $closedContacts = $contactOrders->filter(fn (Order $o) => $this->isClosed($o));
                $appointments = $shopOrders->filter(function (Order $o) use ($from, $to): bool {
                    if (! $o->next_operation_at) {
                        return false;
                    }

                    return $o->next_operation_at->between($from, $to);
                });

                $closedOrders = $shopOrders->filter(fn (Order $o) => $this->isClosed($o));
                $salesRevenue = $closedOrders->sum(fn (Order $o) => $this->orderRevenue($o));
                $marketingRevenue = $closedOrders
                    ->filter(fn (Order $o) => filled($o->marketer_user_id))
                    ->sum(fn (Order $o) => $this->orderRevenue($o));

                $stock = WarehouseInventory::query()
                    ->whereHas('warehouse', fn ($q) => $q->withoutGlobalScope(ShopScope::class)->where('shop_id', $shop->id))
                    ->sum('stock_quantity');

                $productCount = Product::query()
                    ->withoutGlobalScope(ShopScope::class)
                    ->where('shop_id', $shop->id)
                    ->where('is_active', true)
                    ->count();

                $contacts = $contactOrders->count();
                $closedContactCount = $closedContacts->count();
                $appointmentCount = $appointments->count();

                $rows[] = [
                    'shop_id' => (int) $shop->id,
                    'shop_name' => $shop->name,
                    'shop_code' => $shop->code,
                    'is_default' => (bool) $shop->is_default,
                    'contacts' => $contacts,
                    'closed_contacts' => $closedContactCount,
                    'tlc' => $this->pct($closedContactCount, $contacts),
                    'appointments' => $appointmentCount,
                    'tlh' => $this->pct($appointmentCount, $contacts),
                    'sales_revenue' => (int) $salesRevenue,
                    'marketing_revenue' => (int) $marketingRevenue,
                    'product_count' => $productCount,
                    'stock_quantity' => (int) $stock,
                ];

                foreach ($closedOrders as $order) {
                    $this->accumulateProduct($productBuckets, $order, (int) $shop->id, $shop->name, $contactOrderIds, $from, $to);
                }
                foreach ($contactOrders as $order) {
                    if ($this->isClosed($order)) {
                        continue;
                    }
                    $this->accumulateProduct($productBuckets, $order, (int) $shop->id, $shop->name, $contactOrderIds, $from, $to, contactOnly: true);
                }
            }

            $productMatrix = collect($productBuckets)
                ->map(function (array $bucket): array {
                    $contacts = $bucket['contacts'];
                    $closed = $bucket['closed'];
                    $appointments = $bucket['appointments'];

                    return [
                        'shop_id' => $bucket['shop_id'],
                        'shop_name' => $bucket['shop_name'],
                        'product_id' => $bucket['product_id'],
                        'product_name' => $bucket['product_name'],
                        'contacts' => $contacts,
                        'closed' => $closed,
                        'tlc' => $this->pct($closed, $contacts),
                        'appointments' => $appointments,
                        'tlh' => $this->pct($appointments, $contacts),
                        'revenue' => $bucket['revenue'],
                    ];
                })
                ->sortByDesc('revenue')
                ->values()
                ->all();

            $totals = [
                'contacts' => array_sum(array_column($rows, 'contacts')),
                'closed_contacts' => array_sum(array_column($rows, 'closed_contacts')),
                'appointments' => array_sum(array_column($rows, 'appointments')),
                'sales_revenue' => array_sum(array_column($rows, 'sales_revenue')),
                'marketing_revenue' => array_sum(array_column($rows, 'marketing_revenue')),
                'stock_quantity' => array_sum(array_column($rows, 'stock_quantity')),
                'product_count' => array_sum(array_column($rows, 'product_count')),
            ];
            $totals['tlc'] = $this->pct($totals['closed_contacts'], $totals['contacts']);
            $totals['tlh'] = $this->pct($totals['appointments'], $totals['contacts']);

            return [
                'shops' => $rows,
                'product_matrix' => $productMatrix,
                'totals' => $totals,
                'period' => [
                    'from' => Carbon::parse($from)->toDateString(),
                    'to' => Carbon::parse($to)->toDateString(),
                ],
            ];
        });
    }

    private function isClosed(Order $order): bool
    {
        return (string) $order->closing_status === 'closed';
    }

    private function orderRevenue(Order $order): int
    {
        $total = (int) ($order->total ?? 0);
        if ($total > 0) {
            return $total;
        }

        return max(0, (int) ($order->subtotal ?? 0) - (int) ($order->discount ?? 0));
    }

    private function pct(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $buckets
     * @param  Collection<int, int>  $contactOrderIds
     */
    private function accumulateProduct(
        array &$buckets,
        Order $order,
        int $shopId,
        string $shopName,
        Collection $contactOrderIds,
        mixed $from,
        mixed $to,
        bool $contactOnly = false,
    ): void {
        $productId = (int) ($order->product_id ?: 0);
        $productName = $order->product?->name
            ?? $order->items->first()?->product_name
            ?? ('#'.$productId);

        $key = $shopId.':'.$productId;
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'shop_id' => $shopId,
                'shop_name' => $shopName,
                'product_id' => $productId,
                'product_name' => $productName,
                'contacts' => 0,
                'closed' => 0,
                'appointments' => 0,
                'revenue' => 0,
            ];
        }

        $isContact = $contactOrderIds->contains((int) $order->id);
        if ($isContact) {
            $buckets[$key]['contacts']++;
        }

        if (! $contactOnly && $this->isClosed($order) && $isContact) {
            $buckets[$key]['closed']++;
            $buckets[$key]['revenue'] += $this->orderRevenue($order);
        }

        if ($order->next_operation_at && $order->next_operation_at->between($from, $to)) {
            $buckets[$key]['appointments']++;
        }
    }
}
