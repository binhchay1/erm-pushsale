<?php

namespace App\Services\Demo;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\CustomerInternalMessage;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderOperationHistory;
use App\Models\Product;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\Pushsale\UserOperationalProfile;
use App\Models\Pushsale\WarehouseIncidentReport;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Pushsale\WarehouseVoucherLine;
use App\Models\SaleOptimizationAlertThreshold;
use App\Models\SaleOptimizationLevel;
use App\Models\SaleOptimizationTarget;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Services\CustomerInteractions\CustomerIdentity;
use App\Services\Tenant\CompanyProvisioningService;
use App\Support\TenantManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seed / purge dữ liệu demo có gắn nhãn cho UI sale, thủ kho, hồ sơ khách hàng.
 * Chỉ đụng bản ghi mang prefix/tag UXDEMO — không xóa dữ liệu nghiệp vụ khác.
 */
final class WorkspaceUiDemoService
{
    public const BATCH = 'ux-workspace-demo';

    public const ORDER_PREFIX = 'UXDEMO-';

    public const PHONE_PREFIX = '098870';

    public const NOTE_TAG = 'UXDEMO-BATCH';

    public const VOUCHER_PREFIX = 'UXDEMO-PN-';

    public const HANDOVER_PREFIX = 'UXDEMO BB ';

    public const LEAD_PREFIX = 'uxdemo-';

    public const SALE_EMAIL_PREFIX = 'uxdemo.sale';

    /** @return array{orders:int, leads:int, histories:int, messages:int, vouchers:int, handovers:int, movements:int, sales:int, kpi_plans:int} */
    public function seed(?int $companyId = null): array
    {
        $companyId = $companyId ?: (int) (CompanyProvisioningService::internalCompany()?->id ?? 0);
        if ($companyId <= 0) {
            throw new \RuntimeException('Không tìm thấy company nội bộ để seed demo.');
        }

        return app(TenantManager::class)->forCompany($companyId, function () use ($companyId): array {
            $this->purge();

            $users = User::query()->withoutGlobalScopes()->where(function ($q) use ($companyId): void {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })->orderBy('id')->get();

            if ($users->isEmpty()) {
                $users = User::query()->withoutGlobalScopes()->orderBy('id')->limit(20)->get();
            }

            $sale = $users->first(fn (User $u) => $u->role === UserRole::Sales)
                ?? $users->first(fn (User $u) => $u->role === UserRole::Admin)
                ?? $users->first();
            $warehouseUser = $users->first(fn (User $u) => $u->role === UserRole::Warehouse)
                ?? $users->first(fn (User $u) => $u->role === UserRole::Admin)
                ?? $sale;
            $admin = $users->first(fn (User $u) => $u->role === UserRole::Admin)
                ?? $users->first(fn (User $u) => (bool) $u->is_platform_admin)
                ?? $sale;

            if (! $sale) {
                throw new \RuntimeException('Thiếu user — cần ít nhất 1 tài khoản trong DB.');
            }

            foreach ([$sale, $warehouseUser, $admin] as $actor) {
                if ($actor && ! $actor->company_id) {
                    $actor->forceFill(['company_id' => $companyId])->save();
                }
            }

            $sales = $this->ensureDemoSales($companyId, $sale);
            $product = Product::query()->where('is_active', true)->where('type', 'product')->orderBy('id')->first()
                ?? Product::query()->orderBy('id')->first()
                ?? $this->ensureDemoProduct($companyId, $admin ?? $sale);

            $warehouse = Warehouse::query()->orderBy('id')->first()
                ?? $this->ensureDemoWarehouse($companyId);

            $scenarios = array_merge($this->saleScenarios(), $this->reportScenarios());
            $orders = [];

            foreach ($scenarios as $index => $scenario) {
                $saleUser = $sales[$index % count($sales)];
                $orders[] = $this->createSaleOrder(
                    companyId: $companyId,
                    sale: $saleUser,
                    product: $product,
                    warehouse: $warehouse,
                    index: $index + 1,
                    scenario: $scenario,
                );
            }

            $this->seedCustomerProfiles($orders, $sale, $warehouseUser ?? $admin, $admin);
            $this->seedReportConfig($companyId, $sales);
            $this->seedCustomer360($companyId, $orders, $admin);
            $voucherCount = $this->seedWarehouse($warehouse, $product, $warehouseUser ?? $admin, $orders);
            $handoverCount = $this->seedHandovers($warehouseUser ?? $admin, $orders);
            $movementCount = $this->seedInventoryMovements($warehouse, $product, $warehouseUser ?? $admin, $orders);

            return [
                'orders' => count($orders),
                'leads' => LeadIngestion::query()->where('external_id', 'like', self::LEAD_PREFIX.'%')->count(),
                'histories' => OrderOperationHistory::query()->where('note', 'like', self::NOTE_TAG.'%')->count(),
                'messages' => CustomerInternalMessage::query()->where('message', 'like', '[UXDEMO]%')->count(),
                'vouchers' => $voucherCount,
                'handovers' => $handoverCount,
                'movements' => $movementCount,
                'sales' => count($sales),
                'kpi_plans' => MonthlyKpiPlan::query()->where('kpi_name', 'like', 'UXDEMO%')->count(),
                'care_campaigns' => \App\Models\Pushsale\CustomerCareCampaign::query()->where('name', 'like', 'UXDEMO%')->count(),
            ];
        });
    }

