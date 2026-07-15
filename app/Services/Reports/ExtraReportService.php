<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Enums\OperationStage;
use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\User;
use App\Models\WarehouseInventory;
use App\Support\LeadContactMetrics;
use App\Support\OrderRevenueClassifier;
use Illuminate\Support\Collection;

/**
 * Bộ báo cáo bổ sung theo từng bộ phận (Telesale / Marketing / Kho).
 *
 * Phân quyền:
 * - Admin xem tất cả (menu "Báo cáo bổ sung").
 * - Trưởng bộ phận / trưởng nhóm: xem báo cáo tổng hợp của bộ phận mình.
 * - Nhân viên: chỉ xem báo cáo phục vụ công việc + số liệu của chính mình
 *   (mọi báo cáo mức "staff" tự thu hẹp dữ liệu về bản thân).
 */
class ExtraReportService
{
    private const DELIVERED = ['delivered', 'delivery_complete', 'partial_delivery', 'partial', 'delivered_partial', 'partially_delivered'];

    private const PAID = ['paid'];

    private const DELIVERING = ['waiting_waybill', 'posted', 'picking_up', 'delivering', 'deliver_now', 'redelivery'];

    private const RETURNED = ['returned', 'returning', 'refund', 'cannot_deliver'];

    private const CANCELLED = ['cancel_waybill', 'cancel_closing'];

    /** Xác nhận giao hàng (2): đơn đã lên vận đơn / vào luồng giao (kể cả chờ lấy). */
    private const XNGH = ['waiting_waybill', 'posted', 'picking_up', 'delivering', 'deliver_now', 'redelivery', 'delivered', 'delivery_complete', 'partial_delivery', 'partial', 'delivered_partial', 'partially_delivered', 'paid', 'returned', 'returning', 'refund', 'cannot_deliver'];

    /** Chuyển ĐVGH (4): đơn đã bàn giao cho đơn vị vận chuyển (mẫu số của các % giao/hoàn). */
    private const TRANSFER = ['posted', 'picking_up', 'delivering', 'deliver_now', 'redelivery', 'delivered', 'delivery_complete', 'partial_delivery', 'partial', 'delivered_partial', 'partially_delivered', 'paid', 'returned', 'returning', 'refund', 'cannot_deliver'];

    /** Đã hoàn (5): hoàn xong. */
    private const RETURNED_DONE = ['returned', 'refund'];

    /** Đang hoàn (6): đang trên đường hoàn / không giao được. */
    private const RETURNING = ['returning', 'cannot_deliver'];

    /** Giao thành công (9): đã giao + đã thanh toán. */
    private const SUCCESS = ['delivered', 'delivery_complete', 'partial_delivery', 'partial', 'delivered_partial', 'partially_delivered', 'paid'];

    public function __construct(
        private readonly ReportScopeResolver $scope,
    ) {}

