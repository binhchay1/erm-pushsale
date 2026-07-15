<?php

namespace App\Services\Pushsale;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Models\Order;
use App\Models\Pushsale\CareDistributionRule;
use App\Services\Inventory\WarehouseInventoryService;
use App\Services\Operations\OrderOperationPresenter;
use App\Services\Operations\WarehouseOperationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Adapter dữ liệu thật cho các màn Pushsale được dựng lại từ HTML gốc.
 *
 * Lớp này không tạo bản ghi demo. Nó chỉ đọc từ các model/service nghiệp vụ
 * đang được những màn ERM hiện hữu sử dụng, rồi chuyển payload về đúng cột của
 * template Pushsale.
 */
final class PushsaleLiveDataService
{
    public function __construct(
        private readonly WarehouseInventoryService $warehouseInventory,
        private readonly WarehouseOperationService $warehouseOperations,
    ) {}

    /**
     * @return array{data: list<array<string,mixed>>, meta: array<string,int>, summary?: array<string,mixed>}|null
     */
    public function resolve(string $source, Request $request): ?array
    {
        return match ($source) {
            'customer_orders' => $this->customerOrders($request),
            'inventory' => $this->inventory($request),
            'warehouse_orders' => $this->warehouseOrders($request),
            'allocation_summary' => $this->allocationSummary($request),
            'allocation_v2' => $this->allocationV2($request),
            'care_allocation_daily' => $this->careAllocationDaily($request),
            default => null,
        };
    }

    /** @return array{data:list<array<string,mixed>>,meta:array<string,int>,summary:array<string,mixed>} */
    private function customerOrders(Request $request): array
    {
        $filterRequest = $this->normalizedReportRequest($request);
        $filter = ReportFilterData::fromRequest($filterRequest, $request->user());

        $query = Order::query()
            ->with([
                'items.product',
                'saleUser.team',
                'marketerUser.team',
                'marketingSource',
                'warehouse',
                'team',
                'supplementalOriginPacket.relatedOrder:id,order_code',
            ])
            ->withCount('pendingSupplementPackets')
            ->applyReportFilter($filter);

        $this->applyCustomerProfileFilters($query, $request);

        $paginator = $query
            ->orderByDesc('data_arrived_at')
            ->orderByDesc('id')
            ->paginate($filter->perPage, ['*'], 'page', $filter->page)
            ->withQueryString();

        $rows = collect($paginator->items())
            ->map(fn (Order $order): array => $this->presentCustomerOrder(OrderOperationPresenter::toArray($order)))
            ->values()
            ->all();

        $summaryQuery = clone $query;
        $summary = [
            'total_orders' => (int) $paginator->total(),
            'closed_orders' => (int) (clone $summaryQuery)->whereNotNull('closed_at')->count(),
            'total_revenue' => (int) (clone $summaryQuery)->whereNotNull('closed_at')->sum('total'),
            'upsell_orders' => (int) (clone $summaryQuery)->whereHas('items', fn (Builder $q) => $q->where('item_type', 'upsell'))->count(),
        ];

        return [
            'data' => $rows,
            'meta' => $this->paginatorMeta($paginator),
            'summary' => $summary,
        ];
    }