    /** @return array{orders:int, leads:int, histories:int, messages:int, vouchers:int, handovers:int, movements:int} */
    public function purge(?int $companyId = null): array
    {
        $companyId = $companyId ?: (int) (CompanyProvisioningService::internalCompany()?->id ?? 0);
        if ($companyId <= 0) {
            return $this->emptyCounts();
        }

        return app(TenantManager::class)->forCompany($companyId, function (): array {
            return DB::transaction(function (): array {
                $orderIds = Order::query()
                    ->where('order_code', 'like', self::ORDER_PREFIX.'%')
                    ->pluck('id');

                $counts = [
                    'orders' => $orderIds->count(),
                    'leads' => LeadIngestion::query()->where('external_id', 'like', self::LEAD_PREFIX.'%')->count(),
                    'histories' => OrderOperationHistory::query()
                        ->where(function ($q) use ($orderIds): void {
                            $q->where('note', 'like', self::NOTE_TAG.'%');
                            if ($orderIds->isNotEmpty()) {
                                $q->orWhereIn('order_id', $orderIds);
                            }
                        })
                        ->count(),
                    'messages' => CustomerInternalMessage::query()
                        ->where(function ($q) use ($orderIds): void {
                            $q->where('message', 'like', '[UXDEMO]%')
                                ->orWhere('customer_phone', 'like', self::PHONE_PREFIX.'%');
                            if ($orderIds->isNotEmpty()) {
                                $q->orWhereIn('order_id', $orderIds);
                            }
                        })
                        ->count(),
                    'vouchers' => WarehouseVoucher::query()->where('code', 'like', self::VOUCHER_PREFIX.'%')->count(),
                    'handovers' => WarehouseIncidentReport::query()->where('name', 'like', self::HANDOVER_PREFIX.'%')->count(),
                    'movements' => WarehouseInventoryMovement::query()->where('note', 'like', self::NOTE_TAG.'%')->count(),
                ];

                CustomerInternalMessage::query()
                    ->where(function ($q) use ($orderIds): void {
                        $q->where('message', 'like', '[UXDEMO]%')
                            ->orWhere('customer_phone', 'like', self::PHONE_PREFIX.'%');
                        if ($orderIds->isNotEmpty()) {
                            $q->orWhereIn('order_id', $orderIds);
                        }
                    })
                    ->delete();

                OrderOperationHistory::query()
                    ->where(function ($q) use ($orderIds): void {
                        $q->where('note', 'like', self::NOTE_TAG.'%');
                        if ($orderIds->isNotEmpty()) {
                            $q->orWhereIn('order_id', $orderIds);
                        }
                    })
                    ->delete();

                LeadIngestion::query()->where('external_id', 'like', self::LEAD_PREFIX.'%')->delete();

                if ($orderIds->isNotEmpty()) {
                    OrderItem::query()->whereIn('order_id', $orderIds)->delete();
                    Order::query()->whereIn('id', $orderIds)->delete();
                }

                $voucherIds = WarehouseVoucher::query()->where('code', 'like', self::VOUCHER_PREFIX.'%')->pluck('id');
                if ($voucherIds->isNotEmpty()) {
                    WarehouseVoucherLine::query()->whereIn('warehouse_voucher_id', $voucherIds)->delete();
                    WarehouseVoucher::query()->whereIn('id', $voucherIds)->delete();
                }

                WarehouseIncidentReport::query()->where('name', 'like', self::HANDOVER_PREFIX.'%')->delete();
                WarehouseInventoryMovement::query()->where('note', 'like', self::NOTE_TAG.'%')->delete();

                MonthlyKpiPlan::query()->where('kpi_name', 'like', 'UXDEMO%')->forceDelete();
                \App\Models\Pushsale\CustomerCareCampaign::query()->where('name', 'like', 'UXDEMO%')->forceDelete();
                \App\Models\CustomerSegmentAssignment::query()->where('phone_key', 'like', self::PHONE_PREFIX.'%')->delete();

                $demoSaleIds = User::query()->withoutGlobalScopes()
                    ->where('email', 'like', self::SALE_EMAIL_PREFIX.'%@%')
                    ->pluck('id');

                if (Schema::hasTable('sale_optimization_targets') && $demoSaleIds->isNotEmpty()) {
                    SaleOptimizationTarget::query()->whereIn('sale_user_id', $demoSaleIds)->delete();
                }
                if (Schema::hasTable('sale_optimization_levels')) {
                    SaleOptimizationLevel::query()->where('label', 'like', 'UXDEMO%')->delete();
                }
                if (Schema::hasTable('sale_optimization_alert_thresholds')) {
                    SaleOptimizationAlertThreshold::query()
                        ->where('metric_key', 'uxdemo_close_rate')
                        ->delete();
                }

                if ($demoSaleIds->isNotEmpty()) {
                    UserOperationalProfile::query()->whereIn('user_id', $demoSaleIds)->delete();
                }
                UserOperationalProfile::query()
                    ->where('employee_code', 'like', 'UXDEMO-%')
                    ->orWhere('employee_code', 'like', 'UX0%')
                    ->delete();

                User::query()->withoutGlobalScopes()
                    ->where('email', 'like', self::SALE_EMAIL_PREFIX.'%@%')
                    ->delete();

                // Chỉ xóa SP/kho bootstrap nếu đúng mã UXDEMO (không đụng catalog thật).
                Product::query()->where('sku', 'UXDEMO-SP-01')->delete();
                Warehouse::query()->where('code', 'UXDEMO-KHO')->delete();

                return $counts;
            });
        });
    }