    /**
     * @return array<string, array{title: string, description: string, roles: list<string>, level: string, filters: list<string>}>
     */
    public static function registry(): array
    {
        $defs = [
            'sale-1' => ['roles' => ['sales', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'operation_stage', 'team_id', 'product_id']],
            'sale-2' => ['roles' => ['sales', 'accounting', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'sale-3' => ['roles' => ['sales', 'accounting', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'sale-4' => ['roles' => ['sales', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'product_id']],
            'sale-5' => ['roles' => ['sales', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'operation_stage', 'operation_result', 'team_id']],
            'marketing-1' => ['roles' => ['marketing', 'accounting', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'marketing-2' => ['roles' => ['marketing', 'sales', 'accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type']],
            'marketing-3' => ['roles' => ['marketing', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'marketing-4' => ['roles' => ['marketing', 'accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'kho-1' => ['roles' => ['accounting', 'warehouse', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id', 'warehouse_id']],
            'kho-2' => ['roles' => ['sales', 'marketing', 'warehouse', 'accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'warehouse_id']],
            'warehouse-sales-summary' => ['roles' => ['sales', 'warehouse', 'accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'warehouse_id']],
            'warehouse-sales-v2' => ['roles' => ['sales', 'warehouse', 'accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id', 'warehouse_id']],
            'product-conversion' => ['roles' => ['sales', 'marketing', 'accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id', 'team_id', 'marketing_team_id', 'warehouse_id']],
        ];

        $out = [];
        foreach ($defs as $key => $meta) {
            $out[$key] = [
                'title' => __("reports.extra.{$key}.title"),
                'description' => __("reports.extra.{$key}.description"),
                ...$meta,
            ];
        }

        return $out;
    }

    public function exists(string $key): bool
    {
        return isset(self::registry()[$key]);
    }

    public function canView(User $user, string $key): bool
    {
        $report = self::registry()[$key] ?? null;

        if (! $report || ! in_array($user->role->value, $report['roles'], true)) {
            return false;
        }

        if ($report['level'] === 'staff' || $this->isElevated($user)) {
            return true;
        }

        // Kế toán cần quyền đọc báo cáo đối soát/tài chính ở mọi cấp. Nhân sự
        // kho được đọc các báo cáo của chính khối kho; Sales/Marketing chỉ mở
        // báo cáo tổng hợp liên phòng khi là leader/supervisor.
        if ($user->role === UserRole::Accounting) {
            return true;
        }

        return $user->role === UserRole::Warehouse
            && in_array($key, ['kho-1', 'kho-2', 'warehouse-sales-summary', 'warehouse-sales-v2'], true);
    }

    /** Danh sách báo cáo user được xem — dùng cho tab điều hướng. */
    public function availableFor(User $user): array
    {
        $base = $this->basePathFor($user);

        return collect(self::registry())
            ->filter(fn (array $report, string $key) => $this->canView($user, $key))
            ->map(fn (array $report, string $key) => [
                'key' => $key,
                'title' => $report['title'],
                'url' => $base.'/'.$key,
            ])
            ->values()
            ->all();
    }

    public function basePathFor(User $user): string
    {
        return match ($user->role) {
            UserRole::Admin => '/admin/reports/extra',
            UserRole::Sales => '/sales/reports',
            UserRole::Marketing => '/marketing/reports',
            UserRole::Accounting => '/accounting/reports',
            UserRole::Warehouse => '/warehouse/reports',
            default => '/reports',
        };
    }

    /** @return array{meta: array, columns: list<array>, rows: list<array>, totals: ?array} */
    public function build(string $key, User $user, ReportFilterData $filter): array
    {
        $report = self::registry()[$key];

        $data = match ($key) {
            'sale-1' => $this->saleWork($user, $filter),
            'sale-2' => $this->saleClosing($user, $filter),
            'sale-3' => $this->revenueDetail($user, $filter, 'sale'),
            'sale-4' => $this->saleKpi($user, $filter),
            'sale-5' => $this->saleAppointments($user, $filter),
            'marketing-1' => $this->revenueDetail($user, $filter, 'marketing'),
            'marketing-2' => $this->productClosing($user, $filter),
            'marketing-3' => $this->marketingWork($user, $filter),
            'marketing-4' => $this->upsaleReport($user, $filter),
            'kho-1' => $this->warehousePending($filter),
            'kho-2' => $this->systemBusiness($user, $filter),
            'warehouse-sales-summary' => $this->warehouseSalesSummary($user, $filter),
            'warehouse-sales-v2' => $this->warehouseSalesV2($user, $filter),
            'product-conversion' => $this->productConversionMatrix($user, $filter),
        };

        $filterFields = $report['filters'];

        // Admin có thể lọc theo từng nhân viên; các role khác đã bị giới hạn phạm vi sẵn
        if ($user->role === UserRole::Admin && $filterFields !== []) {
            if (in_array($key, ['sale-1', 'sale-2', 'sale-3', 'sale-4', 'sale-5'], true)) {
                $filterFields[] = 'sale_id';
            }

            if (in_array($key, ['marketing-1', 'marketing-3'], true)) {
                $filterFields[] = 'marketer_id';
            }
        } elseif ($user->role === UserRole::Sales && $this->isElevated($user)) {
            if (in_array($key, ['sale-1', 'sale-2', 'sale-3', 'sale-4', 'sale-5'], true)) {
                $filterFields[] = 'sale_id';
            }
        } elseif ($user->role === UserRole::Marketing && $this->isElevated($user)) {
            if (in_array($key, ['marketing-1', 'marketing-3'], true)) {
                $filterFields[] = 'marketer_id';
            }
        }

        // Không hiển thị bộ lọc ngoài phạm vi vai trò. Backend vẫn áp dụng
        // ReportScopeResolver, nên việc sửa query string thủ công cũng không
        // thể mở rộng dữ liệu vượt quá phạm vi được phép.
        if ($user->role === UserRole::Sales) {
            $filterFields = array_values(array_diff($filterFields, ['marketing_team_id', 'marketer_id']));
            if (! $this->isElevated($user)) {
                $filterFields = array_values(array_diff($filterFields, ['team_id', 'sale_id']));
            }
        } elseif ($user->role === UserRole::Marketing) {
            $filterFields = array_values(array_diff($filterFields, ['team_id', 'sale_id']));
            if (! $this->isElevated($user)) {
                $filterFields = array_values(array_diff($filterFields, ['marketing_team_id', 'marketer_id']));
            }
        }

        $filterFields = array_values(array_unique($filterFields));

        return array_merge($data, [
            'meta' => [
                'key' => $key,
                'title' => $report['title'],
                'description' => $report['description'],
                'filterFields' => $filterFields,
            ],
        ]);
    }

    // ─── Telesale ────────────────────────────────────────────────────────────

    private function saleWork(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrders($user, $filter, scopeSales: true)
            ->filter(fn (Order $o) => $o->sale_user_id !== null);

        $stages = [
            OperationStage::NewCustomer, OperationStage::Call2, OperationStage::Call3,
            OperationStage::Call4, OperationStage::Call5, OperationStage::Call6,
            OperationStage::Care1, OperationStage::Care2, OperationStage::Care3,
            OperationStage::Skipped,
        ];

        $columns = array_merge([
            $this->col('telesale', 'name', 'text'),
            $this->col('contacts_total', 'contacts', 'number'),
            $this->col('untouched', 'untouched', 'number'),
        ], array_map(fn (OperationStage $stage) => [
            'key' => 'stage_'.$stage->value,
            'label_key' => $stage->value,
            'label_type' => 'operation_stage',
            'label' => $stage->label(),
            'format' => 'number',
        ], $stages));

        $rows = $orders->groupBy('sale_user_id')->map(function (Collection $group) use ($stages) {
            $contacts = $this->contactOrders($group);
            $row = [
                'name' => $group->first()->saleUser?->name ?? '—',
                'contacts' => $contacts->count(),
                'untouched' => $contacts->where('contact_count', 0)->count(),
            ];

            foreach ($stages as $stage) {
                $stageContacts = $contacts
                    ->filter(fn (Order $o) => (string) $o->operation_stage === $stage->value);
                $row['stage_'.$stage->value] = $stageContacts->count();
                $row['stage_'.$stage->value.'_untouched'] = $stageContacts
                    ->where('contact_count', 0)
                    ->count();
            }

            return $row;
        })->sortByDesc('contacts')->values()->all();

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->sumTotals($columns, $rows)];
    }

    private function saleClosing(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrdersWithItems($user, $filter, scopeSales: true)
            ->filter(fn (Order $order): bool => $order->sale_user_id !== null);

        $columns = [
            $this->col('telesale', 'name', 'text'),
            $this->col('new_contacts', 'new_contacts', 'number'),
            $this->col('new_closed', 'new_closed', 'number'),
            $this->col('new_rate', 'new_rate', 'percent', ['tone' => 'positive']),
            $this->col('new_gross', 'new_gross', 'currency'),
            $this->col('new_discount', 'new_discount', 'currency'),
            $this->col('new_net', 'new_net', 'currency'),
            $this->col('old_closed', 'old_closed', 'number'),
            $this->col('old_gross', 'old_gross', 'currency'),
            $this->col('old_discount', 'old_discount', 'currency'),
            $this->col('old_net', 'old_net', 'currency'),
            $this->col('total_closed', 'total_closed', 'number'),
            $this->col('total_rate', 'total_rate', 'percent', ['tone' => 'positive']),
            $this->col('total_gross', 'total_gross', 'currency'),
            $this->col('total_discount', 'total_discount', 'currency'),
            $this->col('total_net', 'total_net', 'currency'),
            $this->col('upsell_qty', 'upsell_qty', 'number'),
            $this->col('upsell_rev', 'upsell_revenue', 'currency'),
        ];

        $rows = $orders->groupBy('sale_user_id')->map(function (Collection $group): array {
            /*
             * Order supplemental/late-upsell vẫn mang doanh thu cho sale nhưng
             * không đại diện cho một contact và không được làm tăng số đơn chốt
             * trong tỷ lệ. contactOrders() là nguồn sự thật dùng chung toàn hệ thống.
             */
            $contactOrders = $this->contactOrders($group);
            $newContacts = $contactOrders->where('is_returning_customer', false);
            $oldContacts = $contactOrders->where('is_returning_customer', true);
            $newClosedContacts = $this->closed($newContacts);
            $oldClosedContacts = $this->closed($oldContacts);

            $newClosedOrders = $this->closed($group->where('is_returning_customer', false));
            $oldClosedOrders = $this->closed($group->where('is_returning_customer', true));
            $allClosedOrders = $this->closed($group);

            $metrics = static function (Collection $closed): array {
                $net = (int) $closed->sum(fn (Order $order): int => $order->netRevenue());
                $discount = (int) $closed->sum('discount');

                return [
                    'gross' => $net + $discount,
                    'discount' => $discount,
                    'net' => $net,
                ];
            };

            $newMoney = $metrics($newClosedOrders);
            $oldMoney = $metrics($oldClosedOrders);
            $totalMoney = $metrics($allClosedOrders);
            $closedItems = $allClosedOrders->flatMap(fn (Order $order) => $order->items);
            $upsellItems = $closedItems->where('item_type', 'upsell');
            $totalClosed = $newClosedContacts->count() + $oldClosedContacts->count();

            return [
                'name' => $group->first()->saleUser?->name ?? '—',
                'new_contacts' => $newContacts->count(),
                'new_closed' => $newClosedContacts->count(),
                'new_rate' => self::pct($newClosedContacts->count(), $newContacts->count()),
                'new_gross' => $newMoney['gross'],
                'new_discount' => $newMoney['discount'],
                'new_net' => $newMoney['net'],
                'old_closed' => $oldClosedContacts->count(),
                'old_gross' => $oldMoney['gross'],
                'old_discount' => $oldMoney['discount'],
                'old_net' => $oldMoney['net'],
                'total_closed' => $totalClosed,
                // Theo đúng công thức Pushsale: tổng chốt / contact nhận mới.
                'total_rate' => self::pct($totalClosed, $newContacts->count()),
                'total_gross' => $totalMoney['gross'],
                'total_discount' => $totalMoney['discount'],
                'total_net' => $totalMoney['net'],
                'upsell_qty' => (int) $upsellItems->sum('quantity'),
                'upsell_revenue' => (int) $upsellItems->sum(fn (OrderItem $item): int => $item->lineTotal()),
            ];
        })->sortByDesc('total_net')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['new_rate'] = self::pct($totals['new_closed'] ?? 0, $totals['new_contacts'] ?? 0);
        $totals['total_rate'] = self::pct($totals['total_closed'] ?? 0, $totals['new_contacts'] ?? 0);

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Doanh số chi tiết theo telesale hoặc marketer — cùng cấu trúc 19 chỉ số
     * theo mẫu pushsale: 9 nhóm trạng thái (SL + Doanh số) + các tỷ lệ.
     */
    private function revenueDetail(User $user, ReportFilterData $filter, string $groupRole): array
    {
        $bySale = $groupRole === 'sale';
        $orders = $this->fetchOrdersWithItems($user, $filter, scopeSales: $bySale, scopeMarketing: ! $bySale)
            ->filter(fn (Order $o) => $bySale ? $o->sale_user_id !== null : $o->marketer_user_id !== null);

        $columns = [
            ['key' => 'name', 'label_key' => $bySale ? 'telesale' : 'marketer', 'label' => $bySale ? __('reports.columns.telesale') : __('reports.columns.marketer'), 'format' => 'text'],
            $this->col('closed_qty', 'closed_qty', 'number'),
            $this->col('closed_rev', 'closed_rev', 'currency'),
            $this->col('xngh_qty', 'xngh_qty', 'number'),
            $this->col('xngh_rev', 'xngh_rev', 'currency'),
            $this->col('cancel_qty', 'cancel_qty', 'number'),
            $this->col('cancel_rev', 'cancel_rev', 'currency'),
            $this->col('transfer_qty', 'transfer_qty', 'number'),
            $this->col('transfer_rev', 'transfer_rev', 'currency'),
            $this->col('returned_qty', 'returned_qty', 'number'),
            $this->col('returned_rev', 'returned_rev', 'currency'),
            $this->col('returning_qty', 'returning_qty', 'number'),
            $this->col('returning_rev', 'returning_rev', 'currency'),
            $this->col('delivered_qty', 'delivered_qty', 'number'),
            $this->col('delivered_rev', 'delivered_rev', 'currency'),
            $this->col('paid_qty', 'paid_qty', 'number'),
            $this->col('paid_rev', 'paid_rev', 'currency'),
            $this->col('success_qty', 'success_qty', 'number'),
            $this->col('success_rev', 'success_rev', 'currency'),
            $this->col('pct_returned', 'pct_returned', 'percent', ['tone' => 'negative']),
            $this->col('pct_cancel', 'pct_cancel', 'percent', ['tone' => 'negative']),
            $this->col('pct_xngh', 'pct_xngh', 'percent', ['tone' => 'positive']),
            $this->col('pct_success', 'pct_success', 'percent', ['tone' => 'positive']),
            $this->col('contacts', 'contacts', 'number'),
            $this->col('close_rate', 'close_rate', 'percent', ['tone' => 'positive']),
            $this->col('product_count', 'product_count', 'number'),
            $this->col('avg_order', 'avg_order', 'currency'),
            $this->col('pct_rev_returned', 'pct_rev_returned', 'percent', ['tone' => 'negative']),
            $this->col('pct_rev_cancel', 'pct_rev_cancel', 'percent', ['tone' => 'negative']),
            $this->col('base_qty', 'base_qty', 'number'),
            $this->col('base_rev', 'base_rev', 'currency'),
            $this->col('upsell_qty', 'upsell_qty', 'number'),
            $this->col('upsell_rev', 'upsell_rev', 'currency'),
            $this->col('upsell_order_rate', 'upsell_order_rate', 'percent', ['tone' => 'positive']),
            $this->col('upsell_revenue_share', 'upsell_revenue_share', 'percent', ['tone' => 'positive']),
        ];

        $groupKey = $bySale ? 'sale_user_id' : 'marketer_user_id';
        $contactCounts = $bySale
            ? collect()
            : LeadContactMetrics::effectiveCountsByMarketer($filter, $orders);

        $rows = $orders->groupBy($groupKey)->map(function (Collection $group, int|string $groupId) use ($bySale, $contactCounts) {
            $closed = $this->closed($group);
            $rev = fn (Collection $c) => (int) $c->sum(fn (Order $o) => $o->netRevenue());

            $closedRev = $rev($closed);
            $xngh = $this->bucket($closed, self::XNGH);
            $cancel = $this->bucket($closed, self::CANCELLED);
            $transfer = $this->bucket($closed, self::TRANSFER);
            $returned = $this->bucket($closed, self::RETURNED_DONE);
            $returning = $this->bucket($closed, self::RETURNING);
            $delivered = $this->bucket($closed, self::DELIVERED);
            $paid = $this->bucket($closed, self::PAID);
            $success = $this->bucket($closed, self::SUCCESS);
            $cancelRev = $rev($cancel);
            $returnedRev = $rev($returned);
            $contactOrders = $this->contactOrders($group);
            $closedContacts = $this->closed($contactOrders);
            $contacts = $bySale
                ? $contactOrders->count()
                : (int) $contactCounts->get((int) $groupId, 0);
            $closedItems = $closed->flatMap(fn (Order $order) => $order->items);
            $upsellItems = $closedItems->where('item_type', 'upsell');
            $baseItems = $closedItems->reject(fn (OrderItem $item): bool => $item->item_type === 'upsell');
            $upsellOrderCount = $closed->filter(fn (Order $order): bool => $order->items->contains('item_type', 'upsell'))->count();
            $upsellRevenue = (int) $upsellItems->sum(fn (OrderItem $item): int => $item->lineTotal());

            return [
                'name' => ($bySale ? $group->first()->saleUser?->name : $group->first()->marketerUser?->name) ?? '—',
                'closed_qty' => $closed->count(),
                'closed_rev' => $closedRev,
                'xngh_qty' => $xngh->count(),
                'xngh_rev' => $rev($xngh),
                'cancel_qty' => $cancel->count(),
                'cancel_rev' => $cancelRev,
                'transfer_qty' => $transfer->count(),
                'transfer_rev' => $rev($transfer),
                'returned_qty' => $returned->count(),
                'returned_rev' => $returnedRev,
                'returning_qty' => $returning->count(),
                'returning_rev' => $rev($returning),
                'delivered_qty' => $delivered->count(),
                'delivered_rev' => $rev($delivered),
                'paid_qty' => $paid->count(),
                'paid_rev' => $rev($paid),
                'success_qty' => $success->count(),
                'success_rev' => $rev($success),
                'pct_returned' => self::pct($returned->count(), $transfer->count()),
                'pct_cancel' => self::pct($cancel->count(), $closed->count()),
                'pct_xngh' => self::pct($xngh->count(), $closed->count()),
                'pct_success' => self::pct($success->count(), $transfer->count()),
                'contacts' => $contacts,
                'closed_contact_count' => $closedContacts->count(),
                'close_rate' => self::pct($closedContacts->count(), $contacts),
                'product_count' => (int) $closed->sum(fn (Order $o) => $o->items->sum('quantity')),
                'avg_order' => $closed->count() > 0 ? (int) round($closedRev / $closed->count()) : null,
                'pct_rev_returned' => self::pct($returnedRev, $closedRev),
                'pct_rev_cancel' => self::pct($cancelRev, $closedRev),
                'base_qty' => (int) $baseItems->sum('quantity'),
                'base_rev' => (int) $baseItems->sum(fn (OrderItem $item): int => $item->lineTotal()),
                'upsell_qty' => (int) $upsellItems->sum('quantity'),
                'upsell_rev' => $upsellRevenue,
                'upsell_order_count' => $upsellOrderCount,
                'upsell_order_rate' => self::pct($upsellOrderCount, $closed->count()),
                'upsell_revenue_share' => self::pct($upsellRevenue, $closedRev),
            ];
        })->sortByDesc('closed_rev')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['pct_returned'] = self::pct($totals['returned_qty'] ?? 0, $totals['transfer_qty'] ?? 0);
        $totals['pct_cancel'] = self::pct($totals['cancel_qty'] ?? 0, $totals['closed_qty'] ?? 0);
        $totals['pct_xngh'] = self::pct($totals['xngh_qty'] ?? 0, $totals['closed_qty'] ?? 0);
        $totals['pct_success'] = self::pct($totals['success_qty'] ?? 0, $totals['transfer_qty'] ?? 0);
        $totals['closed_contact_count'] = array_sum(array_map(fn (array $row): int => (int) ($row['closed_contact_count'] ?? 0), $rows));
        $totals['close_rate'] = self::pct($totals['closed_contact_count'], $totals['contacts'] ?? 0);
        $totals['avg_order'] = ($totals['closed_qty'] ?? 0) > 0
            ? (int) round(($totals['closed_rev'] ?? 0) / $totals['closed_qty'])
            : null;
        $totals['pct_rev_returned'] = self::pct($totals['returned_rev'] ?? 0, $totals['closed_rev'] ?? 0);
        $totals['pct_rev_cancel'] = self::pct($totals['cancel_rev'] ?? 0, $totals['closed_rev'] ?? 0);
        $totals['upsell_order_count'] = array_sum(array_map(fn (array $row): int => (int) ($row['upsell_order_count'] ?? 0), $rows));
        $totals['upsell_order_rate'] = self::pct($totals['upsell_order_count'], $totals['closed_qty'] ?? 0);
        $totals['upsell_revenue_share'] = self::pct($totals['upsell_rev'] ?? 0, $totals['closed_rev'] ?? 0);

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    private function saleKpi(User $user, ReportFilterData $filter): array
    {
        /*
         * KPI Sale dùng hai trục thời gian độc lập theo đúng nghiệp vụ:
         * - Contact: ngày Sale nhận data (assigned_at).
         * - Chốt/doanh số: ngày chốt đơn (closed_at).
         * Không ép cả hai chỉ số vào cùng một date_type vì sẽ làm KPI sai khi
         * lead được nhận ở tháng trước nhưng được chốt trong kỳ hiện tại.
         */
        $contactFilter = $filter->withDateType(DateType::SaleReceived);
        $closingFilter = $filter->withDateType(DateType::Closing);

        $contactRangeOrders = $this->fetchOrdersWithItems($user, $contactFilter, scopeSales: true)
            ->filter(fn (Order $order): bool => $order->sale_user_id !== null);
        $closingRangeOrders = $this->fetchOrdersWithItems($user, $closingFilter, scopeSales: true)
            ->filter(fn (Order $order): bool => $order->sale_user_id !== null);

        $saleIds = $contactRangeOrders->pluck('sale_user_id')
            ->merge($closingRangeOrders->pluck('sale_user_id'))
            ->when($filter->saleId, fn (Collection $ids) => $ids->push($filter->saleId))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $from = ($filter->dateFrom ?? now()->startOfMonth())->copy();
        $to = ($filter->dateTo ?? now()->endOfMonth())->copy();
        $months = collect();
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $months->push([$cursor->year, $cursor->month]);
            $cursor->addMonth();
        }

        $plans = $saleIds->isEmpty()
            ? collect()
            : MonthlyKpiPlan::query()
                ->whereIn('user_id', $saleIds)
                ->where(function ($query) use ($months): void {
                    foreach ($months as [$year, $month]) {
                        $query->orWhere(fn ($q) => $q->where('year', $year)->where('month', $month));
                    }
                })
                ->get()
                ->groupBy('user_id');

        $saleUsers = $saleIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $saleIds)->get(['id', 'name'])->keyBy('id');
        $contactGroups = $contactRangeOrders->groupBy('sale_user_id');
        $closingGroups = $closingRangeOrders->groupBy('sale_user_id');

        $columns = [
            $this->col('telesale', 'name', 'text'),
            $this->col('new_contacts', 'new_contacts', 'number'),
            $this->col('new_closed', 'new_closed', 'number'),
            $this->col('new_rate', 'new_rate', 'percent', ['tone' => 'positive']),
            $this->col('old_contacts', 'old_contacts', 'number'),
            $this->col('old_closed', 'old_closed', 'number'),
            $this->col('old_rate', 'old_rate', 'percent', ['tone' => 'positive']),
            $this->col('total_closed', 'total_closed', 'number'),
            $this->col('expected_rev', 'expected_rev', 'currency'),
            $this->col('base_salary', 'base_salary', 'currency'),
            $this->col('bonus', 'bonus', 'currency'),
            $this->col('income', 'income', 'currency'),
            $this->col('actual_rev', 'actual_rev', 'currency'),
            $this->col('upsell_qty', 'upsell_qty', 'number'),
            $this->col('upsell_rev', 'upsell_rev', 'currency'),
        ];

        $rows = $saleIds->map(function (int $saleId) use ($plans, $saleUsers, $contactGroups, $closingGroups): array {
            $assignedGroup = collect($contactGroups->get($saleId, collect()));
            $closingGroup = collect($closingGroups->get($saleId, collect()));

            $assignedContacts = $this->contactOrders($assignedGroup);
            $newContacts = $assignedContacts->where('is_returning_customer', false);
            $oldContacts = $assignedContacts->where('is_returning_customer', true);

            $closedOrders = $this->closed($closingGroup);
            $closedContactOrders = $this->closed($this->contactOrders($closingGroup));
            $newClosed = $closedContactOrders->where('is_returning_customer', false);
            $oldClosed = $closedContactOrders->where('is_returning_customer', true);
            $actual = $this->bucket($closedOrders, self::SUCCESS);
            $closedItems = $closedOrders->flatMap(fn (Order $order) => $order->items);
            $upsellItems = $closedItems->where('item_type', 'upsell');

            $userPlans = collect($plans->get($saleId, collect()));
            $configuredBaseSalary = (int) $userPlans->sum('base_salary');
            $baseSalary = (int) $userPlans->sum(function (MonthlyKpiPlan $plan): int {
                $workingDays = max(1, (int) $plan->working_days);
                $actualDays = $plan->actual_days !== null ? max(0, (int) $plan->actual_days) : $workingDays;

                return (int) round(((int) $plan->base_salary * min($actualDays, $workingDays)) / $workingDays);
            });
            $expectedRevenue = (int) $closedOrders->sum(fn (Order $order): int => $order->netRevenue());
            $bonusPercent = (float) ($userPlans->avg('bonus_percent') ?? 0);
            $bonus = (int) round($expectedRevenue * $bonusPercent / 100);
            $targetRevenue = (int) $userPlans->sum('revenue_target');
            $targetBonus = (int) round($targetRevenue * $bonusPercent / 100);
            $targetNewContacts = (int) $userPlans->sum('new_contacts_target');
            $targetNewClosed = (int) $userPlans->sum('new_closed_target');
            $targetOldContacts = (int) $userPlans->sum('old_contacts_target');
            $targetOldClosed = (int) $userPlans->sum('old_closed_target');

            return [
                'sale_id' => $saleId,
                'name' => $saleUsers->get($saleId)?->name
                    ?? $assignedGroup->first()?->saleUser?->name
                    ?? $closingGroup->first()?->saleUser?->name
                    ?? '—',
                'new_contacts' => $newContacts->count(),
                'new_closed' => $newClosed->count(),
                'new_rate' => self::pct($newClosed->count(), $newContacts->count()),
                'old_contacts' => $oldContacts->count(),
                'old_closed' => $oldClosed->count(),
                'old_rate' => self::pct($oldClosed->count(), $oldContacts->count()),
                'total_closed' => $closedContactOrders->count(),
                'expected_rev' => $expectedRevenue,
                'base_salary' => $baseSalary,
                'bonus' => $bonus,
                'income' => $baseSalary + $bonus,
                'actual_rev' => (int) $actual->sum(fn (Order $order): int => $order->netRevenue()),
                'upsell_qty' => (int) $upsellItems->sum('quantity'),
                'upsell_rev' => (int) $upsellItems->sum(fn (OrderItem $item): int => $item->lineTotal()),
                'target_new_contacts' => $targetNewContacts,
                'target_new_closed' => $targetNewClosed,
                'target_new_rate' => self::pct($targetNewClosed, $targetNewContacts),
                'target_old_contacts' => $targetOldContacts,
                'target_old_closed' => $targetOldClosed,
                'target_old_rate' => self::pct($targetOldClosed, $targetOldContacts),
                'target_total_closed' => $targetNewClosed + $targetOldClosed,
                'target_expected_rev' => $targetRevenue,
                'target_base_salary' => $configuredBaseSalary,
                'target_bonus' => $targetBonus,
                'target_income' => $configuredBaseSalary + $targetBonus,
                'target_actual_rev' => $targetRevenue,
                'bonus_percent' => $bonusPercent,
            ];
        })->sortByDesc('expected_rev')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['new_rate'] = self::pct($totals['new_closed'] ?? 0, $totals['new_contacts'] ?? 0);
        $totals['old_rate'] = self::pct($totals['old_closed'] ?? 0, $totals['old_contacts'] ?? 0);
        foreach ([
            'target_new_contacts', 'target_new_closed', 'target_old_contacts', 'target_old_closed',
            'target_total_closed', 'target_expected_rev', 'target_base_salary', 'target_bonus',
            'target_income', 'target_actual_rev',
        ] as $targetKey) {
            $totals[$targetKey] = array_sum(array_map(fn (array $row): int => (int) ($row[$targetKey] ?? 0), $rows));
        }
        $totals['target_new_rate'] = self::pct($totals['target_new_closed'], $totals['target_new_contacts']);
        $totals['target_old_rate'] = self::pct($totals['target_old_closed'], $totals['target_old_contacts']);

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    private function saleAppointments(User $user, ReportFilterData $filter): array
    {
        $saleIds = $this->visibleSaleIds($user, $filter);

        $orders = Order::query()
            ->with('saleUser:id,name')
            ->whereNotNull('next_operation_at')
            ->whereBetween('next_operation_at', [($filter->dateFrom ?? now()->startOfDay())->copy()->startOfDay(), ($filter->dateTo ?? now()->addDays(6)->endOfDay())->copy()->endOfDay()])
            ->when($filter->operationStage, fn ($q) => $q->where('operation_stage', $filter->operationStage))
            ->when($filter->operationResult, fn ($q) => $q->where('operation_result', $filter->operationResult))
            ->when($filter->teamId, fn ($q) => $q->where('team_id', $filter->teamId))
            ->when($saleIds !== null, fn ($q) => $q->whereIn('sale_user_id', $saleIds))
            ->get();

        $columns = [
            $this->col('date', 'date', 'text'),
            $this->col('weekday', 'weekday', 'text'),
            $this->col('appointment_count', 'count', 'number'),
            $this->col('sales_assigned', 'sales', 'text'),
        ];

        $rows = [];

        $from = ($filter->dateFrom ?? now()->startOfDay())->copy()->startOfDay();
        $to = ($filter->dateTo ?? now()->addDays(6)->endOfDay())->copy()->startOfDay();
        $days = min(31, max(1, $from->diffInDays($to) + 1));

        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $dayOrders = $orders->filter(fn (Order $o) => $o->next_operation_at?->isSameDay($day));

            $rows[] = [
                'date' => $day->format('d/m/Y'),
                'date_iso' => $day->toDateString(),
                'weekday' => $day->isToday() ? __('reports.today') : __('reports.weekdays.'.$day->dayOfWeek),
                'count' => $dayOrders->count(),
                'overdue' => $day->isBefore(now()->startOfDay()),
                'sales' => $dayOrders->pluck('saleUser.name')->filter()->unique()->implode(', ') ?: '—',
            ];
        }

        $totals = ['date' => __('reports.total'), 'count' => array_sum(array_column($rows, 'count'))];

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    // ─── Marketing / Kho ─────────────────────────────────────────────────────

    private function productClosing(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrders(
            $user,
            $filter,
            scopeSales: $user->role === UserRole::Sales,
            scopeMarketing: $user->role === UserRole::Marketing,
        )->filter(fn (Order $o) => $o->product_id !== null);

        $columns = [
            $this->col('product', 'name', 'text'),
            $this->col('sku', 'sku', 'text'),
            $this->col('contacts', 'contacts', 'number'),
            $this->col('closed', 'closed', 'number'),
            $this->col('rate', 'rate', 'percent', ['tone' => 'positive']),
            $this->col('revenue', 'revenue', 'currency'),
            $this->col('avg', 'avg', 'currency'),
        ];

        $rows = $orders->groupBy('product_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $contactOrders = $this->contactOrders($group);
            $closedContacts = $this->closed($contactOrders);
            $revenue = (int) $closed->sum(fn (Order $o) => $o->netRevenue());

            return [
                'name' => $group->first()->product?->name ?? '—',
                'sku' => $group->first()->product?->sku,
                'contacts' => $contactOrders->count(),
                'closed' => $closedContacts->count(),
                'rate' => self::pct($closedContacts->count(), $contactOrders->count()),
                'revenue' => $revenue,
                'avg' => $closed->count() > 0 ? (int) round($revenue / $closed->count()) : null,
            ];
        })->sortByDesc('revenue')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['rate'] = self::pct($totals['closed'] ?? 0, $totals['contacts'] ?? 0);
        $totals['avg'] = ($totals['closed'] ?? 0) > 0
            ? (int) round(($totals['revenue'] ?? 0) / $totals['closed'])
            : null;

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Báo cáo công việc marketing — theo từng nhân viên marketing:
     * tổng contact tạo ra, contact chưa phân bổ cho sale, đơn chốt, tỷ lệ chốt,
     * số lượng sản phẩm bán ra & doanh số.
     */
    private function marketingWork(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrdersWithItems($user, $filter, scopeMarketing: true)
            ->filter(fn (Order $o) => $o->marketer_user_id !== null);

        $marketerIds = $this->visibleMarketerIds($user, $filter);

        $leadsQuery = LeadContactMetrics::countableQuery($filter)
            ->with(['marketingSource:id,marketer_user_id,name', 'marketingSource.marketer:id,name'])
            ->whereNotNull('marketing_source_id')
            ->when($marketerIds !== null, function ($q) use ($marketerIds) {
                $q->whereHas('marketingSource', fn ($sq) => $sq->whereIn('marketer_user_id', $marketerIds));
            })
            ->when($filter->marketerId, function ($q) use ($filter) {
                $q->whereHas('marketingSource', fn ($sq) => $sq->where('marketer_user_id', $filter->marketerId));
            });

        $leads = $leadsQuery->get();
        $contactCountsByMarketer = LeadContactMetrics::effectiveCountsByMarketer($filter, $orders);

        $columns = [
            $this->col('marketer', 'name', 'text'),
            $this->col('contacts_total', 'contacts', 'number'),
            $this->col('unallocated', 'unallocated', 'number'),
            $this->col('closed', 'closed', 'number'),
            $this->col('rate', 'rate', 'percent', ['tone' => 'positive']),
            $this->col('qty_sold', 'qty_sold', 'number'),
            $this->col('revenue', 'revenue', 'currency'),
        ];

        $marketerIdsInScope = $orders->pluck('marketer_user_id')
            ->merge($leads->map(fn (LeadIngestion $l) => $l->marketingSource?->marketer_user_id))
            ->filter()
            ->unique()
            ->values();

        $rows = $marketerIdsInScope->map(function (int $marketerId) use ($orders, $leads, $contactCountsByMarketer) {
            $group = $orders->where('marketer_user_id', $marketerId);
            $marketerLeads = $leads->filter(fn (LeadIngestion $l) => (int) $l->marketingSource?->marketer_user_id === $marketerId);
            $closed = $this->closed($group);
            $closedContacts = $this->closed($this->contactOrders($group));
            $revenue = (int) $closed->sum(fn (Order $o) => $o->netRevenue());
            $contactCount = (int) $contactCountsByMarketer->get($marketerId, 0);
            $unallocated = $marketerLeads->filter(
                fn (LeadIngestion $l) => $l->order_id === null
                    && in_array($l->status->value, ['pending', 'gathering'], true),
            )->count() + $group->whereNull('sale_user_id')->count();

            $name = $group->first()?->marketerUser?->name
                ?? $marketerLeads->first()?->marketingSource?->marketer?->name
                ?? User::query()->find($marketerId)?->name
                ?? '—';

            return [
                'name' => $name,
                'contacts' => $contactCount,
                'unallocated' => $unallocated,
                'closed' => $closedContacts->count(),
                'rate' => self::pct($closedContacts->count(), $contactCount),
                'qty_sold' => (int) $closed->sum(fn (Order $o) => $o->items->sum('quantity')),
                'revenue' => $revenue,
            ];
        })->sortByDesc('revenue')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['rate'] = self::pct($totals['closed'] ?? 0, $totals['contacts'] ?? 0);

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Báo cáo upsale — theo từng NGUỒN DỮ LIỆU (chiến dịch):
     * contact, đơn chốt, tỷ lệ chốt, số loại & số lượng SP bán ra, doanh số,
     * giá trị đơn TB, số SP TB/đơn, và riêng phần upsale (SL + doanh số) để đo
     * hiệu quả bán thêm ở trang cảm ơn.
     */
    private function upsaleReport(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrdersWithItems($user, $filter, scopeMarketing: $user->role === UserRole::Marketing)
            ->filter(fn (Order $o) => $o->marketing_source_id !== null);
        $contactCountsBySource = LeadContactMetrics::effectiveCountsBySource($filter, $orders);

        $columns = [
            $this->col('source', 'name', 'text'),
            $this->col('channel', 'channel', 'text'),
            $this->col('contacts', 'contacts', 'number'),
            $this->col('closed', 'closed', 'number'),
            $this->col('rate', 'rate', 'percent', ['tone' => 'positive']),
            $this->col('product_types', 'product_types', 'number'),
            $this->col('qty_sold', 'qty_sold', 'number'),
            $this->col('revenue', 'revenue', 'currency'),
            $this->col('avg_order', 'avg_order', 'currency'),
            $this->col('items_per_order', 'items_per_order', 'number'),
            $this->col('upsell_qty', 'upsell_qty', 'number'),
            $this->col('upsell_rev', 'upsell_rev', 'currency'),
        ];

        $rows = $orders->groupBy('marketing_source_id')->map(function (Collection $group, int|string $sourceId) use ($contactCountsBySource) {
            $closed = $this->closed($group);
            $closedContacts = $this->closed($this->contactOrders($group));
            $revenue = (int) $closed->sum(fn (Order $o) => $o->netRevenue());
            $closedItems = $closed->flatMap(fn (Order $o) => $o->items);
            $qtySold = (int) $closedItems->sum('quantity');
            $upsellItems = $closedItems->filter(fn (OrderItem $i) => $i->item_type === 'upsell');
            $source = $group->first()->marketingSource;
            $contacts = (int) $contactCountsBySource->get((int) $sourceId, 0);

            return [
                'name' => $source?->name ?? '—',
                'channel' => $source?->ad_channel ?? '—',
                'contacts' => $contacts,
                'closed' => $closedContacts->count(),
                'rate' => self::pct($closedContacts->count(), $contacts),
                'product_types' => $closedItems->map(fn (OrderItem $i) => $i->product_id ?? $i->product_name)->unique()->count(),
                'qty_sold' => $qtySold,
                'revenue' => $revenue,
                'avg_order' => $closed->count() > 0 ? (int) round($revenue / $closed->count()) : null,
                'items_per_order' => $closed->count() > 0 ? round($qtySold / $closed->count(), 1) : null,
                'upsell_qty' => (int) $upsellItems->sum('quantity'),
                'upsell_rev' => (int) $upsellItems->sum(fn (OrderItem $i) => $i->lineTotal()),
            ];
        })->sortByDesc('revenue')->values()->all();

        $globalClosed = $this->closed($orders);
        $globalClosedCount = $globalClosed->count();
        $globalItems = $globalClosed->flatMap(fn (Order $o) => $o->items);

        $totals = $this->sumTotals($columns, $rows);
        $totals['rate'] = self::pct($totals['closed'] ?? 0, $totals['contacts'] ?? 0);
        $totals['product_types'] = $globalItems->map(fn (OrderItem $i) => $i->product_id ?? $i->product_name)->unique()->count();
        $totals['avg_order'] = $globalClosedCount > 0 ? (int) round(($totals['revenue'] ?? 0) / $globalClosedCount) : null;
        $totals['items_per_order'] = $globalClosedCount > 0 ? round(($totals['qty_sold'] ?? 0) / $globalClosedCount, 1) : null;

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Bảng tổng hợp chờ xuất theo ngày — bám đúng cấu trúc màn hình Pushsale.
     * Dữ liệu lấy trực tiếp từ tồn kho hiện tại để bảng luôn đồng bộ với trang
     * "Danh sách sản phẩm kho".
     */
    private function warehousePending(ReportFilterData $filter): array
    {
        $inventories = WarehouseInventory::query()
            ->with(['warehouse:id,name', 'product:id,name,sku'])
            ->when($filter->warehouseId, fn ($query) => $query->where('warehouse_id', $filter->warehouseId))
            ->when($filter->productId, fn ($query) => $query->where('product_id', $filter->productId))
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get();

        $columns = [
            $this->col('warehouse', 'warehouse', 'text'),
            $this->col('product', 'product', 'text'),
            ['key' => 'batch', 'label_key' => 'batch', 'label' => 'Mã lô', 'format' => 'text'],
            ['key' => 'opening', 'label_key' => 'opening', 'label' => 'Đầu kỳ', 'format' => 'number'],
            ['key' => 'pending', 'label_key' => 'pending', 'label' => 'Chờ xuất', 'format' => 'number'],
            ['key' => 'sales_export', 'label_key' => 'sales_export', 'label' => 'Xuất bán hàng', 'format' => 'number'],
            ['key' => 'ending', 'label_key' => 'ending', 'label' => 'Cuối kỳ', 'format' => 'number'],
        ];

        $rows = $inventories->map(function (WarehouseInventory $inventory): array {
            $opening = (int) $inventory->stock_quantity;
            $pending = (int) $inventory->pending_sales_quantity;

            return [
                'warehouse' => $inventory->warehouse?->name ?? '—',
                'product' => trim(($inventory->product?->name ?? '—').' '.($inventory->product?->sku ? '('.$inventory->product->sku.')' : '')),
                'batch' => $inventory->batch_code,
                'opening' => $opening,
                'pending' => $pending,
                'sales_export' => 0,
                'ending' => $opening + $pending,
            ];
        })->all();

        return ['columns' => $columns, 'rows' => $rows, 'totals' => null];
    }

    private function warehouseRevenue(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrders($user, $filter)
            ->filter(fn (Order $o) => $o->warehouse_id !== null);

        $columns = [
            $this->col('warehouse', 'name', 'text'),
            $this->col('closed_qty', 'closed_qty', 'number'),
            $this->col('closed_rev_wh', 'closed_rev', 'currency'),
            $this->col('confirmed_qty', 'confirmed_qty', 'number'),
            $this->col('confirmed_rev', 'confirmed_rev', 'currency'),
            $this->col('delivering_qty', 'delivering_qty', 'number'),
            $this->col('delivering_rev', 'delivering_rev', 'currency'),
            $this->col('returned_qty', 'returned_qty', 'number'),
            $this->col('returned_rev', 'returned_rev', 'currency'),
            $this->col('discount', 'discount', 'currency'),
        ];

        $rows = $orders->groupBy('warehouse_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $confirmed = $this->bucket($closed, array_merge(self::DELIVERED, self::PAID));
            $delivering = $this->bucket($closed, self::DELIVERING);
            $returned = $this->bucket($closed, self::RETURNED);

            return [
                'name' => $group->first()->warehouse?->name ?? '—',
                'closed_qty' => $closed->count(),
                'closed_rev' => (int) $closed->sum(fn (Order $o) => $o->netRevenue()),
                'confirmed_qty' => $confirmed->count(),
                'confirmed_rev' => (int) $confirmed->sum(fn (Order $o) => $o->netRevenue()),
                'delivering_qty' => $delivering->count(),
                'delivering_rev' => (int) $delivering->sum(fn (Order $o) => $o->netRevenue()),
                'returned_qty' => $returned->count(),
                'returned_rev' => (int) $returned->sum(fn (Order $o) => $o->netRevenue()),
                'discount' => (int) $closed->sum('discount'),
            ];
        })->sortByDesc('closed_rev')->values()->all();

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->sumTotals($columns, $rows)];
    }

    private function systemBusiness(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrders($user, $filter)
            ->filter(fn (Order $o) => $o->warehouse_id !== null);

        $columns = [
            $this->col('warehouse', 'name', 'text'),
            $this->col('closed_qty', 'closed_qty', 'number'),
            $this->col('total_revenue', 'revenue', 'currency'),
            $this->col('avg_per_order', 'avg', 'currency'),
            $this->col('new_qty', 'new_qty', 'number'),
            $this->col('new_rev', 'new_rev', 'currency'),
            $this->col('new_share', 'new_share', 'percent'),
            $this->col('old_qty', 'old_qty', 'number'),
            $this->col('old_rev', 'old_rev', 'currency'),
            $this->col('old_share', 'old_share', 'percent'),
        ];

        $rows = $orders->groupBy('warehouse_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $revenue = (int) $closed->sum(fn (Order $o) => $o->netRevenue());
            $new = $closed->where('is_returning_customer', false);
            $old = $closed->where('is_returning_customer', true);
            $newRev = (int) $new->sum(fn (Order $o) => $o->netRevenue());
            $oldRev = (int) $old->sum(fn (Order $o) => $o->netRevenue());

            return [
                'name' => $group->first()->warehouse?->name ?? '—',
                'closed_qty' => $closed->count(),
                'revenue' => $revenue,
                'avg' => $closed->count() > 0 ? (int) round($revenue / $closed->count()) : null,
                'new_qty' => $new->count(),
                'new_rev' => $newRev,
                'new_share' => self::pct($newRev, $revenue),
                'old_qty' => $old->count(),
                'old_rev' => $oldRev,
                'old_share' => self::pct($oldRev, $revenue),
            ];
        })->sortByDesc('revenue')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $revenue = $totals['revenue'] ?? 0;
        $totals['avg'] = ($totals['closed_qty'] ?? 0) > 0 ? (int) round($revenue / $totals['closed_qty']) : null;
        $totals['new_share'] = self::pct($totals['new_rev'] ?? 0, $revenue);
        $totals['old_share'] = self::pct($totals['old_rev'] ?? 0, $revenue);

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }


    /**
     * Menu 4.5.5 — báo cáo doanh số theo kho theo đủ 12 nhóm của Pushsale.
     * Mỗi nhóm có doanh số, số đơn, giá trị trung bình, số sản phẩm và số
     * sản phẩm/đơn. Upsell được bổ sung thành nhóm riêng nhưng không làm tăng
     * contact hay số đơn chốt từ lead gốc.
     */
    private function warehouseSalesSummary(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrdersWithItems($user, $filter)
            ->filter(fn (Order $order): bool => $order->warehouse_id !== null);
        $groups = $this->warehouseRevenueGroupDefinitions();

        $columns = [$this->col('warehouse', 'name', 'text')];
        foreach ($groups as $group) {
            $prefix = $group['key'];
            $columns[] = $this->warehouseMetricColumn($group['label'], 'revenue', "{$prefix}_revenue", 'currency');
            $columns[] = $this->warehouseMetricColumn($group['label'], 'orders', "{$prefix}_orders", 'number');
            $columns[] = $this->warehouseMetricColumn($group['label'], 'avg', "{$prefix}_avg", 'currency');
            $columns[] = $this->warehouseMetricColumn($group['label'], 'product_count', "{$prefix}_products", 'number');
            $columns[] = $this->warehouseMetricColumn($group['label'], 'items_per_order', "{$prefix}_products_per_order", 'number');
        }
        $columns[] = $this->col('upsell_qty', 'upsell_qty', 'number');
        $columns[] = $this->col('upsell_rev', 'upsell_revenue', 'currency');
        $columns[] = $this->col('upsell_revenue_share', 'upsell_share', 'percent');

        $rows = $orders->groupBy('warehouse_id')->map(function (Collection $warehouseOrders) use ($groups): array {
            $closed = $this->closed($warehouseOrders);
            $buckets = $this->warehouseRevenueBuckets($closed);
            $row = [
                'warehouse_id' => (int) $warehouseOrders->first()->warehouse_id,
                'name' => $warehouseOrders->first()->warehouse?->name ?? 'Chưa chọn kho',
            ];

            foreach ($groups as $group) {
                $prefix = $group['key'];
                $metrics = $this->warehouseRevenueMetrics($buckets[$prefix], $prefix === 'discount');
                $row["{$prefix}_revenue"] = $metrics['revenue'];
                $row["{$prefix}_orders"] = $metrics['orders'];
                $row["{$prefix}_avg"] = $metrics['avg'];
                $row["{$prefix}_products"] = $metrics['products'];
                $row["{$prefix}_products_per_order"] = $metrics['products_per_order'];
            }

            $upsellItems = $closed
                ->flatMap(fn (Order $order) => $order->items)
                ->where('item_type', 'upsell');
            $upsellRevenue = (int) $upsellItems->sum(fn (OrderItem $item): int => $item->lineTotal());
            $row['upsell_qty'] = (int) $upsellItems->sum('quantity');
            $row['upsell_revenue'] = $upsellRevenue;
            $row['upsell_share'] = self::pct($upsellRevenue, $row['total_revenue']);

            return $row;
        })->sortByDesc('total_revenue')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        foreach ($groups as $group) {
            $prefix = $group['key'];
            $ordersKey = "{$prefix}_orders";
            $totals["{$prefix}_avg"] = ($totals[$ordersKey] ?? 0) > 0
                ? (int) round(($totals["{$prefix}_revenue"] ?? 0) / $totals[$ordersKey])
                : 0;
            $totals["{$prefix}_products_per_order"] = ($totals[$ordersKey] ?? 0) > 0
                ? round(($totals["{$prefix}_products"] ?? 0) / $totals[$ordersKey], 2)
                : 0;
        }
        $totals['upsell_share'] = self::pct($totals['upsell_revenue'] ?? 0, $totals['total_revenue'] ?? 0);

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'extra' => ['revenueGroups' => $groups, 'defaultRevenueGroups' => ['total', 'confirmed', 'estimated', 'discount']],
        ];
    }

    /**
     * Menu 4.5.6 — báo cáo doanh số V2 theo kho. Giữ phễu contact của hệ
     * thống mới, sau đó hiển thị đủ 12 nhóm doanh số Pushsale; mỗi nhóm gồm
     * số đơn, số sản phẩm, giá trị trung bình và doanh số.
     */
    private function warehouseSalesV2(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrdersWithItems($user, $filter)
            ->filter(fn (Order $order): bool => $order->warehouse_id !== null);
        $groups = $this->warehouseRevenueGroupDefinitions();

        $columns = [
            $this->col('warehouse', 'name', 'text'),
            $this->col('contacts', 'contacts', 'number'),
            $this->col('closed', 'closed_contacts', 'number'),
            $this->col('rate', 'close_rate', 'percent'),
        ];
        foreach ($groups as $group) {
            $prefix = $group['key'];
            $columns[] = $this->warehouseMetricColumn($group['label'], 'orders', "{$prefix}_orders", 'number');
            $columns[] = $this->warehouseMetricColumn($group['label'], 'product_count', "{$prefix}_products", 'number');
            $columns[] = $this->warehouseMetricColumn($group['label'], 'avg', "{$prefix}_avg", 'currency');
            $columns[] = $this->warehouseMetricColumn($group['label'], 'revenue', "{$prefix}_revenue", 'currency');
        }
        $columns[] = $this->col('upsell_qty', 'upsell_qty', 'number');
        $columns[] = $this->col('upsell_rev', 'upsell_revenue', 'currency');
        $columns[] = $this->col('upsell_revenue_share', 'upsell_share', 'percent');

        $rows = $orders->groupBy('warehouse_id')->map(function (Collection $warehouseOrders) use ($groups): array {
            $contacts = $this->contactOrders($warehouseOrders);
            $closedContacts = $this->closed($contacts);
            $closed = $this->closed($warehouseOrders);
            $buckets = $this->warehouseRevenueBuckets($closed);
            $row = [
                'warehouse_id' => (int) $warehouseOrders->first()->warehouse_id,
                'name' => $warehouseOrders->first()->warehouse?->name ?? 'Chưa chọn kho',
                'contacts' => $contacts->count(),
                'closed_contacts' => $closedContacts->count(),
                'close_rate' => self::pct($closedContacts->count(), $contacts->count()),
            ];

            foreach ($groups as $group) {
                $prefix = $group['key'];
                $metrics = $this->warehouseRevenueMetrics($buckets[$prefix], $prefix === 'discount');
                $row["{$prefix}_orders"] = $metrics['orders'];
                $row["{$prefix}_products"] = $metrics['products'];
                $row["{$prefix}_avg"] = $metrics['avg'];
                $row["{$prefix}_revenue"] = $metrics['revenue'];
            }

            $upsellItems = $closed
                ->flatMap(fn (Order $order) => $order->items)
                ->where('item_type', 'upsell');
            $upsellRevenue = (int) $upsellItems->sum(fn (OrderItem $item): int => $item->lineTotal());
            $row['upsell_qty'] = (int) $upsellItems->sum('quantity');
            $row['upsell_revenue'] = $upsellRevenue;
            $row['upsell_share'] = self::pct($upsellRevenue, $row['total_revenue']);

            return $row;
        })->sortByDesc('total_revenue')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['close_rate'] = self::pct($totals['closed_contacts'] ?? 0, $totals['contacts'] ?? 0);
        foreach ($groups as $group) {
            $prefix = $group['key'];
            $ordersKey = "{$prefix}_orders";
            $totals["{$prefix}_avg"] = ($totals[$ordersKey] ?? 0) > 0
                ? (int) round(($totals["{$prefix}_revenue"] ?? 0) / $totals[$ordersKey])
                : 0;
        }
        $totals['upsell_share'] = self::pct($totals['upsell_revenue'] ?? 0, $totals['total_revenue'] ?? 0);

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'extra' => ['revenueGroups' => $groups, 'defaultRevenueGroups' => ['total', 'confirmed', 'estimated', 'discount']],
        ];
    }

    /**
     * Định nghĩa 12 nhóm doanh số đúng thứ tự/chú thích của template 4.5.5 và
     * 4.5.6. Dữ liệu metadata này được trả về frontend để điều khiển việc
     * hiện/ẩn nhóm cột mà không phải nhúng dữ liệu mẫu trong HTML.
     *
     * @return list<array{key: string, number: int, label: string, description: string}>
     */
    private function warehouseRevenueGroupDefinitions(): array
    {
        return collect([
            ['key' => 'total', 'number' => 1],
            ['key' => 'confirmed', 'number' => 2],
            ['key' => 'estimated', 'number' => 3],
            ['key' => 'discount', 'number' => 4],
            ['key' => 'cancelled', 'number' => 5],
            ['key' => 'returning', 'number' => 6],
            ['key' => 'returned', 'number' => 7],
            ['key' => 'transit', 'number' => 8],
            ['key' => 'success', 'number' => 9],
            ['key' => 'actual', 'number' => 10],
            ['key' => 'pending_reconciliation', 'number' => 11],
            ['key' => 'partial', 'number' => 12],
        ])->map(fn (array $group): array => [
            ...$group,
            'label' => __("reports.warehouse_groups.{$group['key']}.label"),
            'description' => __("reports.warehouse_groups.{$group['key']}.description"),
        ])->all();
    }

    /** @return array<string, Collection<int, Order>> */
    private function warehouseRevenueBuckets(Collection $closed): array
    {
        return OrderRevenueClassifier::buckets($closed);
    }

    /**
     * @return array{revenue: int, orders: int, avg: int, products: int, products_per_order: float}
     */
    private function warehouseRevenueMetrics(Collection $orders, bool $discountMetric = false): array
    {
        $orderCount = $orders->count();
        $revenue = $discountMetric
            ? (int) $orders->sum('discount')
            : (int) $orders->sum(fn (Order $order): int => $order->netRevenue());
        $products = (int) $orders
            ->flatMap(fn (Order $order) => $order->items)
            ->sum(fn (OrderItem $item): int => max(0, (int) $item->quantity));

        return [
            'revenue' => $revenue,
            'orders' => $orderCount,
            'avg' => $orderCount > 0 ? (int) round($revenue / $orderCount) : 0,
            'products' => $products,
            'products_per_order' => $orderCount > 0 ? round($products / $orderCount, 2) : 0,
        ];
    }

    private function warehouseMetricColumn(string $groupLabel, string $labelKey, string $key, string $format): array
    {
        return $this->col($labelKey, $key, $format, [
            'label' => $groupLabel.' — '.__("reports.columns.{$labelKey}"),
        ]);
    }

    /**
     * Menu 6.3.9 — ma trận tỉ lệ chốt theo sản phẩm và nhân sự. Mỗi dòng hàng
     * dùng doanh thu của chính order_item để không nhân doanh thu đơn khi đơn
     * có nhiều sản phẩm; sản phẩm upsell được tách riêng nhưng vẫn cộng vào
     * doanh thu tổng.
     */
    private function productConversionMatrix(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrdersWithItems(
            $user,
            $filter,
            scopeSales: $user->role === UserRole::Sales,
            scopeMarketing: $user->role === UserRole::Marketing,
        );
        $contactOrderIds = collect(LeadContactMetrics::contactOrderIds($orders))->map('intval');

        $people = collect();
        if ($user->role !== UserRole::Marketing) {
            $people = $people->merge($orders->map(fn (Order $order) => $order->saleUser)->filter()->unique('id')->map(fn (User $person) => [
                'id' => $person->id,
                'role' => 'sales',
                'name' => $person->name,
                'username' => strstr($person->email, '@', true) ?: $person->email,
                'foreign_key' => 'sale_user_id',
            ]));
        }
        if ($user->role !== UserRole::Sales) {
            $people = $people->merge($orders->map(fn (Order $order) => $order->marketerUser)->filter()->unique('id')->map(fn (User $person) => [
                'id' => $person->id,
                'role' => 'marketing',
                'name' => $person->name,
                'username' => strstr($person->email, '@', true) ?: $person->email,
                'foreign_key' => 'marketer_user_id',
            ]));
        }
        $people = $people->unique(fn (array $person): string => $person['role'].'-'.$person['id'])->values();

        $productBuckets = collect();
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                if ($filter->productId && (int) $item->product_id !== $filter->productId) {
                    continue;
                }
                $productKey = $item->product_id ? 'id:'.$item->product_id : 'name:'.mb_strtolower(trim((string) $item->product_name));
                if (! $productBuckets->has($productKey)) {
                    $productBuckets->put($productKey, collect());
                }
                $productBuckets->get($productKey)->push(['order' => $order, 'item' => $item]);
            }
        }

        $rows = $productBuckets->map(function (Collection $entries, string $productKey) use ($people, $contactOrderIds): array {
            /** @var OrderItem $firstItem */
            $firstItem = $entries->first()['item'];
            $orders = $entries->pluck('order')->unique('id')->values();
            $closedOrders = $this->closed($orders);
            $contactOrders = $orders->filter(fn (Order $order): bool => $contactOrderIds->contains((int) $order->id));
            $closedContactOrders = $this->closed($contactOrders);
            $closedOrderIds = $closedOrders->pluck('id');
            $closedEntries = $entries->filter(fn (array $entry): bool => $closedOrderIds->contains($entry['order']->id));
            $revenue = (int) $closedEntries->sum(fn (array $entry): int => $entry['item']->lineTotal());
            $upsellEntries = $closedEntries->filter(fn (array $entry): bool => $entry['item']->item_type === 'upsell');
            $row = [
                'product_key' => $productKey,
                'product_id' => $firstItem->product_id,
                'name' => $firstItem->product?->name ?? $firstItem->product_name ?? 'Sản phẩm chưa đặt tên',
                'contacts' => $contactOrders->count(),
                'closed' => $closedContactOrders->count(),
                'rate' => self::pct($closedContactOrders->count(), $contactOrders->count()),
                'revenue' => $revenue,
                'avg' => $closedOrders->count() > 0 ? (int) round($revenue / $closedOrders->count()) : 0,
                'quantity' => (int) $closedEntries->sum(fn (array $entry): int => (int) $entry['item']->quantity),
                'upsell_qty' => (int) $upsellEntries->sum(fn (array $entry): int => (int) $entry['item']->quantity),
                'upsell_revenue' => (int) $upsellEntries->sum(fn (array $entry): int => $entry['item']->lineTotal()),
            ];
            $row['upsell_share'] = self::pct($row['upsell_revenue'], $revenue);

            foreach ($people as $person) {
                $prefix = 'person_'.$person['role'].'_'.$person['id'].'_';
                $personOrders = $orders->where($person['foreign_key'], $person['id']);
                $personClosed = $this->closed($personOrders);
                $personContactOrders = $personOrders->filter(fn (Order $order): bool => $contactOrderIds->contains((int) $order->id));
                $personClosedContacts = $this->closed($personContactOrders);
                $personClosedIds = $personClosed->pluck('id');
                $personEntries = $closedEntries->filter(fn (array $entry): bool => $personClosedIds->contains($entry['order']->id));
                $personRevenue = (int) $personEntries->sum(fn (array $entry): int => $entry['item']->lineTotal());
                $personContacts = $personContactOrders->count();
                $row[$prefix.'contacts'] = $personContacts;
                $row[$prefix.'closed'] = $personClosedContacts->count();
                $row[$prefix.'rate'] = self::pct($personClosedContacts->count(), $personContacts);
                $row[$prefix.'revenue'] = $personRevenue;
                $row[$prefix.'avg'] = $personClosed->count() > 0 ? (int) round($personRevenue / $personClosed->count()) : 0;
            }

            return $row;
        })->sortByDesc('revenue')->values()->all();

        $columns = [
            ['key' => 'product_id', 'label' => 'ID', 'format' => 'number'],
            $this->col('product', 'name', 'text'),
            $this->col('contacts', 'contacts', 'number'),
            $this->col('closed', 'closed', 'number'),
            $this->col('rate', 'rate', 'percent'),
            $this->col('revenue', 'revenue', 'currency'),
            $this->col('avg', 'avg', 'currency'),
            $this->col('upsell_qty', 'upsell_qty', 'number'),
            $this->col('upsell_rev', 'upsell_revenue', 'currency'),
            $this->col('upsell_revenue_share', 'upsell_share', 'percent'),
        ];
        foreach ($people as $person) {
            $prefix = 'person_'.$person['role'].'_'.$person['id'].'_';
            foreach ([
                ['contacts', 'Số contact', 'number'],
                ['closed', 'Số chốt đơn', 'number'],
                ['rate', 'Tỉ lệ chốt đơn', 'percent'],
                ['revenue', 'Doanh số', 'currency'],
                ['avg', 'AVG', 'currency'],
            ] as [$key, $label, $format]) {
                $columns[] = ['key' => $prefix.$key, 'label' => $person['name'].' - '.$label, 'format' => $format];
            }
        }

        $totals = $this->sumTotals($columns, $rows);
        $totals['rate'] = self::pct($totals['closed'] ?? 0, $totals['contacts'] ?? 0);
        $totals['avg'] = ($totals['closed'] ?? 0) > 0 ? (int) round(($totals['revenue'] ?? 0) / $totals['closed']) : 0;
        $totals['upsell_share'] = self::pct($totals['upsell_revenue'] ?? 0, $totals['revenue'] ?? 0);
        foreach ($people as $person) {
            $prefix = 'person_'.$person['role'].'_'.$person['id'].'_';
            $totals[$prefix.'rate'] = self::pct($totals[$prefix.'closed'] ?? 0, $totals[$prefix.'contacts'] ?? 0);
            $totals[$prefix.'avg'] = ($totals[$prefix.'closed'] ?? 0) > 0
                ? (int) round(($totals[$prefix.'revenue'] ?? 0) / $totals[$prefix.'closed'])
                : 0;
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'extra' => [
                'groups' => $people->map(fn (array $person): array => [
                    ...$person,
                    'prefix' => 'person_'.$person['role'].'_'.$person['id'].'_',
                    'label' => trim($person['name'].' ('.$person['username'].')'),
                ])->all(),
            ],
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function fetchOrders(
        User $user,
        ReportFilterData $filter,
        bool $scopeSales = false,
        bool $scopeMarketing = false,
    ): Collection {
        $saleIds = $scopeSales ? $this->visibleSaleIds($user, $filter) : null;
        $marketerIds = $scopeMarketing ? $this->visibleMarketerIds($user, $filter) : null;

        return Order::query()
            ->with(['saleUser:id,name,email,role', 'marketerUser:id,name,email,role', 'warehouse:id,name', 'product:id,name,sku'])
            ->applyReportFilter($filter)
            ->when($saleIds !== null, fn ($q) => $q->whereIn('sale_user_id', $saleIds))
            ->when($marketerIds !== null, fn ($q) => $q->whereIn('marketer_user_id', $marketerIds))
            ->get();
    }

    /**
     * Như fetchOrders nhưng eager-load thêm items + nguồn dữ liệu — dùng cho
     * báo cáo cần đếm số lượng/loại sản phẩm & tách phần upsale.
     */
    private function fetchOrdersWithItems(
        User $user,
        ReportFilterData $filter,
        bool $scopeSales = false,
        bool $scopeMarketing = false,
    ): Collection {
        $saleIds = $scopeSales ? $this->visibleSaleIds($user, $filter) : null;
        $marketerIds = $scopeMarketing ? $this->visibleMarketerIds($user, $filter) : null;

        return Order::query()
            ->with([
                'saleUser:id,name,email,role',
                'marketerUser:id,name,email,role',
                'warehouse:id,name',
                'product:id,name,sku',
                'marketingSource:id,name,ad_channel',
                'items:id,order_id,product_id,product_name,item_type,origin,quantity,unit_price,cost_price,discount_amount',
                'items.product:id,name,sku',
            ])
            ->applyReportFilter($filter)
            ->when($saleIds !== null, fn ($q) => $q->whereIn('sale_user_id', $saleIds))
            ->when($marketerIds !== null, fn ($q) => $q->whereIn('marketer_user_id', $marketerIds))
            ->get();
    }

    /** null = không giới hạn (admin / trưởng bộ phận). */
    private function visibleSaleIds(User $user, ?ReportFilterData $filter): ?array
    {
        if ($user->role === UserRole::Admin) {
            return $filter?->saleId ? [$filter->saleId] : null;
        }

        if ($user->role !== UserRole::Sales) {
            return null;
        }

        $allowed = $this->scope->allowedSaleIds($user);
        if ($filter?->saleId) {
            return in_array($filter->saleId, $allowed, true) ? [$filter->saleId] : [];
        }

        return $user->org_level === OrgLevel::Head ? null : $allowed;
    }

    /** null = không giới hạn. */
    private function visibleMarketerIds(User $user, ?ReportFilterData $filter): ?array
    {
        if ($user->role === UserRole::Admin) {
            return $filter?->marketerId ? [$filter->marketerId] : null;
        }

        if ($user->role !== UserRole::Marketing) {
            return null;
        }

        $allowed = $this->scope->allowedMarketerIds($user);
        if ($filter?->marketerId) {
            return in_array($filter->marketerId, $allowed, true) ? [$filter->marketerId] : [];
        }

        return $user->org_level === OrgLevel::Head ? null : $allowed;
    }

    private function isElevated(User $user): bool
    {
        return $user->role === UserRole::Admin
            || $user->is_team_leader
            || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true);
    }

    private function dateColumn(ReportFilterData $filter): string
    {
        return match ($filter->dateType) {
            DateType::SaleReceived => 'assigned_at',
            DateType::Closing => 'closed_at',
            default => 'data_arrived_at',
        };
    }

    /** Chỉ các order đại diện cho lead/contact thật; loại đơn supplemental. */
    private function contactOrders(Collection $orders): Collection
    {
        return $orders->whereIn('id', LeadContactMetrics::contactOrderIds($orders));
    }

    private function closed(Collection $orders): Collection
    {
        return $orders->filter(fn (Order $o) => (string) $o->closing_status === 'closed');
    }

    /** @param  list<string>  $statuses */
    private function bucket(Collection $orders, array $statuses): Collection
    {
        return $orders->filter(fn (Order $o) => in_array((string) $o->delivery_status, $statuses, true));
    }

    private static function pct(float|int $numerator, float|int $denominator): ?float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
    }

    /**
     * Cộng tổng các cột number/currency — cột percent tính lại ở từng báo cáo.
     *
     * @param  list<array{key: string, label: string, format: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function sumTotals(array $columns, array $rows): array
    {
        $totals = ['name' => __('reports.grand_total')];

        foreach ($columns as $column) {
            if (! in_array($column['format'], ['number', 'currency'], true)) {
                continue;
            }

            $totals[$column['key']] = array_sum(array_map(
                fn (array $row) => (int) ($row[$column['key']] ?? 0),
                $rows,
            ));
        }

        return $totals;
    }

    /** @param  array<string, mixed>  $extra */
    private function col(string $labelKey, string $key, string $format, array $extra = []): array
    {
        return array_merge([
            'key' => $key,
            'label_key' => $labelKey,
            'label' => __("reports.columns.{$labelKey}"),
            'format' => $format,
        ], $extra);
    }
}