    private function applyCustomerProfileFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->integer('source_id'), fn (Builder $q, int $id) => $q->where('marketing_source_id', $id))
            ->when($request->integer('sale_team_id'), fn (Builder $q, int $id) => $q->where('team_id', $id))
            ->when($request->integer('sale_leader_id'), fn (Builder $q, int $id) => $q->whereHas('team', fn (Builder $team) => $team->where('leader_user_id', $id)))
            ->when($request->integer('marketer_team_id'), fn (Builder $q, int $id) => $q->whereHas('marketerUser', fn (Builder $user) => $user->where('team_id', $id)))
            ->when($request->integer('marketer_leader_id'), fn (Builder $q, int $id) => $q->whereHas('marketerUser.team', fn (Builder $team) => $team->where('leader_user_id', $id)))
            ->when($request->integer('product_id'), fn (Builder $q, int $id) => $q->whereHas('items', fn (Builder $items) => $items->where('product_id', $id)))
            ->when($request->filled('customer_type'), function (Builder $q) use ($request): void {
                $value = (string) $request->input('customer_type');
                if (in_array($value, ['0', '1'], true)) $q->where('is_returning_customer', $value === '1');
            })
            ->when($request->filled('duplicate_status'), function (Builder $q) use ($request): void {
                $value = (string) $request->input('duplicate_status');
                if (in_array($value, ['0', '1'], true)) $q->where('is_duplicate_phone', $value === '1');
            })
            ->when($request->filled('allocation_status'), function (Builder $q) use ($request): void {
                (string) $request->input('allocation_status') === '1'
                    ? $q->whereNotNull('sale_user_id')
                    : $q->whereNull('sale_user_id');
            })
            ->when($request->filled('care_operation_status'), function (Builder $q) use ($request): void {
                $value = (string) $request->input('care_operation_status');
                if ($value === '2') $q->whereNotNull('next_operation_at');
                elseif ($value === '0') $q->whereNull('next_operation_at');
            })
            ->when($request->filled('operation_state'), function (Builder $q) use ($request): void {
                $value = (string) $request->input('operation_state');
                if ($value === '1') {
                    $q->whereNull('operation_stage')->whereNull('operation_result');
                } elseif ($value === '2') {
                    $q->where(fn (Builder $inner) => $inner->whereNotNull('operation_stage')->orWhereNotNull('operation_result'));
                }
            })
            ->when($request->filled('closed_status'), function (Builder $q) use ($request): void {
                $value = (string) $request->input('closed_status');
                if ($value === '1') $q->whereNotNull('closed_at');
                elseif ($value === '0') $q->whereNull('closed_at');
            })
            ->when($request->filled('internal_reconciliation_status'), fn (Builder $q) => $q->where('reconciliation_status', $request->input('internal_reconciliation_status')));
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function presentCustomerOrder(array $row): array
    {
        $productLines = collect($row['products'] ?? [])->map(function (array $item): string {
            $suffix = ($item['itemType'] ?? 'product') === 'upsell' ? ' [UPSALE]' : '';
            return sprintf('%s x%s · %s%s', $item['productName'] ?? '—', $item['quantity'] ?? 0, $this->formatVnd((int) ($item['unitPrice'] ?? 0)), $suffix);
        });
        $isUpsell = collect($row['products'] ?? [])->contains(fn (array $item) => ($item['itemType'] ?? '') === 'upsell');

        return [
            'select' => false,
            'order_code' => $row['orderCode'] ?? '',
            'source' => trim(($row['sourceName'] ?? '—')."\n".$this->formatDateTime($row['dataArrivedAt'] ?? null)),
            'customer' => trim(($row['customerName'] ?? '')."\n".($row['customerPhone'] ?? '')),
            'address' => (string) ($row['effectiveShippingAddress'] ?? ''),
            'message' => (string) ($row['customerNote'] ?? ''),
            'sale' => trim(($row['saleName'] ?? '—')."\n".$this->formatDateTime($row['assignedAt'] ?? null)),
            'operation' => trim(($row['currentOperation'] ?? 'Khách mới')."\n".$this->formatDateTime($row['closedAt'] ?? null)),
            'result' => trim(($row['operationResult'] ?? '—')."\n".$this->formatDateTime($row['nextOperationAt'] ?? null)),
            'products' => $productLines->implode("\n"),
            'money' => implode("\n", [
                $this->formatVnd((int) ($row['subtotal'] ?? 0)),
                '-'.$this->formatVnd((int) ($row['discount'] ?? 0)),
                $this->formatVnd((int) ($row['vat'] ?? 0)),
                $this->formatVnd((int) ($row['shippingFeeCollected'] ?? 0)),
                $this->formatVnd((int) ($row['total'] ?? 0)),
            ]),
            'deposit' => (int) ($row['deposit'] ?? 0),
            'shipping' => trim(($row['warehouseName'] ?? '—')."\n".($row['shippingProvider'] ?? '—')."\n".($row['trackingNumber'] ?? '')),
            'delivery' => trim(($row['deliveryStatus'] ?? '—')."\n".$this->formatDate($row['desiredDeliveryAt'] ?? null)),
            'internal_note' => (string) ($row['internalReconNote'] ?? ''),
            'actions' => 'Lịch sử · Tin nhắn · Mua hàng',
            'is_upsell' => $isUpsell,
            '_order_id' => $row['id'] ?? null,
        ];
    }

    /** @return array{data:list<array<string,mixed>>,meta:array<string,int>,summary:array<string,mixed>} */
    private function inventory(Request $request): array
    {
        $result = $this->warehouseInventory->build($request);
        $paginator = $result['rows'];
        $rows = collect($paginator->items())->map(fn (array $row): array => [
            'select' => false,
            'id' => $row['id'],
            'warehouse' => $row['warehouseName'],
            'product' => trim($row['productName'].($row['sku'] ? " ({$row['sku']})" : '')),
            'uom' => $row['uom'],
            'batch_code' => $row['batchCode'],
            'expiry_date' => $row['expiryDate'],
            'location' => $row['locationCode'],
            'stock' => (int) $row['stockQuantity'],
            'pending' => (int) $row['pendingSalesQuantity'],
            'low_stock' => max(0, (int) $row['pendingSalesQuantity'] - (int) $row['stockQuantity']),
            'discontinued' => (bool) $row['isDiscontinued'],
            'updated_at' => null,
            'actions' => 'Chi tiết',
        ])->values()->all();

        return [
            'data' => $rows,
            'meta' => $this->paginatorMeta($paginator),
            'summary' => [
                'total_items' => (int) $paginator->total(),
                'stock_quantity' => (int) collect($paginator->items())->sum('stockQuantity'),
                'pending_quantity' => (int) collect($paginator->items())->sum('pendingSalesQuantity'),
            ],
        ];
    }

    /** @return array{data:list<array<string,mixed>>,meta:array<string,int>,summary:array<string,mixed>} */
    private function warehouseOrders(Request $request): array
    {
        $filter = ReportFilterData::fromRequest($this->normalizedReportRequest($request), $request->user());
        $result = $this->warehouseOperations->build($filter);
        $collection = collect($result['rows'] ?? []);
        $page = max(1, $request->integer('page', 1));
        $perPage = min(100, max(10, $request->integer('per_page', 20)));
        $total = $collection->count();
        $rows = $collection->slice(($page - 1) * $perPage, $perPage)->values()->map(function (array $row): array {
            $products = collect($row['products'] ?? [])->map(fn (array $item) => trim(($item['productName'] ?? '—').' '.(($item['sku'] ?? '') ? "({$item['sku']})" : '').' x'.($item['quantity'] ?? 0)))->implode("\n");
            return [
                'select' => false,
                'sale' => '',
                'order_info' => trim(($row['orderCode'] ?? '')."\n".$this->formatDateTime($row['closedAt'] ?? null)),
                'shipping' => trim(($row['shippingProviderLabel'] ?? $row['shippingProvider'] ?? '—')."\n".($row['trackingNumber'] ?? '')),
                'care' => '',
                'delivery' => (string) ($row['deliveryStatus'] ?? ''),
                'customer' => trim(($row['effectiveReceiverName'] ?? $row['customerName'] ?? '')."\n".($row['effectiveReceiverPhone'] ?? $row['customerPhone'] ?? '')),
                'address' => (string) ($row['shippingAddress'] ?? ''),
                'products' => $products,
                'money' => $this->formatVnd((int) ($row['codAmount'] ?? 0)),
                'deposit' => 0,
                'collect' => (int) ($row['codAmount'] ?? 0),
                'carrier_fee' => 0,
                'shipping_support' => 0,
                'internal_note' => (string) ($row['customerNote'] ?? ''),
                'actions' => collect($row)->only(['canCreateShipment', 'canPrintLabel', 'canReceiveReturn'])->filter()->keys()->implode(' · '),
                '_order_id' => $row['id'] ?? null,
            ];
        })->all();

        return [
            'data' => $rows,
            'meta' => $this->meta($page, $perPage, $total),
            'summary' => [
                'total_orders' => $total,
                'cod_amount' => (int) $collection->sum('codAmount'),
                'insufficient_stock' => $collection->where('hasInsufficientStock', true)->count(),
            ],
        ];
    }

    /**
     * Báo cáo chia data theo ngày/sale dựa trên chính các đơn đã được gán sale.
     * Không dựng wave, định mức hoặc số lượng giả từ ảnh template.
     *
     * @return array{data:list<array<string,mixed>>,meta:array<string,int>,summary:array<string,mixed>}
     */
    private function allocationSummary(Request $request): array
    {
        [$orders, $filter] = $this->allocationOrders($request);

        $rows = $orders
            ->groupBy(fn (Order $order): string => ($order->assigned_at?->toDateString() ?? 'unknown').'|'.($order->sale_user_id ?? 0))
            ->map(function (Collection $items): array {
                /** @var Order $first */
                $first = $items->first();
                $duplicates = $items->where('is_duplicate_phone', true);
                $unique = $items->where('is_duplicate_phone', false);
                $new = $unique->where('is_returning_customer', false);
                $old = $unique->where('is_returning_customer', true);
                $care = $items->filter(fn (Order $order): bool => str_starts_with((string) $order->operation_stage, 'care_'));
                $manual = $items->filter(fn (Order $order): bool => $order->leadPackets->contains(fn ($packet): bool => in_array((string) $packet->platform, ['manual', 'excel'], true)));

                return [
                    'day' => $first->assigned_at?->format('d/m/Y') ?? '—',
                    'sale' => $first->saleUser?->name ?? 'Chưa phân sale',
                    'new_contacts' => $new->count(),
                    'duplicate_contacts' => $duplicates->count(),
                    'old_contacts' => $old->count(),
                    'care' => $care->count(),
                    'manual' => $manual->count(),
                    'team' => $first->team?->name ?? $first->saleUser?->team?->name ?? '—',
                ];
            })
            ->sortByDesc(fn (array $row): string => $row['day'].'|'.$row['sale'])
            ->values();

        return $this->paginateRows($rows, $filter->page, $filter->perPage, [
            'total_allocated' => $orders->count(),
            'unique_contacts' => $orders->where('is_duplicate_phone', false)->count(),
            'duplicate_contacts' => $orders->where('is_duplicate_phone', true)->count(),
        ]);
    }

    /**
     * Phiên bản V2 vẫn lấy số được chia từ đơn thật. Các trường hệ thống chưa lưu
     * (wave/định mức sale) trả null thay vì bịa dữ liệu.
     *
     * @return array{data:list<array<string,mixed>>,meta:array<string,int>,summary:array<string,mixed>}
     */
    private function allocationV2(Request $request): array
    {
        [$orders, $filter] = $this->allocationOrders($request);

        $rows = $orders
            ->groupBy(fn (Order $order): string => ($order->assigned_at?->toDateString() ?? 'unknown').'|'.($order->sale_user_id ?? 0))
            ->map(function (Collection $items): array {
                /** @var Order $first */
                $first = $items->first();
                $new = $items->where('is_returning_customer', false);
                $old = $items->where('is_returning_customer', true);

                return [
                    'day' => $first->assigned_at?->format('d/m/Y') ?? '—',
                    'user' => $first->saleUser?->name ?? 'Chưa phân sale',
                    'receive' => (bool) data_get($first->saleUser?->permissions, 'receive_data', true),
                    'quota' => data_get($first->saleUser?->permissions, 'lead_quota'),
                    'wave' => null,
                    'new_contacts' => $new->where('is_duplicate_phone', false)->count(),
                    'duplicate_new' => $new->where('is_duplicate_phone', true)->count(),
                    'old_contacts' => $old->where('is_duplicate_phone', false)->count(),
                    'duplicate_old' => $old->where('is_duplicate_phone', true)->count(),
                    'care' => $items->filter(fn (Order $order): bool => str_starts_with((string) $order->operation_stage, 'care_'))->count(),
                ];
            })
            ->sortByDesc(fn (array $row): string => $row['day'].'|'.$row['user'])
            ->values();

        return $this->paginateRows($rows, $filter->page, $filter->perPage, [
            'total_allocated' => $orders->count(),
            'sales' => $orders->pluck('sale_user_id')->filter()->unique()->count(),
        ]);
    }

    /**
     * Cấu hình care lấy từ care_distribution_rules; số đang có lấy từ đơn thật
     * theo sale/care user hiện có. Không tự đặt quota tối thiểu 10 hoặc wave=1.
     *
     * @return array{data:list<array<string,mixed>>,meta:array<string,int>,summary:array<string,mixed>}
     */
    private function careAllocationDaily(Request $request): array
    {
        $filter = ReportFilterData::fromRequest($this->allocationRequest($request), $request->user());
        $rules = CareDistributionRule::query()
            ->with('careUser:id,name,email,permissions')
            ->orderBy('id')
            ->get();

        $counts = Order::query()
            ->whereNotNull('sale_user_id')
            ->whereBetween('assigned_at', [$filter->dateFrom, $filter->dateTo])
            ->selectRaw('sale_user_id, DATE(assigned_at) as allocation_day, COUNT(*) as contacts')
            ->groupBy('sale_user_id', 'allocation_day')
            ->get()
            ->groupBy('sale_user_id');

        $rows = $rules->flatMap(function (CareDistributionRule $rule) use ($counts, $filter): Collection {
            $userRows = $counts->get($rule->care_user_id, collect());

            if ($userRows->isEmpty()) {
                return collect([[
                    'day' => $filter->dateTo?->format('d/m/Y') ?? now()->format('d/m/Y'),
                    'user' => $rule->careUser?->name ?? '—',
                    'receive' => (bool) $rule->receive_data,
                    'quota' => (int) $rule->quota,
                    'wave' => null,
                    'new_contacts' => 0,
                ]]);
            }

            return $userRows->map(fn ($count): array => [
                'day' => \Carbon\CarbonImmutable::parse($count->allocation_day)->format('d/m/Y'),
                'user' => $rule->careUser?->name ?? '—',
                'receive' => (bool) $rule->receive_data,
                'quota' => (int) $rule->quota,
                'wave' => null,
                'new_contacts' => (int) $count->contacts,
            ]);
        })->sortByDesc(fn (array $row): string => $row['day'].'|'.$row['user'])->values();

        return $this->paginateRows($rows, $filter->page, $filter->perPage, [
            'care_users' => $rules->count(),
            'receive_enabled' => $rules->where('receive_data', true)->count(),
            'allocated_contacts' => $rows->sum('new_contacts'),
        ]);
    }

    /** @return array{0:Collection<int,Order>,1:ReportFilterData} */
    private function allocationOrders(Request $request): array
    {
        $filter = ReportFilterData::fromRequest($this->allocationRequest($request), $request->user());
        $orders = Order::query()
            ->with([
                'saleUser:id,name,email,team_id,permissions',
                'saleUser.team:id,name',
                'team:id,name',
                'leadPackets:id,order_id,platform',
            ])
            ->whereNotNull('sale_user_id')
            ->whereNotNull('assigned_at')
            ->applyReportFilter($filter)
            ->orderByDesc('assigned_at')
            ->get([
                'id', 'sale_user_id', 'team_id', 'assigned_at', 'is_returning_customer',
                'is_duplicate_phone', 'operation_stage', 'data_arrived_at', 'closed_at',
            ]);

        return [$orders, $filter];
    }

    private function allocationRequest(Request $request): Request
    {
        $clone = Request::create($request->fullUrl(), $request->method(), array_merge($request->all(), [
            'date_type' => 'sale_received_data',
            'team_id' => $request->input('team_id', $request->input('sale_team_id')),
            'team_leader_id' => $request->input('team_leader_id', $request->input('sale_leader_id')),
        ]));
        $clone->setUserResolver(fn () => $request->user());
        $clone->setRouteResolver(fn () => $request->route());

        return $clone;
    }

    /**
     * @param Collection<int,array<string,mixed>> $rows
     * @param array<string,mixed> $summary
     * @return array{data:list<array<string,mixed>>,meta:array<string,int>,summary:array<string,mixed>}
     */
    private function paginateRows(Collection $rows, int $page, int $perPage, array $summary): array
    {
        $total = $rows->count();

        return [
            'data' => $rows->slice(($page - 1) * $perPage, $perPage)->values()->all(),
            'meta' => $this->meta($page, $perPage, $total),
            'summary' => $summary,
        ];
    }

    private function normalizedReportRequest(Request $request): Request
    {
        $clone = Request::create($request->fullUrl(), $request->method(), array_merge($request->all(), [
            'team_id' => $request->input('team_id', $request->input('sale_team_id')),
            'team_leader_id' => $request->input('team_leader_id', $request->input('sale_leader_id')),
            'marketing_team_id' => $request->input('marketing_team_id', $request->input('marketer_team_id')),
            'marketing_team_leader_id' => $request->input('marketing_team_leader_id', $request->input('marketer_leader_id')),
            'reconciliation_status' => $request->input('reconciliation_status', $request->input('internal_reconciliation_status')),
            'closing_status' => $this->closingStatus($request),
            'date_type' => $this->dateType((string) $request->input('date_type', '')),
            'delivery_status' => $this->deliveryStatus((string) $request->input('delivery_status', '')),
            'operation_stage' => $this->operationStage((string) $request->input('operation_stage', '')),
            'operation_result' => $this->operationResult((string) $request->input('operation_result', '')),
            'shipping_method' => $this->shippingMethod((string) $request->input('shipping_method', '')),
        ]));
        $clone->setUserResolver(fn () => $request->user());
        $clone->setRouteResolver(fn () => $request->route());
        return $clone;
    }

    private function closingStatus(Request $request): ?string
    {
        if ($request->filled('closing_status')) return (string) $request->input('closing_status');
        if (! $request->filled('closed_status')) return null;
        return (string) $request->input('closed_status') === '1' ? 'closed' : 'open';
    }

    private function dateType(string $value): string
    {
        return match ($value) {
            'SaleNgayNhanData' => 'sale_received_data',
            'DonHangNgayChot', 'NgayDangDon' => 'closing_date',
            'SaleTacNghiepNgayCapNhat', 'NgayChoXuat', 'NgayCapNhatTrangThaiGiaoHang', 'NgayGiaoHang' => 'care_update',
            'data_arrival', 'sale_received_data', 'closing_date', 'care_update' => $value,
            default => 'data_arrival',
        };
    }

    private function deliveryStatus(string $value): ?string
    {
        if ($value === '' || in_array($value, ['-1', 'all'], true)) return null;
        return match ($value) {
            '1' => DeliveryStatus::WaitingWaybill->value,
            '2' => DeliveryStatus::DeliverNow->value,
            '4' => DeliveryStatus::CancelWaybill->value,
            '5' => DeliveryStatus::CancelClosing->value,
            '20' => DeliveryStatus::Posted->value,
            '21', '23' => DeliveryStatus::PickingUp->value,
            '22' => DeliveryStatus::CannotPickup->value,
            '30' => DeliveryStatus::Delivering->value,
            '31' => DeliveryStatus::Delivered->value,
            '32' => DeliveryStatus::Paid->value,
            '33' => DeliveryStatus::CannotDeliver->value,
            '34' => DeliveryStatus::Redelivery->value,
            '40' => DeliveryStatus::Returning->value,
            '41' => DeliveryStatus::Returned->value,
            '50' => DeliveryStatus::Refund->value,
            default => $value,
        };
    }

    private function operationStage(string $value): ?string
    {
        if ($value === '' || in_array($value, ['-1', 'all'], true)) return null;
        return match ($value) {
            '102133' => OperationStage::NewCustomer->value,
            '102134' => OperationStage::Call2->value,
            '102135' => OperationStage::Call3->value,
            '102136' => OperationStage::Call4->value,
            '102137' => OperationStage::Call5->value,
            '102138' => OperationStage::Call6->value,
            '102139' => OperationStage::Care1->value,
            '102140' => OperationStage::Care2->value,
            '102141' => OperationStage::Care3->value,
            '102142' => OperationStage::Skipped->value,
            default => $value,
        };
    }

    private function operationResult(string $value): ?string
    {
        if ($value === '' || in_array($value, ['-1', 'all'], true)) return null;
        return match ($value) {
            '109117' => OperationResult::ClosedSuccess->value,
            '109118', '109119' => OperationResult::NoAnswer1->value,
            '109120' => OperationResult::CallbackScheduled->value,
            '109121', '109122' => OperationResult::WrongNumber->value,
            '109123' => OperationResult::NoContact->value,
            '109124' => OperationResult::Considering->value,
            '109125' => OperationResult::NoNeed->value,
            '109128' => OperationResult::ReadyToClose->value,
            default => $value,
        };
    }

    private function shippingMethod(string $value): ?string
    {
        if ($value === '' || in_array($value, ['-1', 'all'], true)) return null;
        return match ($value) {
            '1' => 'giao hàng tiết kiệm',
            '2' => 'thủ công',
            '3' => 'viettel post',
            '4' => 'giao hàng nhanh',
            '7' => 'jnt',
            '8' => 'ems',
            '9' => 'supership',
            '10' => 'best',
            '12' => 'boxme',
            '14' => 'ship60',
            '15' => 'holaship',
            '16' => 'ahamove',
            '17' => 'shopee',
            '18' => 'ninjavan',
            '19' => 'tiktok',
            '20' => 'spx',
            default => $value,
        };
    }

    /** @return array<string,int> */
    private function paginatorMeta(object $paginator): array
    {
        return [
            'current_page' => (int) $paginator->currentPage(),
            'last_page' => max(1, (int) $paginator->lastPage()),
            'per_page' => (int) $paginator->perPage(),
            'total' => (int) $paginator->total(),
            'from' => (int) ($paginator->firstItem() ?? 0),
            'to' => (int) ($paginator->lastItem() ?? 0),
        ];
    }

    /** @return array<string,int> */
    private function meta(int $page, int $perPage, int $total): array
    {
        return [
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / max(1, $perPage))),
            'per_page' => $perPage,
            'total' => $total,
            'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
            'to' => min($page * $perPage, $total),
        ];
    }

    private function formatVnd(int|float|string|null $value): string
    {
        return number_format((int) round((float) ($value ?? 0)), 0, ',', '.').' ₫';
    }

    private function formatDateTime(mixed $value): string
    {
        if (! $value) return '';
        try { return \Carbon\CarbonImmutable::parse($value)->format('d/m/Y H:i:s'); }
        catch (\Throwable) { return (string) $value; }
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) return '';
        try { return \Carbon\CarbonImmutable::parse($value)->format('d/m/Y'); }
        catch (\Throwable) { return (string) $value; }
    }
}