    /** @return list<array<string, mixed>> */
    private function saleScenarios(): array
    {
        return [
            [
                'name' => 'Nguyễn Văn UX Demo Mới',
                'stage' => OperationStage::NewCustomer,
                'result' => OperationResult::NoContact,
                'closing' => ClosingStatus::Open,
                'delivery' => null,
                'note' => 'Chưa liên lạc được — cần gọi lại.',
            ],
            [
                'name' => 'Trần Thị UX Demo Hẹn gọi',
                'stage' => OperationStage::Call2,
                'result' => OperationResult::CallbackScheduled,
                'closing' => ClosingStatus::Open,
                'delivery' => null,
                'note' => 'Khách hẹn gọi lại chiều nay.',
            ],
            [
                'name' => 'Lê Hoàng UX Demo Cân nhắc',
                'stage' => OperationStage::Call3,
                'result' => OperationResult::Considering,
                'closing' => ClosingStatus::Open,
                'delivery' => null,
                'note' => 'Đang cân nhắc giá / so sánh.',
            ],
            [
                'name' => 'Phạm Thu UX Demo Đã chốt',
                'stage' => OperationStage::Call2,
                'result' => OperationResult::ReceivedOrder,
                'closing' => ClosingStatus::Closed,
                'delivery' => DeliveryStatus::WaitingWaybill,
                'note' => 'Đã chốt, chờ kho tạo vận đơn.',
            ],
            [
                'name' => 'Ngô Văn UX Demo Giao ngay',
                'stage' => OperationStage::Call2,
                'result' => OperationResult::ReceivedOrder,
                'closing' => ClosingStatus::Closed,
                'delivery' => DeliveryStatus::DeliverNow,
                'note' => 'Chốt giao ngay — kho ưu tiên.',
            ],
            [
                'name' => 'Hoàng Minh UX Demo Đang giao',
                'stage' => OperationStage::Call2,
                'result' => OperationResult::ReceivedOrder,
                'closing' => ClosingStatus::Closed,
                'delivery' => DeliveryStatus::Delivering,
                'note' => 'Đã xuất kho, đang giao.',
            ],
            [
                'name' => 'Lý Văn UX Demo Đã thanh toán',
                'stage' => OperationStage::Care1,
                'result' => OperationResult::GoodEffect,
                'closing' => ClosingStatus::Closed,
                'delivery' => DeliveryStatus::Paid,
                'note' => 'Đã giao và thanh toán COD.',
            ],
            [
                'name' => 'Vũ Thị UX Demo Giao xong',
                'stage' => OperationStage::Care1,
                'result' => OperationResult::GoodEffect,
                'closing' => ClosingStatus::Closed,
                'delivery' => DeliveryStatus::Delivered,
                'note' => 'Giao thành công — chăm sóc sau bán.',
            ],
            [
                'name' => 'Đặng Quốc UX Demo Hoàn',
                'stage' => OperationStage::Call3,
                'result' => OperationResult::NotReceivedOrder,
                'closing' => ClosingStatus::Closed,
                'delivery' => DeliveryStatus::Returned,
                'note' => 'Khách từ chối nhận — hàng hoàn về kho.',
            ],
            [
                'name' => 'Bùi Thanh UX Demo Sai SĐT',
                'stage' => OperationStage::Call2,
                'result' => OperationResult::WrongNumber,
                'closing' => ClosingStatus::Cancelled,
                'delivery' => DeliveryStatus::CancelClosing,
                'note' => 'Sai số — hủy đơn.',
            ],
        ];
    }

