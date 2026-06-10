<?php

namespace Database\Seeders;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventoryMovement;
use App\Services\Inventory\InventoryDeductionService;
use App\Services\Inventory\InventoryReturnService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dòng chảy nghiệp vụ đầy đủ trong 45 ngày gần nhất, mọi số liệu khớp nhau:
 *
 * Lead đổ về từ chiến dịch → chia số cho telesale → tác nghiệp gọi →
 * chốt đơn → kho tạo vận đơn (trừ tồn qua InventoryDeductionService) →
 * giao hàng / thu COD → hàng hoàn nhập lại kho (InventoryReturnService).
 *
 * Nhờ đi qua đúng service nghiệp vụ, tồn kho + lịch sử nhập xuất +
 * báo cáo doanh số + đối soát đều đồng bộ với nhau.
 */
class SalesPipelineSeeder extends Seeder
{
    private const TOTAL_LEADS = 240;

    private const FIRST_NAMES = [
        'An', 'Bảo', 'Châu', 'Dũng', 'Giang', 'Hạnh', 'Hiếu', 'Hùng', 'Khánh', 'Lan',
        'Linh', 'Long', 'Mai', 'Minh', 'Nam', 'Ngọc', 'Phong', 'Phương', 'Quân', 'Quỳnh',
        'Sơn', 'Thảo', 'Thắng', 'Trang', 'Trung', 'Tuấn', 'Tú', 'Vân', 'Việt', 'Yến',
    ];

    private const LAST_NAMES = [
        'Nguyễn Văn', 'Trần Thị', 'Lê Hoàng', 'Phạm Thu', 'Hoàng Minh', 'Vũ Thị',
        'Đặng Quốc', 'Bùi Thanh', 'Đỗ Thị', 'Ngô Văn', 'Dương Thị', 'Lý Văn',
    ];

    private const PROVINCES = [
        'Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng', 'Hải Phòng', 'Cần Thơ',
        'Nghệ An', 'Thanh Hóa', 'Bắc Ninh', 'Quảng Ninh', 'Bình Dương',
    ];

    private const RETURN_REASONS = [
        'Khách đổi ý không nhận hàng',
        'Khách không nghe máy khi giao',
        'Sai địa chỉ giao hàng',
        'Khách hẹn giao lại nhiều lần rồi từ chối',
        'Hàng móp méo do vận chuyển',
    ];

    public function __construct(
        private readonly InventoryDeductionService $deduction,
        private readonly InventoryReturnService $returns,
    ) {}

    public function run(): void
    {
        mt_srand(20260610); // deterministic — seed lại bao nhiêu lần cũng ra cùng bộ số

        $salesPool = User::query()
            ->where('role', UserRole::Sales)
            ->where('email', '!=', 'head.sale@saleops.local')
            ->orderBy('id')
            ->get()
            ->values();

        $campaigns = MarketingSource::query()->with('product')->get();
        $approvedCampaigns = $campaigns->where('is_approved', true)->values();
        $pendingCampaign = $campaigns->firstWhere('is_approved', false);
        $warehouses = Warehouse::query()->orderBy('id')->get()->values();
        $warehouseStaff = User::query()->where('email', 'warehouse@saleops.local')->first();

        $orderSeq = 0;

        for ($i = 1; $i <= self::TOTAL_LEADS; $i++) {
            $daysAgo = $this->leadAgeDays($i);
            $arrivedAt = now()->subDays($daysAgo)->setTime(8 + ($i % 11), ($i * 13) % 60);

            $campaign = $approvedCampaigns[$i % $approvedCampaigns->count()];
            $bucket = $this->leadBucket($i);

            // Lead từ chiến dịch chưa duyệt luôn nằm ở hàng chờ
            if ($bucket === 'pending' && $pendingCampaign && $i % 3 === 0) {
                $campaign = $pendingCampaign;
            }

            $customerName = $this->customerName($i);
            $phone = '09'.str_pad((string) (30000000 + $i * 137), 8, '0', STR_PAD_LEFT);

            $lead = LeadIngestion::query()->create([
                'platform' => $this->platformOf($campaign),
                'external_id' => strtoupper(substr($campaign->ad_channel ?? 'web', 0, 2)).'-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'status' => match ($bucket) {
                    'pending' => LeadIngestionStatus::Pending,
                    'duplicate' => LeadIngestionStatus::Duplicate,
                    'failed' => LeadIngestionStatus::Failed,
                    default => LeadIngestionStatus::Processed,
                },
                'customer_name' => $customerName,
                'customer_phone' => $bucket === 'failed' ? null : $phone,
                'product_interest' => $campaign->product?->name,
                'utm_source' => $campaign->utm_source,
                'utm_campaign' => $campaign->utm_campaign,
                'payload' => ['seed' => true, 'campaign' => $campaign->name],
            ]);

            $lead->forceFill(['created_at' => $arrivedAt, 'updated_at' => $arrivedAt])->save();

            if ($bucket !== 'processed') {
                continue;
            }

            $orderSeq++;
            $sale = $salesPool[$orderSeq % $salesPool->count()];
            $warehouse = $warehouses[$orderSeq % $warehouses->count()];

            $order = $this->createOrder($orderSeq, $i, $sale, $campaign, $warehouse, $customerName, $phone, $arrivedAt);
            $lead->update(['order_id' => $order->id]);

            $this->progressFulfillment($order, $warehouseStaff);
        }

