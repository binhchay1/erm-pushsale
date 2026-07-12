<?php

namespace App\Services\Legacy;

use App\Models\ActivityLog;
use App\Models\IntegrationConnection;
use App\Models\LeadIngestion;
use App\Models\LegacyModuleRecord;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LegacyPageService
{
    /** @return array<string, mixed> */
    public function schema(string $code): array
    {
        $schema = config("legacy_pages.{$code}");
        abort_unless(is_array($schema), 404);

        return array_merge([
            'code' => $code,
            'kind' => 'table',
            'source' => 'generic',
            'editable' => false,
            'upsell' => false,
            'filters' => [],
            'dialogs' => [],
            'template_alias' => $code,
        ], $schema, ['code' => $code]);
    }

    /** @return array{data: array<int, array<string, mixed>>, meta: array<string, int>} */
    public function rows(string $code, Request $request): array
    {
        $schema = $this->schema($code);
        $source = (string) $schema['source'];

        $rows = match ($source) {
            'users' => $this->users(),
            'teams' => $this->teams(),
            'products' => $this->products(false),
            'combos' => $this->products(true),
            'activity_logs' => $this->activityLogs($code),
            'login_permissions' => $this->loginPermissions(),
            'integrations' => $this->integrations(),
            'marketing_sources' => $this->marketingSources(),
            'lead_ingestions' => $this->leadIngestions(),
            'lead_imports' => $this->genericRows($code),
            'customer_multidimensional' => $this->customerMultidimensional(),
            'customer_spending' => $this->customerSpending(),
            'customer_orders' => $this->customerOrders(),
            'sales_ranking' => $this->salesRanking(),
            'sale_operation_rate' => $this->saleOperationRate(),
            'sale_work' => $this->saleWork(),
            'sale_team' => $this->saleTeam(),
            'sale_data' => $this->saleData(),
            'sale_optimization' => $this->saleOptimization(),
            'warehouse_orders' => $this->warehouseOrders(),
            'warehouses' => $this->warehouses(),
            'inventory' => $this->inventory(),
            'warehouse_vouchers' => $this->warehouseVouchers(),
            'movements' => $this->movements(),
            'inventory_daily' => $this->inventoryDaily(),
            'inventory_pending' => $this->inventoryPending(),
            'inventory_summary' => $this->inventorySummary(),
            'care_report' => $this->careReport(),
            'phone_corrections' => $this->phoneCorrections(),
            'delivery_by_care' => $this->deliveryByCare(),
            'care_operations' => $this->careOperations(),
            'care_allocation' => $this->careAllocation(),
            'monthly_plan' => $this->monthlyPlan($code),
            'trend' => $this->trend(),
            'allocation_summary' => $this->allocationSummary(),
            'power_dashboard' => $this->powerDashboard(),
            'repurchase' => $this->repurchase(),
            'repurchase_products' => $this->repurchaseProducts(),
            'allocation_v2' => $this->allocationV2(),
            'care_allocation_daily' => $this->careAllocationDaily(),
            default => $this->genericRows($code),
        };

        $rows = $this->applySearch($rows, trim((string) $request->query('search', '')));
        $perPage = max(10, min(100, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));
        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'data' => $slice->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total),
            ],
        ];
    }

    /** @return array<string, array<int, array{id: string|int, label: string}>> */
    public function filterOptions(): array
    {
        return [
            'users' => User::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'label' => $u->name])->all(),
            'sales' => User::query()->where('role', User::ROLE_SALES)->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'label' => $u->name])->all(),
            'teams' => Team::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (Team $t) => ['id' => $t->id, 'label' => $t->name])->all(),
            'products' => Product::query()->orderBy('name')->limit(1000)->get(['id', 'name', 'type', 'unit_price'])->map(fn (Product $p) => [
                'id' => $p->id,
                'label' => $p->name,
                'type' => $p->type,
                'unit_price' => (int) $p->unit_price,
            ])->all(),
            'warehouses' => Warehouse::query()->orderBy('name')->limit(200)->get(['id', 'name'])->map(fn (Warehouse $w) => ['id' => $w->id, 'label' => $w->name])->all(),
            'sources' => MarketingSource::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (MarketingSource $source) => ['id' => $source->id, 'label' => $source->name])->all(),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function create(string $code, array $payload, ?User $actor): LegacyModuleRecord
    {
        abort_unless((bool) $this->schema($code)['editable'], 403);

        return LegacyModuleRecord::query()->create([
            'module_code' => $code,
            'status' => $payload['status'] ?? null,
            'payload' => $payload,
            'created_by_user_id' => $actor?->id,
            'updated_by_user_id' => $actor?->id,
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function update(string $code, LegacyModuleRecord $record, array $payload, ?User $actor): LegacyModuleRecord
    {
        abort_unless($record->module_code === $code, 404);
        abort_unless((bool) $this->schema($code)['editable'], 403);

        $record->update([
            'status' => $payload['status'] ?? $record->status,
            'payload' => array_merge($record->payload ?? [], $payload),
            'updated_by_user_id' => $actor?->id,
        ]);

        return $record->refresh();
    }

    private function genericRows(string $code): Collection
    {
        if (! Schema::hasTable('legacy_module_records')) {
            return collect();
        }

        return LegacyModuleRecord::query()
            ->where('module_code', $code)
            ->latest()
            ->limit(2000)
            ->get()
            ->map(function (LegacyModuleRecord $record, int $index): array {
                return array_merge($record->payload ?? [], [
                    'id' => $record->id,
                    'index' => $index + 1,
                    'status' => $record->status ?? ($record->payload['status'] ?? null),
                    'created_at' => $record->created_at?->toIso8601String(),
                    'updated_at' => $record->updated_at?->toIso8601String(),
                    '_record_id' => $record->id,
                ]);
            });
    }

    private function users(): Collection
    {
        return User::query()->with('team:id,name')->latest('id')->limit(1000)->get()->values()->map(fn (User $user, int $index) => [
            'index' => $index + 1,
            'select' => false,
            'name' => $user->name,
            'role' => $user->roleLabel(),
            'employee_code' => 'NV'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            'base_salary' => (int) data_get($user->permissions, 'base_salary', 0),
            'phone' => $user->phone,
            'email' => $user->email,
            'leader' => $user->team?->name ?? ($user->is_team_leader ? 'Trưởng nhóm' : ''),
            'receive_data' => data_get($user->permissions, 'receive_data', true),
            'shift' => data_get($user->permissions, 'shift', 'Giờ hành chính'),
            'active' => true,
            'updated_at' => $user->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_edit_url' => "/admin/users/{$user->id}/edit",
        ]);
    }

    private function teams(): Collection
    {
        return Team::query()->with(['leader:id,name', 'parent:id,name'])->withCount('users')->latest('id')->limit(500)->get()->values()->map(fn (Team $team, int $index) => [
            'index' => $index + 1,
            'type' => $team->type && method_exists($team->type, 'label') ? $team->type->label() : (string) ($team->type?->value ?? $team->type ?? ''),
            'code' => 'TEAM'.str_pad((string) $team->id, 3, '0', STR_PAD_LEFT),
            'name' => $team->name,
            'leader' => $team->leader?->name,
            'member_count' => $team->users_count,
            'members' => $team->users_count ? "{$team->users_count} thành viên" : '',
            'parent' => $team->parent?->name,
            'updated_at' => $team->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_edit_url' => "/admin/teams/{$team->id}/edit",
        ]);
    }

    private function products(bool $combos): Collection
    {
        return Product::query()
            ->where('type', $combos ? 'combo' : 'product')
            ->with('parent:id,name')
            ->withCount('children')
            ->latest('id')
            ->limit(1500)
            ->get()
            ->values()
            ->map(function (Product $product, int $index) use ($combos): array {
                if ($combos) {
                    $count = max(1, (int) $product->children_count);
                    return [
                        'id' => $product->id,
                        'code' => $product->sku ?: 'CB'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                        'name' => $product->name,
                        'product_count' => $count,
                        'original_total' => (int) $product->unit_price,
                        'combo_total' => (int) $product->unit_price,
                        'status' => $product->is_active ? 'Đang áp dụng' : 'Ngừng áp dụng',
                        'applied_at' => $product->created_at?->format('d/m/Y'),
                        'limit_quantity' => null,
                        'sold' => 0,
                        'remaining' => null,
                        'shipping_support' => 0,
                        'updated_at' => $product->updated_at?->toIso8601String(),
                        '_edit_url' => "/admin/products/{$product->id}/edit",
                    ];
                }

                return [
                    'id' => $product->id,
                    'category' => $product->parent?->name ?? 'Sản phẩm',
                    'product' => trim("{$product->name} ({$product->sku})"),
                    'unit' => 'SP',
                    'cost_price' => 0,
                    'unit_price' => (int) $product->unit_price,
                    'vat' => 0,
                    'vat_code' => '',
                    'price_after_vat' => (int) $product->unit_price,
                    'weight' => 0,
                    'inactive' => ! $product->is_active,
                    'marketing' => true,
                    'sale' => true,
                    'care' => true,
                    'updated_at' => $product->updated_at?->toIso8601String(),
                    'actions' => 'Cập nhật',
                    '_edit_url' => "/admin/products/{$product->id}/edit",
                ];
            });
    }

    private function activityLogs(string $code): Collection
    {
        return ActivityLog::query()->with('actor:id,name,email')->latest('created_at')->limit(2000)->get()->values()->map(function (ActivityLog $log, int $index) use ($code): array {
            if ($code === '1.7.3') {
                return [
                    'id' => $index + 1,
                    'filter_form' => data_get($log->properties, 'filters', $log->subject_label ?: $log->actionLabel()),
                    'closing_status' => data_get($log->properties, 'closing_status'),
                    'delivery_status' => data_get($log->properties, 'delivery_status'),
                    'date_filter' => data_get($log->properties, 'date_type'),
                    'user' => $log->actor?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            }

            return [
                'ip_address' => $log->ip_address,
                'company' => data_get($log->properties, 'company', 'Đơn vị hiện tại'),
                'account' => $log->actor?->email ?? $log->actor?->name,
                'access_code' => Str::limit((string) data_get($log->properties, 'access_code', $log->subject_label), 48),
                'browser' => Str::limit((string) $log->user_agent, 80),
                'created_at' => $log->created_at?->toIso8601String(),
                'status' => str_contains($log->action, 'fail') ? 'Không thành công' : 'Thành công',
            ];
        });
    }

    private function loginPermissions(): Collection
    {
        return User::query()->latest('updated_at')->limit(1000)->get()->values()->map(fn (User $user) => [
            'company' => 'Đơn vị hiện tại',
            'account' => $user->email,
            'access_code' => data_get($user->permissions, 'access_code'),
            'login_at' => $user->updated_at?->toIso8601String(),
            'status' => 'Đã phê duyệt',
            'actions' => 'Cập nhật',
        ]);
    }

    private function integrations(): Collection
    {
        return IntegrationConnection::query()->latest('id')->limit(200)->get()->map(fn (IntegrationConnection $connection) => [
            'fanpage' => strtoupper((string) $connection->platform),
            'fb_creator' => data_get($connection->metadata, 'creator', '—'),
            'pushsale_user' => data_get($connection->metadata, 'user', '—'),
            'updated_at' => $connection->updated_at?->toIso8601String(),
            'status' => $connection->is_enabled ? 'Đang bật' : 'Đang tắt',
        ]);
    }

    private function marketingSources(): Collection
    {
        return MarketingSource::query()->with(['marketer:id,name', 'product:id,name'])->latest('id')->limit(2000)->get()->values()->map(fn (MarketingSource $source, int $index) => [
            'index' => $index + 1,
            'marketer' => $source->marketer?->name,
            'source' => $source->name."\n".url('/api/v1/webhooks/landing/'.$source->webhook_token),
            'channel' => $source->ad_channel ?: $source->utm_source,
            'product' => $source->product?->name,
            'sale_priority' => $source->lead_allocation?->value ?? 'round_robin',
            'allocation' => $source->lead_allocation?->value,
            'webhook_url' => url('/api/v1/webhooks/landing/'.$source->webhook_token),
            'manual_import' => true,
            'approved' => (bool) $source->is_approved,
            'updated_at' => $source->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
        ]);
    }

    private function leadIngestions(): Collection
    {
        return LeadIngestion::query()->latest('id')->limit(1000)->get()->values()->map(fn (LeadIngestion $lead, int $index) => [
            'index' => $index + 1,
            'customer_name' => $lead->customer_name,
            'customer_phone' => $lead->customer_phone,
            'message' => data_get($lead->payload, 'message', $lead->product_interest),
            'created_at' => $lead->created_at?->toIso8601String(),
            'status' => $lead->status?->value,
            'is_upsell' => $lead->isSupplementalPacket(),
        ]);
    }

    private function customerMultidimensional(): Collection
    {
        $orders = $this->recentOrders();
        $total = max(1, $orders->count());
        $dimensions = [
            'Khách mua 1 lần' => $orders->groupBy('customer_phone')->filter(fn (Collection $g) => $g->count() === 1)->count(),
            'Khách mua lại' => $orders->groupBy('customer_phone')->filter(fn (Collection $g) => $g->count() > 1)->count(),
            'Khách đã giao thành công' => $orders->where('delivery_status', 'delivered')->count(),
            'Khách đang chăm sóc' => $orders->whereNotNull('next_operation_at')->count(),
        ];

        return collect($dimensions)->map(fn ($quantity, $label) => [
            'dimension' => $label,
            'quantity' => $quantity,
            'ratio' => round(((int) $quantity / $total) * 100, 2),
        ])->values()->map(fn ($row, $index) => ['index' => $index + 1] + $row);
    }

    private function customerSpending(): Collection
    {
        $orders = $this->recentOrders();
        $groups = $orders->groupBy(fn (Order $order) => $order->is_returning_customer ? 'Khách cũ' : 'Khách mới');
        $total = max(1, $orders->pluck('customer_phone')->filter()->unique()->count());

        return $groups->map(function (Collection $group, string $type) use ($total): array {
            return [
                'customer_type' => $type,
                'delivery_status' => 'Tất cả',
                'customer_count' => $group->pluck('customer_phone')->filter()->unique()->count(),
                'ratio' => round(($group->count() / $total) * 100, 2),
                'description' => $type === 'Khách cũ' ? 'Khách hàng có lịch sử mua trước đó' : 'Khách hàng phát sinh lần đầu',
            ];
        })->values()->map(fn ($row, $index) => ['index' => $index + 1] + $row);
    }

    private function customerOrders(): Collection
    {
        return Order::query()
            ->with(['saleUser:id,name,email', 'marketingSource:id,name', 'warehouse:id,name', 'items:id,order_id,product_name,item_type,quantity,unit_price,discount_amount'])
            ->latest('data_arrived_at')
            ->limit(1000)
            ->get()
            ->map(function (Order $order): array {
                $productLines = $order->items->map(fn ($item) => trim("{$item->product_name} x{$item->quantity} ".number_format((int) $item->unit_price)))->implode("\n");
                $upsellCount = $order->items->where('item_type', 'upsell')->sum('quantity');
                return [
                    'order_code' => $order->order_code,
                    'source' => trim(($order->marketingSource?->name ?? '—')."\n".$order->data_arrived_at?->format('d/m/Y H:i:s')),
                    'customer' => trim("{$order->customer_name}\n{$order->customer_phone}"),
                    'address' => $order->effectiveShippingAddress(),
                    'message' => $order->customer_note,
                    'sale' => trim(($order->saleUser?->name ?? '—')."\n".$order->assigned_at?->format('d/m/Y H:i:s')),
                    'operation' => trim(($order->operation_stage ?: 'Khách mới')."\n".$order->closed_at?->format('d/m/Y H:i:s')),
                    'result' => trim(($order->operation_result ?: '—')."\n".$order->next_operation_at?->format('d/m/Y H:i:s')),
                    'products' => $productLines,
                    'money' => implode("\n", [number_format((int) $order->subtotal), '-'.number_format((int) $order->discount), number_format((int) $order->shipping_fee_collected), number_format((int) $order->total)]),
                    'deposit' => (int) $order->deposit,
                    'shipping' => trim(($order->warehouse?->name ?? '—')."\n".($order->shipping_provider ?: $order->shipping_method)."\n".$order->tracking_number),
                    'delivery' => trim(($order->delivery_status ?: 'Chờ vận đơn')."\n".$order->desired_delivery_at?->format('d/m/Y')),
                    'internal_note' => $order->internal_recon_note,
                    'actions' => $upsellCount > 0 ? "Upsale: {$upsellCount}\nLịch sử · Tin nhắn · Mua hàng" : 'Lịch sử · Tin nhắn · Mua hàng',
                    'is_upsell' => $upsellCount > 0,
                    '_order_id' => $order->id,
                ];
            });
    }

    private function salesRanking(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(function (array $row, int $index): array {
            return [
                'index' => $index + 1,
                'sale' => $row['name'],
                'new_customers' => $row['new_contacts'].' contact / '.$row['new_closed'].' đơn / '.number_format($row['new_revenue']),
                'old_customers' => $row['old_contacts'].' contact / '.$row['old_closed'].' đơn / '.number_format($row['old_revenue']),
                'total' => $row['contacts'].' contact / '.$row['closed'].' đơn / '.number_format($row['revenue']),
            ];
        });
    }

    private function saleOperationRate(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(function (array $r, int $index): array {
            $contacts = max(1, $r['contacts']);
            return [
                'index' => $index + 1,
                'sale' => $r['name'],
                'total_contacts' => $r['contacts'],
                'total_closed' => $r['closed'],
                'total_rate' => round(($r['closed'] / $contacts) * 100, 2),
                'revenue' => $r['revenue'],
                'call_1' => $r['stages']['call_1'] ?? 0,
                'call_2' => $r['stages']['call_2'] ?? 0,
                'call_3' => $r['stages']['call_3'] ?? 0,
                'call_4' => $r['stages']['call_4'] ?? 0,
                'call_5' => $r['stages']['call_5'] ?? 0,
                'call_6' => $r['stages']['call_6'] ?? 0,
                'care_1' => $r['stages']['care_1'] ?? 0,
                'care_2' => $r['stages']['care_2'] ?? 0,
                'care_3' => $r['stages']['care_3'] ?? 0,
                'skipped' => $r['stages']['skipped'] ?? 0,
            ];
        });
    }

    private function saleWork(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(function (array $r, int $index): array {
            return [
                'index' => $index + 1,
                'sale' => $r['name'],
                'total_contacts' => $r['contacts'],
                'untouched' => $r['untouched'],
                'call_1' => $r['stages']['call_1'] ?? 0,
                'call_2' => $r['stages']['call_2'] ?? 0,
                'call_3' => $r['stages']['call_3'] ?? 0,
                'call_4' => $r['stages']['call_4'] ?? 0,
                'call_5' => $r['stages']['call_5'] ?? 0,
                'call_6' => $r['stages']['call_6'] ?? 0,
                'care_1' => $r['stages']['care_1'] ?? 0,
                'care_2' => $r['stages']['care_2'] ?? 0,
                'care_3' => $r['stages']['care_3'] ?? 0,
                'skipped' => $r['stages']['skipped'] ?? 0,
            ];
        });
    }

    private function saleTeam(): Collection
    {
        $orders = $this->recentOrders()->groupBy(fn (Order $o) => $o->team?->name ?? 'Chưa có nhóm');
        return $orders->map(function (Collection $group, string $team): array {
            $closed = $group->whereNotNull('closed_at');
            return [
                'team' => $team,
                'total_contacts' => $group->count(),
                'closed' => $closed->count(),
                'rate' => round(($closed->count() / max(1, $group->count())) * 100, 2),
                'revenue' => $closed->sum(fn (Order $o) => $o->effectiveRevenue()),
                'delivered' => $group->where('delivery_status', 'delivered')->count(),
                'delivered_revenue' => $group->where('delivery_status', 'delivered')->sum(fn (Order $o) => $o->effectiveRevenue()),
            ];
        })->values()->map(fn ($row, $index) => ['index' => $index + 1] + $row);
    }

    private function saleData(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(fn (array $r, int $index) => [
            'index' => $index + 1,
            'sale' => $r['name'],
            'new_contacts' => $r['new_contacts'],
            'old_contacts' => $r['old_contacts'],
            'operated' => $r['contacts'] - $r['untouched'],
            'untouched' => $r['untouched'],
            'closed' => $r['closed'],
            'rate' => round(($r['closed'] / max(1, $r['contacts'])) * 100, 2),
            'revenue' => $r['revenue'],
        ]);
    }

    private function saleOptimization(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(function (array $r, int $index): array {
            $operationRate = (($r['contacts'] - $r['untouched']) / max(1, $r['contacts'])) * 100;
            $closingRate = ($r['closed'] / max(1, $r['contacts'])) * 100;
            return [
                'index' => $index + 1,
                'sale' => $r['name'],
                'contacts' => $r['contacts'],
                'operation_rate' => round($operationRate, 2),
                'closing_rate' => round($closingRate, 2),
                'revenue' => $r['revenue'],
                'score' => round(($operationRate * .4) + ($closingRate * .6), 2),
            ];
        });
    }

    private function warehouseOrders(): Collection
    {
        return Order::query()->with(['saleUser:id,name', 'warehouse:id,name', 'items:id,order_id,product_name,quantity,unit_price,item_type'])->latest('closed_at')->limit(1000)->get()->map(function (Order $order): array {
            return [
                'select' => false,
                'order_info' => trim(($order->saleUser?->name ?? '—')."\n".$order->data_arrived_at?->format('d/m/Y H:i:s')."\n".$order->order_code),
                'shipping' => trim(($order->warehouse?->name ?? '—')."\n".($order->shipping_method ?: 'Thủ công')."\n".$order->tracking_number),
                'care' => trim($order->next_operation_at?->format('d/m/Y H:i:s')."\n".$order->operation_result."\n".$order->accounting_notes),
                'delivery' => trim($order->updated_at?->format('d/m/Y H:i:s')."\n".($order->delivery_status ?: 'Chờ vận đơn')."\n".$order->closed_at?->format('d/m/Y H:i:s')),
                'customer' => trim("{$order->customer_name}\n{$order->customer_phone}\n".$order->desired_delivery_at?->format('d/m/Y')),
                'address' => trim($order->effectiveShippingAddress()."\n".$order->shipping_notes),
                'products' => $order->items->map(fn ($i) => "{$i->product_name} x{$i->quantity} ".number_format((int) $i->unit_price).($i->item_type === 'upsell' ? ' [UPSALE]' : ''))->implode("\n"),
                'money' => implode("\n", [number_format((int) $order->subtotal), '-'.number_format((int) $order->discount), number_format((int) $order->vat), number_format((int) $order->shipping_fee_collected), number_format((int) $order->total)]),
                'deposit' => (int) $order->deposit,
                'collect' => (int) $order->amount_to_collect,
                'carrier_fee' => (int) $order->carrier_service_fee,
                'shipping_support' => (int) $order->shipping_support_fee,
                'internal_note' => $order->internal_recon_note,
                'actions' => 'Cập nhật vận đơn',
                'is_upsell' => $order->items->contains('item_type', 'upsell'),
                '_order_id' => $order->id,
            ];
        });
    }

    private function warehouses(): Collection
    {
        return Warehouse::query()->with('manager:id,name')->latest('id')->limit(500)->get()->map(fn (Warehouse $warehouse) => [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'phone' => $warehouse->phone,
            'province' => $warehouse->pick_province,
            'district' => $warehouse->pick_district,
            'ward' => $warehouse->pick_ward,
            'address' => $warehouse->address,
            'manager' => $warehouse->manager?->name,
            'vtp_code' => $warehouse->vtp_code,
            'ghn_code' => $warehouse->ghtk_pick_address_id,
            'updated_at' => $warehouse->updated_at?->toIso8601String(),
            '_edit_url' => "/admin/warehouses/{$warehouse->id}/edit",
        ]);
    }

    private function inventory(): Collection
    {
        return WarehouseInventory::query()->with(['warehouse:id,name', 'product:id,name,sku,is_active'])->latest('id')->limit(2000)->get()->map(fn (WarehouseInventory $item) => [
            'id' => $item->id,
            'warehouse' => $item->warehouse?->name,
            'product' => trim(($item->product?->name ?? '—').' ('.($item->product?->sku ?? '').')'),
            'uom' => $item->uom,
            'batch_code' => $item->batch_code,
            'expiry_date' => $item->expiry_date?->toDateString(),
            'location' => $item->location_code,
            'stock' => (int) $item->stock_quantity,
            'pending' => (int) $item->pending_sales_quantity,
            'low_stock' => max(0, 10 - (int) $item->stock_quantity),
            'discontinued' => (bool) $item->is_discontinued,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ]);
    }

    private function warehouseVouchers(): Collection
    {
        return WarehouseInventoryMovement::query()->with(['warehouse:id,name'])->latest('id')->limit(2000)->get()->groupBy(fn ($m) => $m->reference_type.'-'.$m->reference_id.'-'.$m->created_at?->format('YmdHi'))->values()->map(function (Collection $group): array {
            /** @var WarehouseInventoryMovement $first */
            $first = $group->first();
            return [
                'id' => $first->id,
                'warehouse' => $first->warehouse?->name,
                'type' => WarehouseInventoryMovement::typeLabel($first->type),
                'voucher_code' => $first->reference_id ? strtoupper((string) $first->reference_type).'-'.$first->reference_id : 'PXN-'.$first->id,
                'performed_at' => $first->created_at?->toIso8601String(),
                'total_quantity' => $group->sum('quantity'),
                'total_value' => 0,
                'status' => 'Hoàn thành',
                'note' => $first->note,
                'internal_voucher' => '',
                'updated_at' => $first->updated_at?->toIso8601String(),
            ];
        });
    }

    private function movements(): Collection
    {
        return WarehouseInventoryMovement::query()->with(['warehouse:id,name', 'product:id,name,sku'])->latest('id')->limit(2500)->get()->values()->map(fn (WarehouseInventoryMovement $movement, int $index) => [
            'index' => $index + 1,
            'warehouse' => $movement->warehouse?->name,
            'product' => trim(($movement->product?->name ?? '—').' ('.($movement->product?->sku ?? '').')'),
            'type' => WarehouseInventoryMovement::typeLabel($movement->type),
            'quantity' => (int) $movement->quantity,
            'pending' => 0,
            'reference' => $movement->reference_id ? "{$movement->reference_type} #{$movement->reference_id}" : '',
            'note' => $movement->note,
            'created_at' => $movement->created_at?->toIso8601String(),
        ]);
    }

    private function inventoryDaily(): Collection
    {
        return WarehouseInventory::query()->with(['warehouse:id,name', 'product:id,name,sku'])->latest('id')->limit(2000)->get()->values()->map(fn (WarehouseInventory $item, int $index) => [
            'index' => $index + 1,
            'warehouse' => $item->warehouse?->name,
            'product' => trim(($item->product?->name ?? '—').' ('.($item->product?->sku ?? '').')'),
            'batch_code' => $item->batch_code,
            'opening' => (int) $item->stock_quantity,
            'intake' => 0,
            'export' => 0,
            'pending' => (int) $item->pending_sales_quantity,
            'closing' => (int) $item->stock_quantity,
            'available' => max(0, (int) $item->stock_quantity - (int) $item->pending_sales_quantity),
        ]);
    }

    private function inventoryPending(): Collection
    {
        return $this->inventoryDaily()->map(fn (array $r) => [
            'index' => $r['index'],
            'warehouse' => $r['warehouse'],
            'product' => $r['product'],
            'batch_code' => $r['batch_code'],
            'opening' => $r['opening'],
            'pending' => $r['pending'],
            'sold_export' => 0,
            'closing' => $r['closing'] + $r['pending'],
        ]);
    }

    private function inventorySummary(): Collection
    {
        return WarehouseInventory::query()->with(['warehouse:id,name', 'product:id,name'])->get()->groupBy(fn (WarehouseInventory $i) => $i->warehouse_id.'-'.$i->product_id)->values()->map(function (Collection $group, int $index): array {
            /** @var WarehouseInventory $first */
            $first = $group->first();
            return [
                'index' => $index + 1,
                'warehouse' => $first->warehouse?->name,
                'product' => $first->product?->name,
                'total_quantity' => $group->sum('stock_quantity'),
                'total_pending' => $group->sum('pending_sales_quantity'),
                'quantity' => $group->sum('stock_quantity'),
                'pending' => $group->sum('pending_sales_quantity'),
            ];
        });
    }

    private function careReport(): Collection
    {
        return User::query()->whereIn('role', [User::ROLE_WAREHOUSE, User::ROLE_SALES])->orderBy('name')->get()->values()->map(function (User $user, int $index): array {
            $orders = Order::query()->where('sale_user_id', $user->id)->get();
            $received = $orders->count();
            $success = $orders->where('delivery_status', 'delivered')->count();
            $returned = $orders->whereIn('delivery_status', ['returned', 'returning'])->count();
            return [
                'index' => $index + 1,
                'care_user' => $user->name,
                'received' => $received,
                'care_actions' => $orders->whereNotNull('operation_result')->count(),
                'caring' => $orders->whereNotNull('next_operation_at')->count(),
                'uncared' => max(0, $received - $orders->whereNotNull('operation_result')->count()),
                'success' => $success,
                'returned' => $returned,
                'success_rate' => round(($success / max(1, $received)) * 100, 2),
                'auto_success' => 0,
                'auto_return' => 0,
            ];
        });
    }

    private function phoneCorrections(): Collection
    {
        return ActivityLog::query()->where('action', 'like', '%phone%')->with(['actor:id,name,team_id', 'actor.team:id,name'])->latest('created_at')->limit(1000)->get()->groupBy('user_id')->values()->map(function (Collection $logs, int $index): array {
            /** @var ActivityLog $first */
            $first = $logs->first();
            return [
                'index' => $index + 1,
                'sale' => $first->actor?->name,
                'team' => $first->actor?->team?->name,
                'quantity' => $logs->count(),
                'export' => 'Xuất Excel',
            ];
        });
    }

    private function deliveryByCare(): Collection
    {
        return $this->careReport()->map(fn (array $r) => [
            'index' => $r['index'],
            'care_user' => $r['care_user'],
            'pending' => $r['uncared'],
            'shipping' => $r['caring'],
            'delivered' => $r['success'],
            'returned' => $r['returned'],
            'cancelled' => 0,
            'total' => $r['received'],
        ]);
    }

    private function careOperations(): Collection
    {
        return Order::query()->with('saleUser:id,name')->whereNotNull('operation_result')->latest('updated_at')->limit(2000)->get()->values()->map(fn (Order $order, int $index) => [
            'index' => $index + 1,
            'order_code' => $order->order_code,
            'care_user' => $order->saleUser?->name,
            'care_status' => $order->operation_result,
            'note' => $order->customer_note,
            'operated_at' => $order->updated_at?->toIso8601String(),
            'previous_status' => $order->operation_stage,
            'export' => 'Xuất Excel',
        ]);
    }

    private function careAllocation(): Collection
    {
        return User::query()->where('role', User::ROLE_WAREHOUSE)->orderBy('name')->get()->values()->map(fn (User $user) => [
            'care_user' => $user->name,
            'account' => $user->email,
            'contacts' => Order::query()->where('sale_user_id', $user->id)->count(),
            'receive_data' => data_get($user->permissions, 'care_receive_data', true),
            'active' => true,
        ]);
    }

    private function monthlyPlan(string $code): Collection
    {
        $generic = $this->genericRows($code);
        if ($generic->isNotEmpty()) {
            return $generic;
        }

        return User::query()->orderBy('name')->limit(200)->get()->values()->map(fn (User $user, int $index) => [
            'index' => $index + 1,
            'account' => $user->name,
            'role' => $user->roleLabel(),
            'kpi' => 'KPI tháng',
            'budget' => 0,
            'clicks' => 0,
            'contacts' => 0,
            'revenue_target' => 0,
            'actual_revenue' => Order::query()->where('sale_user_id', $user->id)->sum('total'),
            'working_days' => 26,
            'actual_days' => 0,
            'base_salary' => 0,
            'bonus' => 0,
            'income' => 0,
            'locked' => false,
            'updated_at' => $user->updated_at?->toIso8601String(),
        ]);
    }

    private function trend(): Collection
    {
        $orders = $this->recentOrders();
        return collect(range(6, 0))->map(function (int $offset) use ($orders): array {
            $day = now()->subDays($offset)->toDateString();
            $value = $orders->filter(fn (Order $o) => $o->closed_at?->toDateString() === $day)->sum(fn (Order $o) => $o->effectiveRevenue());
            $comparison = $orders->filter(fn (Order $o) => $o->closed_at?->toDateString() === now()->subDays($offset + 7)->toDateString())->sum(fn (Order $o) => $o->effectiveRevenue());
            return [
                'period' => CarbonImmutable::parse($day)->format('d/m/Y'),
                'value' => $value,
                'comparison' => $comparison,
                'change' => $comparison > 0 ? round((($value - $comparison) / $comparison) * 100, 2) : 0,
            ];
        });
    }

    private function allocationSummary(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(fn (array $r) => [
            'day' => now()->format('d/m/Y'),
            'sale' => $r['name'],
            'new_contacts' => $r['new_contacts'],
            'duplicate_contacts' => 0,
            'old_contacts' => $r['old_contacts'],
            'care' => 0,
            'manual' => 0,
            'team' => '',
        ]);
    }

    private function powerDashboard(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(fn (array $r) => [
            'account' => $r['name'],
            'contacts' => $r['contacts'],
            'closed' => $r['closed'],
            'rate' => round(($r['closed'] / max(1, $r['contacts'])) * 100, 2),
            'cost_per_contact' => 0,
            'budget_ratio' => 0,
            'revenue' => $r['revenue'],
        ]);
    }

    private function repurchase(): Collection
    {
        $counts = $this->recentOrders()->groupBy('customer_phone')->map->count();
        $buckets = [
            'purchase_1' => $counts->filter(fn ($v) => $v === 1)->count(),
            'purchase_2' => $counts->filter(fn ($v) => $v === 2)->count(),
            'purchase_3' => $counts->filter(fn ($v) => $v === 3)->count(),
            'purchase_n' => $counts->filter(fn ($v) => $v >= 4)->count(),
        ];
        return collect([
            ['metric' => 'Số khách hàng'] + $buckets,
            ['metric' => 'Tỷ lệ (%)', 'purchase_1' => round(($buckets['purchase_1'] / max(1, $counts->count())) * 100, 2), 'purchase_2' => round(($buckets['purchase_2'] / max(1, $counts->count())) * 100, 2), 'purchase_3' => round(($buckets['purchase_3'] / max(1, $counts->count())) * 100, 2), 'purchase_n' => round(($buckets['purchase_n'] / max(1, $counts->count())) * 100, 2)],
        ])->values()->map(fn ($row, $index) => ['index' => $index + 1] + $row);
    }

    private function repurchaseProducts(): Collection
    {
        $orders = $this->recentOrders()->loadMissing('items:id,order_id,quantity');
        $rows = [];
        foreach (range(1, 4) as $purchaseNo) {
            $row = ['purchase_no' => "Mua lần {$purchaseNo}"];
            foreach (range(1, 30) as $quantity) {
                $row["product_{$quantity}"] = $orders->filter(fn (Order $o) => $o->items->sum('quantity') === $quantity)->count();
            }
            $rows[] = $row;
        }
        return collect($rows);
    }

    private function allocationV2(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(fn (array $r) => [
            'day' => now()->format('d/m/Y'),
            'user' => $r['name'],
            'receive' => true,
            'quota' => max(10, $r['contacts']),
            'wave' => 1,
            'new_contacts' => $r['new_contacts'],
            'duplicate_new' => 0,
            'old_contacts' => $r['old_contacts'],
            'duplicate_old' => 0,
            'care' => 0,
        ]);
    }

    private function careAllocationDaily(): Collection
    {
        return $this->careAllocation()->map(fn (array $r) => [
            'day' => now()->format('d/m/Y'),
            'user' => $r['care_user'],
            'receive' => $r['receive_data'],
            'quota' => max(10, $r['contacts']),
            'wave' => 1,
            'new_contacts' => $r['contacts'],
        ]);
    }

    private function recentOrders(): Collection
    {
        return Order::query()->with(['team:id,name', 'saleUser:id,name', 'items:id,order_id,quantity,item_type'])->latest('data_arrived_at')->limit(5000)->get();
    }

    private function ordersGroupedBySale(): Collection
    {
        return $this->recentOrders()->groupBy(fn (Order $order) => $order->sale_user_id ?: 0)->map(function (Collection $orders): array {
            /** @var Order $first */
            $first = $orders->first();
            $closed = $orders->whereNotNull('closed_at');
            $new = $orders->where('is_returning_customer', false);
            $old = $orders->where('is_returning_customer', true);
            $stages = [];
            foreach ($orders as $order) {
                $key = match ((string) $order->operation_stage) {
                    'call_1', 'call1' => 'call_1',
                    'call_2', 'call2' => 'call_2',
                    'call_3', 'call3' => 'call_3',
                    'call_4', 'call4' => 'call_4',
                    'call_5', 'call5' => 'call_5',
                    'call_6', 'call6' => 'call_6',
                    'care_1', 'care1' => 'care_1',
                    'care_2', 'care2' => 'care_2',
                    'care_3', 'care3' => 'care_3',
                    'skipped', 'ignore' => 'skipped',
                    default => null,
                };
                if ($key) {
                    $stages[$key] = ($stages[$key] ?? 0) + 1;
                }
            }
            return [
                'name' => $first->saleUser?->name ?? 'Chưa phân sale',
                'contacts' => $orders->count(),
                'untouched' => $orders->filter(fn (Order $o) => blank($o->operation_stage) && blank($o->operation_result))->count(),
                'closed' => $closed->count(),
                'revenue' => $closed->sum(fn (Order $o) => $o->effectiveRevenue()),
                'new_contacts' => $new->count(),
                'new_closed' => $new->whereNotNull('closed_at')->count(),
                'new_revenue' => $new->whereNotNull('closed_at')->sum(fn (Order $o) => $o->effectiveRevenue()),
                'old_contacts' => $old->count(),
                'old_closed' => $old->whereNotNull('closed_at')->count(),
                'old_revenue' => $old->whereNotNull('closed_at')->sum(fn (Order $o) => $o->effectiveRevenue()),
                'stages' => $stages,
            ];
        });
    }

    private function applySearch(Collection $rows, string $search): Collection
    {
        if ($search === '') {
            return $rows->values();
        }

        $needle = Str::lower($search);
        return $rows->filter(function (array $row) use ($needle): bool {
            foreach ($row as $value) {
                if (is_scalar($value) && Str::contains(Str::lower((string) $value), $needle)) {
                    return true;
                }
            }
            return false;
        })->values();
    }
}