    /**
     * Scenario bổ sung để bảng báo cáo 4.6.x có đủ stage, kỳ ngày, trùng SĐT, chưa TN.
     *
     * @return list<array<string, mixed>>
     */
    private function reportScenarios(): array
    {
        $stages = ['call_1', 'call_2', 'call_3', 'call_4', 'call_5', 'call_6', 'care_1', 'care_2', 'care_3', 'skipped'];
        $rows = [];

        foreach ($stages as $i => $stage) {
            $rows[] = [
                'name' => 'UX Report Stage '.Str::upper($stage),
                'stage' => $stage,
                'result' => $i % 2 === 0 ? OperationResult::ReceivedOrder : OperationResult::Considering,
                'closing' => $i % 2 === 0 ? ClosingStatus::Closed : ClosingStatus::Open,
                'delivery' => $i % 2 === 0 ? DeliveryStatus::Delivered : DeliveryStatus::WaitingWaybill,
                'note' => 'Seed báo cáo stage '.$stage,
                'day_offset' => $i % 3,
                'duplicate' => $i === 1,
                'returning' => $i >= 7,
                'untouched' => false,
                'discount' => $i * 5_000,
                'cod_fee' => 15_000,
                'cod_support' => $i % 3 === 0 ? 10_000 : 0,
                'deposit' => $i === 0 ? 50_000 : 0,
            ];
        }

        foreach ([0, 1, 2] as $i) {
            $rows[] = [
                'name' => 'UX Report Chưa TN #'.($i + 1),
                'stage' => null,
                'result' => null,
                'closing' => ClosingStatus::Open,
                'delivery' => DeliveryStatus::WaitingWaybill,
                'note' => 'Contact chưa tác nghiệp',
                'day_offset' => 0,
                'untouched' => true,
                'duplicate' => false,
                'returning' => false,
            ];
        }

        $rows[] = [
            'name' => 'UX Report Hôm qua chốt',
            'stage' => 'call_1',
            'result' => OperationResult::ReceivedOrder,
            'closing' => ClosingStatus::Closed,
            'delivery' => DeliveryStatus::Paid,
            'note' => 'Đơn chốt hôm qua',
            'anchor' => 'yesterday',
            'duplicate' => false,
            'returning' => false,
        ];
        $rows[] = [
            'name' => 'UX Report Tháng trước',
            'stage' => 'call_2',
            'result' => OperationResult::ReceivedOrder,
            'closing' => ClosingStatus::Closed,
            'delivery' => DeliveryStatus::Delivered,
            'note' => 'Đơn chốt tháng trước',
            'anchor' => 'last_month',
            'duplicate' => true,
            'returning' => true,
        ];
        $rows[] = [
            'name' => 'UX Report Hủy VĐ',
            'stage' => 'call_3',
            'result' => OperationResult::NotReceivedOrder,
            'closing' => ClosingStatus::Cancelled,
            'delivery' => DeliveryStatus::CancelWaybill,
            'note' => 'Hủy vận đơn — card 4.6.3',
            'day_offset' => 0,
        ];

        return $rows;
    }

    /** @return list<User> */
    private function ensureDemoSales(int $companyId, User $template): array
    {
        $sales = [];
        for ($i = 1; $i <= 3; $i++) {
            $email = self::SALE_EMAIL_PREFIX."{$i}@salesloop.local";
            $user = User::query()->withoutGlobalScopes()->where('email', $email)->first();
            if (! $user) {
                $user = User::query()->create([
                    'company_id' => $companyId,
                    'name' => "UXDEMO Sale {$i}",
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'role' => UserRole::Sales,
                    'team_id' => $template->team_id,
                    'is_active' => true,
                    'permissions' => ['receive_data' => $i !== 3],
                ]);
            } else {
                $user->forceFill([
                    'company_id' => $companyId,
                    'team_id' => $template->team_id,
                    'role' => UserRole::Sales,
                    'is_active' => true,
                ])->save();
            }
            $sales[] = $user;
        }

        if (! collect($sales)->contains(fn (User $u) => $u->id === $template->id)) {
            array_unshift($sales, $template);
        }

        return array_values($sales);
    }