        $this->backdateOrderMovements();

        $this->command?->info(sprintf(
            'Đã tạo %d lead → %d đơn hàng đồng bộ kho/doanh số.',
            self::TOTAL_LEADS,
            Order::query()->count(),
        ));
    }

    /** Lead mới dồn về các ngày gần đây, lead cũ rải đều 45 ngày. */
    private function leadAgeDays(int $i): int
    {
        if ($i % 6 === 0) {
            return mt_rand(0, 3);
        }

        return mt_rand(0, 44);
    }

    private function leadBucket(int $i): string
    {
        return match (true) {
            $i % 19 === 0 => 'pending',   // chờ chia số thủ công
            $i % 23 === 0 => 'duplicate', // trùng SĐT
            $i % 41 === 0 => 'failed',    // thiếu SĐT / dữ liệu lỗi
            default => 'processed',
        };
    }

    private function createOrder(
        int $seq,
        int $leadIndex,
        User $sale,
        MarketingSource $campaign,
        Warehouse $warehouse,
        string $customerName,
        string $phone,
        Carbon $arrivedAt,
    ): Order {
        $product = $campaign->product;
        $qty = 1 + ($leadIndex % 3 === 0 ? 1 : 0);
        $unitPrice = (int) $product->unit_price;
        $subtotal = $qty * $unitPrice;
        $discount = $leadIndex % 5 === 0 ? (int) round($subtotal * 0.05) : 0;
        $shipFee = 30_000;
        $total = $subtotal - $discount + $shipFee;
        $deposit = $leadIndex % 9 === 0 ? 50_000 : 0;

        [$stage, $result, $closing, $delivery, $contactCount] = $this->funnelState($seq, $arrivedAt);

        $assignedAt = $arrivedAt->copy()->addMinutes(20 + ($seq % 90));
        $closed = $closing === ClosingStatus::Closed;
        $closedAt = $closed ? $assignedAt->copy()->addHours(2 + ($seq % 30)) : null;

        $hasWaybill = in_array($delivery, [
            DeliveryStatus::PickingUp, DeliveryStatus::Delivering, DeliveryStatus::Delivered,
            DeliveryStatus::Paid, DeliveryStatus::Returned,
        ], true);

        $province = self::PROVINCES[$seq % count(self::PROVINCES)];

        $order = Order::query()->create([
            'order_code' => 'PS'.str_pad((string) (2_000_000 + $seq), 9, '0', STR_PAD_LEFT),
            'sale_user_id' => $sale->id,
            'marketer_user_id' => $campaign->marketer_user_id,
            'team_id' => $sale->team_id,
            'marketing_source_id' => $campaign->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'customer_name' => $customerName,
            'customer_phone' => $phone,
            'phone_carrier' => ['VIETTEL', 'VINAPHONE', 'MOBIFONE'][$seq % 3],
            'customer_note' => $closed ? 'Khách xác nhận nhận hàng giờ hành chính.' : null,
            'shipping_address' => 'Số '.($seq % 200 + 1).' đường demo, '.$province,
            'data_arrived_at' => $arrivedAt,
            'assigned_at' => $assignedAt,
            'closed_at' => $closedAt,
            'next_operation_at' => $this->nextOperationAt($closing, $result),
            'operation_stage' => $stage->value,
            'operation_result' => $result->value,
            'closing_status' => $closing->value,
            'delivery_status' => $delivery->value,
            'carrier_name' => $hasWaybill ? 'Viettel Post(COD)' : null,
            'tracking_number' => $hasWaybill ? 'VT'.str_pad((string) (10_000_000 + $seq), 8, '0', STR_PAD_LEFT) : null,
            'is_returning_customer' => $seq % 7 === 0,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'vat' => 0,
            'shipping_fee_collected' => $shipFee,
            'total' => $total,
            'deposit' => $deposit,
            'amount_to_collect' => $total - $deposit,
            'contact_count' => $contactCount,
            'cod_fee' => $hasWaybill ? 15_000 : 0,
            'cod_support' => $hasWaybill ? 5_000 : 0,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
        ]);

        return $order;
    }

    /**
     * @return array{0: OperationStage, 1: OperationResult, 2: ClosingStatus, 3: DeliveryStatus, 4: int}
     */
    private function funnelState(int $seq, Carbon $arrivedAt): array
    {
        $roll = mt_rand(1, 100);

        // ~30% đang tác nghiệp (chưa chốt)
        if ($roll <= 30 || $arrivedAt->greaterThan(now()->subDay())) {
            $open = [
                [OperationStage::NewCustomer, OperationResult::NoContact, 0],
                [OperationStage::Call2, OperationResult::NoAnswer1, 1],
                [OperationStage::Call3, OperationResult::CallbackScheduled, 2],
                [OperationStage::Call4, OperationResult::SentQuote, 3],
                [OperationStage::Call5, OperationResult::Considering, 4],
                [OperationStage::Call6, OperationResult::ReadyToClose, 5],
            ];
            [$stage, $result, $contacts] = $open[$seq % count($open)];

            return [$stage, $result, ClosingStatus::Open, DeliveryStatus::DeliverNow, $contacts];
        }

        // ~8% từ chối / hủy
        if ($roll <= 38) {
            $cancelled = [
                [OperationStage::Skipped, OperationResult::PriceRejected],
                [OperationStage::Skipped, OperationResult::NoNeed],
                [OperationStage::NoOperation, OperationResult::WrongNumber],
            ];
            [$stage, $result] = $cancelled[$seq % count($cancelled)];

            return [$stage, $result, ClosingStatus::Cancelled, DeliveryStatus::CancelClosing, 1 + $seq % 4];
        }

        // ~62% chốt đơn — phân bổ trạng thái giao hàng theo pipeline thực tế
        $stage = [OperationStage::Care1, OperationStage::Care2, OperationStage::Care3][$seq % 3];
        $delivery = $this->deliveryStatusFor(mt_rand(1, 100));

        return [$stage, OperationResult::ClosedSuccess, ClosingStatus::Closed, $delivery, 2 + $seq % 5];
    }

    private function deliveryStatusFor(int $roll): DeliveryStatus
    {
        return match (true) {
            $roll <= 9 => DeliveryStatus::WaitingWaybill,
            $roll <= 17 => DeliveryStatus::PickingUp,
            $roll <= 30 => DeliveryStatus::Delivering,
            $roll <= 58 => DeliveryStatus::Delivered,
            $roll <= 88 => DeliveryStatus::Paid,
            $roll <= 96 => DeliveryStatus::Returned,
            default => DeliveryStatus::CancelWaybill,
        };
    }

    private function nextOperationAt(ClosingStatus $closing, OperationResult $result): ?Carbon
    {
        if ($closing !== ClosingStatus::Open) {
            return null;
        }

        // Rải lịch hẹn trong 7 ngày tới để báo cáo lịch hẹn telesales có dữ liệu mỗi ngày
        return in_array($result, [OperationResult::CallbackScheduled, OperationResult::Considering, OperationResult::SentQuote], true)
            ? now()->addDays(mt_rand(0, 6))->setTime(mt_rand(8, 18), [0, 15, 30, 45][mt_rand(0, 3)])
            : null;
    }

    /** Trừ kho khi tạo vận đơn + nhập hoàn cho đơn hoàn — đi qua đúng service nghiệp vụ. */
    private function progressFulfillment(Order $order, ?User $warehouseStaff): void
    {
        $delivery = DeliveryStatus::tryFrom($order->delivery_status);

        $deducted = in_array($delivery, [
            DeliveryStatus::PickingUp, DeliveryStatus::Delivering, DeliveryStatus::Delivered,
            DeliveryStatus::Paid, DeliveryStatus::Returned,
        ], true);

        if (! $deducted) {
            return;
        }

        $this->deduction->deductForOrder($order, $warehouseStaff);

        if ($delivery === DeliveryStatus::Returned) {
            $reason = self::RETURN_REASONS[$order->id % count(self::RETURN_REASONS)];
            $this->returns->receiveReturn($order, $reason, $warehouseStaff);
        }

        // Đồng bộ mốc thời gian trừ kho / nhập hoàn theo dòng thời gian của đơn
        $deductedAt = $order->closed_at?->copy()->addHours(3) ?? now();
        $updates = ['inventory_deducted_at' => $deductedAt];

        if ($delivery === DeliveryStatus::Returned) {
            $updates['return_restocked_at'] = $deductedAt->copy()->addDays(4);
        }

        $order->forceFill($updates)->save();
    }

    /** Lùi ngày các phiếu kho sinh ra từ đơn hàng về đúng dòng thời gian của đơn. */
    private function backdateOrderMovements(): void
    {
        WarehouseInventoryMovement::query()
            ->where('reference_type', 'order')
            ->with([])
            ->get()
            ->groupBy('reference_id')
            ->each(function (Collection $movements, int $orderId) {
                $order = Order::query()->find($orderId);

                if (! $order) {
                    return;
                }

                foreach ($movements as $movement) {
                    $timestamp = $movement->type === WarehouseInventoryMovement::TYPE_RETURN
                        ? $order->return_restocked_at
                        : $order->inventory_deducted_at;

                    if ($timestamp) {
                        $movement->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->save();
                    }
                }
            });
    }

    private function customerName(int $i): string
    {
        return self::LAST_NAMES[$i % count(self::LAST_NAMES)].' '.self::FIRST_NAMES[($i * 7) % count(self::FIRST_NAMES)];
    }

    private function platformOf(MarketingSource $campaign): string
    {
        return match ($campaign->ad_channel) {
            'facebook' => 'Facebook',
            'tiktok' => 'TikTok',
            'google' => 'Google',
            'zalo' => 'Zalo',
            default => 'Landing',
        };
    }
}