    /** @param list<User> $sales */
    private function seedReportConfig(int $companyId, array $sales): void
    {
        $now = now();
        foreach ($sales as $index => $sale) {
            MonthlyKpiPlan::query()->withTrashed()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'user_id' => $sale->id,
                    'year' => $now->year,
                    'month' => $now->month,
                ],
                [
                    'kpi_name' => 'UXDEMO KPI',
                    'revenue_target' => 50_000_000 + ($index * 10_000_000),
                    'contacts_target' => 100,
                    'new_contacts_target' => 70,
                    'old_contacts_target' => 30,
                    'new_closed_target' => 40,
                    'old_closed_target' => 15,
                    'working_days' => 26,
                    'actual_days' => 20,
                    'locked' => false,
                    'deleted_at' => null,
                ],
            );

            UserOperationalProfile::query()->updateOrCreate(
                ['user_id' => $sale->id],
                [
                    'company_id' => $companyId,
                    'receive_data' => $index !== 2,
                    'employee_code' => 'UXDEMO-U'.$sale->id,
                ],
            );

            if (Schema::hasTable('sale_optimization_targets')) {
                SaleOptimizationTarget::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'sale_user_id' => $sale->id,
                        'metric_key' => 'close_rate',
                    ],
                    ['target_value' => 60 + ($index * 10)],
                );
            }
        }

        if (Schema::hasTable('sale_optimization_alert_thresholds')) {
            SaleOptimizationAlertThreshold::query()->updateOrCreate(
                ['company_id' => $companyId, 'metric_key' => 'close_rate'],
                ['low_ratio' => 80, 'high_ratio' => 100],
            );
        }

        if (Schema::hasTable('sale_optimization_levels')) {
            foreach ([
                ['label' => 'UXDEMO Chưa tốt', 'tone' => 'bad', 'min_ratio' => 0, 'max_ratio' => 80, 'sort_order' => 1],
                ['label' => 'UXDEMO Trung bình', 'tone' => 'average', 'min_ratio' => 80, 'max_ratio' => 100, 'sort_order' => 2],
                ['label' => 'UXDEMO Tốt', 'tone' => 'good', 'min_ratio' => 100, 'max_ratio' => null, 'sort_order' => 3],
            ] as $level) {
                SaleOptimizationLevel::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'metric_key' => 'close_rate',
                        'label' => $level['label'],
                    ],
                    [
                        'tone' => $level['tone'],
                        'min_ratio' => $level['min_ratio'],
                        'max_ratio' => $level['max_ratio'],
                        'sort_order' => $level['sort_order'],
                    ],
                );
            }
        }
    }

    /** @param  array<int, Order>  $orders */
    private function seedCustomer360(int $companyId, array $orders, User $admin): void
    {
        $segments = app(\App\Services\Customers\CustomerSegmentService::class);
        $segments->saveDefinitions([
            ['name' => 'UXDEMO Mới', 'color' => '#337ab7', 'min_successful_order_value' => 0],
            ['name' => 'UXDEMO Thân thiết', 'color' => '#00a65a', 'min_successful_order_value' => 200000],
            ['name' => 'UXDEMO VIP', 'color' => '#f39c12', 'min_successful_order_value' => 2000000],
        ]);
        $segments->recalculate($companyId);

        $orderIds = collect($orders)->take(5)->pluck('id')->map(fn ($id) => (int) $id)->all();
        \App\Models\Pushsale\CustomerCareCampaign::query()->updateOrCreate(
            ['company_id' => $companyId, 'name' => 'UXDEMO Chăm sóc 7 ngày'],
            [
                'customer_condition' => [
                    'source' => 'uxdemo',
                    'filters' => ['customer_type' => 'returning', 'status' => 'active'],
                    'order_ids' => $orderIds,
                    'phone_keys' => [],
                ],
                'repeat_days' => 7,
                'starts_at' => now()->subDays(3)->toDateString(),
                'ends_at' => now()->addDays(27)->toDateString(),
                'status' => 'active',
                'created_by_user_id' => $admin->id,
                'updated_by_user_id' => $admin->id,
                'deleted_at' => null,
            ],
        );
    }

    /** @param array<string, mixed> $scenario */
    private function createSaleOrder(
        int $companyId,
        User $sale,
        Product $product,
        Warehouse $warehouse,
        int $index,
        array $scenario,
    ): Order {
        $phone = self::PHONE_PREFIX.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
        if (! empty($scenario['duplicate']) && $index > 1) {
            $phone = self::PHONE_PREFIX.str_pad((string) max(1, $index - 1), 4, '0', STR_PAD_LEFT);
        }

        $qty = 1 + ($index % 2);
        $unitPrice = max(50_000, (int) $product->unit_price);
        $subtotal = $unitPrice * $qty;
        $discount = (int) ($scenario['discount'] ?? 0);
        $shipFee = 30_000;
        $codFee = (int) ($scenario['cod_fee'] ?? 0);
        $codSupport = (int) ($scenario['cod_support'] ?? 0);
        $deposit = (int) ($scenario['deposit'] ?? 0);
        $total = max(0, $subtotal - $discount + $shipFee);

        $arrivedAt = match ($scenario['anchor'] ?? null) {
            'yesterday' => now()->subDay()->setTime(10, 30),
            'last_month' => now()->subMonthNoOverflow()->setDay(min(28, now()->day))->setTime(11, 0),
            default => now()->subDays((int) ($scenario['day_offset'] ?? (($index - 1) % 5)))->setTime(9 + ($index % 8), 15 + ($index % 40)),
        };
        $assignedAt = $arrivedAt->copy()->addMinutes(30);

        $closing = $scenario['closing'] instanceof ClosingStatus ? $scenario['closing'] : ClosingStatus::Open;
        $delivery = $scenario['delivery'] ?? null;
        $deliveryStatus = $delivery instanceof DeliveryStatus
            ? $delivery->value
            : DeliveryStatus::WaitingWaybill->value;
        $isClosedLike = in_array($closing, [ClosingStatus::Closed, ClosingStatus::Cancelled], true);
        $closedAt = $isClosedLike ? $assignedAt->copy()->addHours(2 + ($index % 4)) : null;
        $needsWaybill = $closing === ClosingStatus::Closed
            || ($delivery instanceof DeliveryStatus && $delivery !== DeliveryStatus::WaitingWaybill);

        $stageValue = OperationStage::NoOperation->value;
        $resultValue = null;
        if (empty($scenario['untouched'])) {
            $stage = $scenario['stage'] ?? OperationStage::NewCustomer;
            $stageValue = $stage instanceof OperationStage ? $stage->value : (is_string($stage) ? $stage : OperationStage::NewCustomer->value);
            $result = $scenario['result'] ?? OperationResult::NoContact;
            $resultValue = $result instanceof OperationResult ? $result->value : (is_string($result) ? $result : null);
        } else {
            // DB NOT NULL operation_stage — dùng no_operation + result rỗng để báo cáo tính "chưa TN".
            $stageValue = OperationStage::NoOperation->value;
            $resultValue = '';
        }

        $order = Order::query()->create([
            'order_code' => self::ORDER_PREFIX.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            'sale_user_id' => $sale->id,
            'marketer_user_id' => $sale->id,
            'team_id' => $sale->team_id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'customer_name' => $scenario['name'],
            'customer_phone' => $phone,
            'phone_carrier' => ['VIETTEL', 'VINAPHONE', 'MOBIFONE'][$index % 3],
            'customer_note' => self::NOTE_TAG.' '.($scenario['note'] ?? ''),
            'sale_operation_note' => self::NOTE_TAG.' '.($scenario['note'] ?? ''),
            'shipping_address' => 'Số '.$index.' đường UX Demo, Hà Nội',
            'shipping_method' => 'viettel_post',
            'shipping_provider' => 'viettel_post',
            'data_arrived_at' => $arrivedAt,
            'assigned_at' => $assignedAt,
            'closed_at' => $closedAt,
            'next_operation_at' => $closedAt ? null : now()->addHours($index),
            'operation_stage' => $stageValue,
            'operation_result' => $resultValue,
            'closing_status' => $closing->value,
            'delivery_status' => $deliveryStatus,
            'carrier_name' => $needsWaybill || $closing === ClosingStatus::Closed ? 'Viettel Post(COD)' : null,
            'tracking_number' => ($needsWaybill || $closing === ClosingStatus::Closed)
                ? 'UXVT'.str_pad((string) (1000 + $index), 6, '0', STR_PAD_LEFT)
                : null,
            'is_returning_customer' => (bool) ($scenario['returning'] ?? ($index % 4 === 0)),
            'is_duplicate_phone' => (bool) ($scenario['duplicate'] ?? false),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'vat' => 0,
            'shipping_fee_collected' => $shipFee,
            'cod_fee' => $codFee,
            'cod_support' => $codSupport,
            'total' => $total,
            'deposit' => $deposit,
            'amount_to_collect' => max(0, $total - $deposit),
            'contact_count' => max(1, $index % 4),
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'item_type' => 'base',
            'origin' => self::BATCH,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
        ]);

        LeadIngestion::query()->create([
            'company_id' => $companyId,
            'platform' => 'manual',
            'external_id' => self::LEAD_PREFIX.$order->order_code,
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Lead,
            'counts_as_lead' => true,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'product_interest' => $product->name,
            'payload' => [
                'demo_batch' => self::BATCH,
                'order_code' => $order->order_code,
            ],
            'order_id' => $order->id,
            'processed_at' => $assignedAt,
        ]);

        return $order->fresh();
    }

    /** @param list<Order> $orders */
    private function seedCustomerProfiles(array $orders, User $sale, User $warehouseUser, User $admin): void
    {
        foreach ($orders as $offset => $order) {
            $base = now()->subHours(18 + $offset);
            $phoneKey = CustomerIdentity::phoneKey($order);

            OrderOperationHistory::query()->create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'actor_user_id' => $sale->id,
                'actor_name' => $sale->name,
                'actor_role' => $sale->role?->value ?? 'sales',
                'action' => OrderOperationHistory::ACTION_CALL,
                'operation_stage_before' => OperationStage::NewCustomer->value,
                'operation_stage_after' => $order->operation_stage,
                'operation_result' => $order->operation_result,
                'note' => self::NOTE_TAG.' Sale gọi khách lần đầu.',
                'created_at' => $base,
            ]);

            OrderOperationHistory::query()->create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'actor_user_id' => $sale->id,
                'actor_name' => $sale->name,
                'actor_role' => $sale->role?->value ?? 'sales',
                'action' => OrderOperationHistory::ACTION_STATUS_UPDATED,
                'operation_stage_before' => OperationStage::NewCustomer->value,
                'operation_stage_after' => $order->operation_stage,
                'operation_result' => $order->operation_result,
                'note' => self::NOTE_TAG.' Cập nhật trạng thái tác nghiệp: '.$order->customer_note,
                'created_at' => $base->copy()->addMinutes(20),
            ]);

            CustomerInternalMessage::query()->create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'author_user_id' => $sale->id,
                'author_name' => $sale->name,
                'author_role' => $sale->role?->value ?? 'sales',
                'customer_phone' => $phoneKey,
                'message' => '[UXDEMO] Sale ghi chú nội bộ: '.$order->sale_operation_note,
            ]);

            if ($offset % 2 === 0) {
                CustomerInternalMessage::query()->create([
                    'company_id' => $order->company_id,
                    'order_id' => $order->id,
                    'author_user_id' => $warehouseUser->id,
                    'author_name' => $warehouseUser->name,
                    'author_role' => $warehouseUser->role?->value ?? 'warehouse',
                    'customer_phone' => $phoneKey,
                    'message' => '[UXDEMO] Kho xác nhận đã kiểm hàng / chuẩn bị giao.',
                ]);
            }

            if ($offset % 3 === 0) {
                CustomerInternalMessage::query()->create([
                    'company_id' => $order->company_id,
                    'order_id' => $order->id,
                    'author_user_id' => $admin->id,
                    'author_name' => $admin->name,
                    'author_role' => $admin->role?->value ?? 'admin',
                    'customer_phone' => $phoneKey,
                    'message' => '[UXDEMO] Admin review hồ sơ khách — theo dõi chăm sóc.',
                ]);
            }
        }
    }

    /** @param list<Order> $orders */
    private function seedWarehouse(Warehouse $warehouse, Product $product, User $actor, array $orders): int
    {
        $intake = WarehouseVoucher::query()->create([
            'warehouse_id' => $warehouse->id,
            'code' => self::VOUCHER_PREFIX.'IN-001',
            'type' => 'inbound',
            'document_date' => now()->subDays(3)->toDateString(),
            'partner' => 'NCC UX Demo',
            'note' => self::NOTE_TAG.' Phiếu nhập kho demo UI thủ kho.',
            'status' => 'confirmed',
            'approved_by_user_id' => $actor->id,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);

        WarehouseVoucherLine::query()->create([
            'warehouse_voucher_id' => $intake->id,
            'product_id' => $product->id,
            'document_quantity' => 50,
            'quantity' => 50,
            'unit_cost' => max(10_000, (int) $product->unit_price - 20_000),
            'note' => self::NOTE_TAG.' Nhập tồn demo',
        ]);

        $export = WarehouseVoucher::query()->create([
            'warehouse_id' => $warehouse->id,
            'code' => self::VOUCHER_PREFIX.'OUT-001',
            'type' => 'outbound',
            'document_date' => now()->subDays(1)->toDateString(),
            'partner' => 'Xuất giao UX Demo',
            'note' => self::NOTE_TAG.' Phiếu xuất kho theo đơn đã chốt.',
            'status' => 'confirmed',
            'approved_by_user_id' => $actor->id,
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);

        $exportQty = collect($orders)
            ->filter(fn (Order $order) => $order->closing_status === ClosingStatus::Closed->value)
            ->sum(fn (Order $order) => (int) $order->items()->sum('quantity'));

        WarehouseVoucherLine::query()->create([
            'warehouse_voucher_id' => $export->id,
            'product_id' => $product->id,
            'document_quantity' => max(1, $exportQty),
            'quantity' => max(1, $exportQty),
            'unit_cost' => (int) $product->unit_price,
            'note' => self::NOTE_TAG.' Xuất theo đơn UXDEMO',
        ]);

        return 2;
    }

    /** @param list<Order> $orders */
    private function seedHandovers(User $actor, array $orders): int
    {
        $shipped = collect($orders)->filter(fn (Order $order) => filled($order->tracking_number))->count();

        WarehouseIncidentReport::query()->create([
            'manager_user_id' => $actor->id,
            'name' => self::HANDOVER_PREFIX.'01',
            'document_date' => now()->subDay()->toDateString(),
            'carrier' => 'viettel_post',
            'sender_name' => $actor->name,
            'receiver_name' => 'Điều phối Viettel Post',
            'order_count' => max(1, $shipped),
            'product_count' => max(1, $shipped),
            'status' => 'updating',
            'note' => self::NOTE_TAG.' Biên bản bàn giao demo UI thủ kho.',
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);

        WarehouseIncidentReport::query()->create([
            'manager_user_id' => $actor->id,
            'name' => self::HANDOVER_PREFIX.'02',
            'document_date' => now()->toDateString(),
            'carrier' => 'ghn',
            'sender_name' => $actor->name,
            'receiver_name' => 'Điều phối GHN',
            'order_count' => 2,
            'product_count' => 3,
            'status' => 'closed',
            'note' => self::NOTE_TAG.' Biên bản đã chốt demo.',
            'created_by_user_id' => $actor->id,
            'updated_by_user_id' => $actor->id,
        ]);

        return 2;
    }

    /** @param list<Order> $orders */
    private function seedInventoryMovements(Warehouse $warehouse, Product $product, User $actor, array $orders): int
    {
        $inventory = WarehouseInventory::query()->firstOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
            ],
            [
                'stock_quantity' => 100,
                'pending_sales_quantity' => 0,
                'uom' => $product->unit ?: 'cai',
            ],
        );

        $count = 0;
        WarehouseInventoryMovement::query()->create([
            'warehouse_inventory_id' => $inventory->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'user_id' => $actor->id,
            'approved_by_user_id' => $actor->id,
            'type' => WarehouseInventoryMovement::TYPE_INTAKE,
            'quantity' => 50,
            'unit_cost' => max(10_000, (int) $product->unit_price - 20_000),
            'stock_after' => (int) $inventory->stock_quantity + 50,
            'reference_type' => 'uxdemo_voucher',
            'reference_id' => null,
            'note' => self::NOTE_TAG.' Nhập tồn demo UI.',
        ]);
        $count++;

        foreach ($orders as $order) {
            if ($order->closing_status !== ClosingStatus::Closed->value || ! $order->tracking_number) {
                continue;
            }

            WarehouseInventoryMovement::query()->create([
                'warehouse_inventory_id' => $inventory->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'user_id' => $actor->id,
                'approved_by_user_id' => $actor->id,
                'type' => WarehouseInventoryMovement::TYPE_DEDUCTION,
                'quantity' => max(1, (int) $order->items()->sum('quantity')),
                'unit_cost' => (int) $product->unit_price,
                'stock_after' => max(0, (int) $inventory->stock_quantity - 1),
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'note' => self::NOTE_TAG.' Trừ tồn theo đơn '.$order->order_code,
            ]);
            $count++;
        }

        return $count;
    }

    private function ensureDemoProduct(int $companyId, User $actor): Product
    {
        return Product::query()->create([
            'company_id' => $companyId,
            'name' => 'UXDEMO Sản phẩm thử UI',
            'sku' => 'UXDEMO-SP-01',
            'type' => 'product',
            'unit' => 'chai',
            'unit_price' => 199_000,
            'cost_price' => 80_000,
            'is_active' => true,
            'available_marketing' => true,
            'available_sale' => true,
        ]);
    }

    private function ensureDemoWarehouse(int $companyId): Warehouse
    {
        return Warehouse::query()->create([
            'company_id' => $companyId,
            'name' => 'Kho UXDEMO',
            'code' => 'UXDEMO-KHO',
        ]);
    }

    /** @return array{orders:int, leads:int, histories:int, messages:int, vouchers:int, handovers:int, movements:int} */
    private function emptyCounts(): array
    {
        return [
            'orders' => 0,
            'leads' => 0,
            'histories' => 0,
            'messages' => 0,
            'vouchers' => 0,
            'handovers' => 0,
            'movements' => 0,
        ];
    }
}
