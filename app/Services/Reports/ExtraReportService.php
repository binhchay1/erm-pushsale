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
use App\Models\User;
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
    private const DELIVERED = ['delivered', 'delivery_complete'];

    private const PAID = ['paid'];

    private const DELIVERING = ['waiting_waybill', 'posted', 'picking_up', 'delivering', 'deliver_now', 'redelivery'];

    private const RETURNED = ['returned', 'returning', 'refund', 'cannot_deliver'];

    private const CANCELLED = ['cancel_waybill', 'cancel_closing'];

    /** Xác nhận giao hàng (2): đơn đã lên vận đơn / vào luồng giao (kể cả chờ lấy). */
    private const XNGH = ['waiting_waybill', 'posted', 'picking_up', 'delivering', 'deliver_now', 'redelivery', 'delivered', 'delivery_complete', 'paid', 'returned', 'returning', 'refund', 'cannot_deliver'];

    /** Chuyển ĐVGH (4): đơn đã bàn giao cho đơn vị vận chuyển (mẫu số của các % giao/hoàn). */
    private const TRANSFER = ['posted', 'picking_up', 'delivering', 'deliver_now', 'redelivery', 'delivered', 'delivery_complete', 'paid', 'returned', 'returning', 'refund', 'cannot_deliver'];

    /** Đã hoàn (5): hoàn xong. */
    private const RETURNED_DONE = ['returned', 'refund'];

    /** Đang hoàn (6): đang trên đường hoàn / không giao được. */
    private const RETURNING = ['returning', 'cannot_deliver'];

    /** Giao thành công (9): đã giao + đã thanh toán. */
    private const SUCCESS = ['delivered', 'delivery_complete', 'paid'];

    public function __construct(
        private readonly ReportScopeResolver $scope,
    ) {}

    /**
     * @return array<string, array{title: string, description: string, roles: list<string>, level: string, filters: list<string>}>
     */
    public static function registry(): array
    {
        $defs = [
            'sale-1' => ['roles' => ['sales', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'product_id']],
            'sale-2' => ['roles' => ['sales', 'accounting', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'sale-3' => ['roles' => ['sales', 'accounting', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'sale-4' => ['roles' => ['sales', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'product_id']],
            'sale-5' => ['roles' => ['sales', 'admin'], 'level' => 'staff', 'filters' => []],
            'marketing-1' => ['roles' => ['marketing', 'accounting', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'marketing-2' => ['roles' => ['marketing', 'sales', 'accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type']],
            'marketing-3' => ['roles' => ['marketing', 'admin'], 'level' => 'staff', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'marketing-4' => ['roles' => ['marketing', 'accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id']],
            'kho-1' => ['roles' => ['accounting', 'warehouse', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'product_id', 'warehouse_id']],
            'kho-2' => ['roles' => ['accounting', 'admin'], 'level' => 'leader', 'filters' => ['date_from', 'date_to', 'date_type', 'warehouse_id']],
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

        return $report['level'] === 'staff' || $this->isElevated($user);
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
            'sale-5' => $this->saleAppointments($user),
            'marketing-1' => $this->revenueDetail($user, $filter, 'marketing'),
            'marketing-2' => $this->productClosing($user, $filter),
            'marketing-3' => $this->marketingWork($user, $filter),
            'marketing-4' => $this->upsaleReport($user, $filter),
            'kho-1' => $this->warehouseRevenue($user, $filter),
            'kho-2' => $this->systemBusiness($user, $filter),
        };

        $filterFields = $report['filters'];

        // Admin có thể lọc theo từng nhân viên; các role khác đã bị giới hạn phạm vi sẵn
        if ($user->role === UserRole::Admin && $filterFields !== []) {
            if (in_array($key, ['sale-1', 'sale-2', 'sale-3', 'sale-4'], true)) {
                $filterFields[] = 'sale_id';
            }

            if (in_array($key, ['marketing-1', 'marketing-3'], true)) {
                $filterFields[] = 'marketer_id';
            }
        } elseif ($user->role === UserRole::Marketing && $this->isElevated($user)) {
            if (in_array($key, ['marketing-1', 'marketing-3'], true)) {
                $filterFields[] = 'marketer_id';
            }
        }

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
            $row = [
                'name' => $group->first()->saleUser?->name ?? '—',
                'contacts' => $group->count(),
                'untouched' => $group->where('contact_count', 0)->count(),
            ];

            foreach ($stages as $stage) {
                $row['stage_'.$stage->value] = $group
                    ->filter(fn (Order $o) => (string) $o->operation_stage === $stage->value)
                    ->count();
            }

            return $row;
        })->sortByDesc('contacts')->values()->all();

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $this->sumTotals($columns, $rows)];
    }

    private function saleClosing(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrders($user, $filter, scopeSales: true)
            ->filter(fn (Order $o) => $o->sale_user_id !== null);

        $columns = [
            $this->col('telesale', 'name', 'text'),
            $this->col('contacts', 'contacts', 'number'),
            $this->col('closed', 'closed', 'number'),
            $this->col('rate', 'rate', 'percent', ['tone' => 'positive']),
            $this->col('gross', 'gross', 'currency'),
            $this->col('discount', 'discount', 'currency'),
            $this->col('net', 'net', 'currency'),
        ];

        $rows = $orders->groupBy('sale_user_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $net = (int) $closed->sum(fn (Order $o) => $o->netRevenue());
            $discount = (int) $closed->sum('discount');

            return [
                'name' => $group->first()->saleUser?->name ?? '—',
                'contacts' => $group->count(),
                'closed' => $closed->count(),
                'rate' => self::pct($closed->count(), $group->count()),
                'gross' => $net + $discount,
                'discount' => $discount,
                'net' => $net,
            ];
        })->sortByDesc('net')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['rate'] = self::pct($totals['closed'] ?? 0, $totals['contacts'] ?? 0);

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
        ];

        $groupKey = $bySale ? 'sale_user_id' : 'marketer_user_id';

        $rows = $orders->groupBy($groupKey)->map(function (Collection $group) use ($bySale) {
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
                'contacts' => $group->count(),
                'close_rate' => self::pct($closed->count(), $group->count()),
                'product_count' => (int) $closed->sum(fn (Order $o) => $o->items->sum('quantity')),
                'avg_order' => $closed->count() > 0 ? (int) round($closedRev / $closed->count()) : null,
                'pct_rev_returned' => self::pct($returnedRev, $closedRev),
                'pct_rev_cancel' => self::pct($cancelRev, $closedRev),
            ];
        })->sortByDesc('closed_rev')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['pct_returned'] = self::pct($totals['returned_qty'] ?? 0, $totals['transfer_qty'] ?? 0);
        $totals['pct_cancel'] = self::pct($totals['cancel_qty'] ?? 0, $totals['closed_qty'] ?? 0);
        $totals['pct_xngh'] = self::pct($totals['xngh_qty'] ?? 0, $totals['closed_qty'] ?? 0);
        $totals['pct_success'] = self::pct($totals['success_qty'] ?? 0, $totals['transfer_qty'] ?? 0);
        $totals['close_rate'] = self::pct($totals['closed_qty'] ?? 0, $totals['contacts'] ?? 0);
        $totals['avg_order'] = ($totals['closed_qty'] ?? 0) > 0
            ? (int) round(($totals['closed_rev'] ?? 0) / $totals['closed_qty'])
            : null;
        $totals['pct_rev_returned'] = self::pct($totals['returned_rev'] ?? 0, $totals['closed_rev'] ?? 0);
        $totals['pct_rev_cancel'] = self::pct($totals['cancel_rev'] ?? 0, $totals['closed_rev'] ?? 0);

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    private function saleKpi(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrders($user, $filter, scopeSales: true)
            ->filter(fn (Order $o) => $o->sale_user_id !== null);

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
            $this->col('actual_rev', 'actual_rev', 'currency'),
        ];

        $rows = $orders->groupBy('sale_user_id')->map(function (Collection $group) {
            $new = $group->where('is_returning_customer', false);
            $old = $group->where('is_returning_customer', true);
            $newClosed = $this->closed($new);
            $oldClosed = $this->closed($old);
            $closed = $this->closed($group);
            $actual = $this->bucket($closed, array_merge(self::DELIVERED, self::PAID));

            return [
                'name' => $group->first()->saleUser?->name ?? '—',
                'new_contacts' => $new->count(),
                'new_closed' => $newClosed->count(),
                'new_rate' => self::pct($newClosed->count(), $new->count()),
                'old_contacts' => $old->count(),
                'old_closed' => $oldClosed->count(),
                'old_rate' => self::pct($oldClosed->count(), $old->count()),
                'total_closed' => $closed->count(),
                'expected_rev' => (int) $closed->sum(fn (Order $o) => $o->netRevenue()),
                'actual_rev' => (int) $actual->sum(fn (Order $o) => $o->netRevenue()),
            ];
        })->sortByDesc('expected_rev')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['new_rate'] = self::pct($totals['new_closed'] ?? 0, $totals['new_contacts'] ?? 0);
        $totals['old_rate'] = self::pct($totals['old_closed'] ?? 0, $totals['old_contacts'] ?? 0);

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    private function saleAppointments(User $user): array
    {
        $saleIds = $this->visibleSaleIds($user, null);

        $orders = Order::query()
            ->with('saleUser:id,name')
            ->whereNotNull('next_operation_at')
            ->whereBetween('next_operation_at', [now()->startOfDay(), now()->addDays(6)->endOfDay()])
            ->when($saleIds !== null, fn ($q) => $q->whereIn('sale_user_id', $saleIds))
            ->get();

        $columns = [
            $this->col('date', 'date', 'text'),
            $this->col('weekday', 'weekday', 'text'),
            $this->col('appointment_count', 'count', 'number'),
            $this->col('sales_assigned', 'sales', 'text'),
        ];

        $rows = [];

        for ($i = 0; $i <= 6; $i++) {
            $day = now()->addDays($i);
            $dayOrders = $orders->filter(fn (Order $o) => $o->next_operation_at?->isSameDay($day));

            $rows[] = [
                'date' => $day->format('d/m/Y'),
                'weekday' => $i === 0 ? __('reports.today') : __('reports.weekdays.'.$day->dayOfWeek),
                'count' => $dayOrders->count(),
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
            $revenue = (int) $closed->sum(fn (Order $o) => $o->netRevenue());

            return [
                'name' => $group->first()->product?->name ?? '—',
                'sku' => $group->first()->product?->sku,
                'contacts' => $group->count(),
                'closed' => $closed->count(),
                'rate' => self::pct($closed->count(), $group->count()),
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

        $leadsQuery = LeadIngestion::query()
            ->with(['marketingSource:id,marketer_user_id,name', 'marketingSource.marketer:id,name'])
            ->whereNotNull('marketing_source_id')
            ->when(
                $filter->dateFrom && $filter->dateTo,
                fn ($q) => $q->whereBetween('created_at', [$filter->dateFrom, $filter->dateTo]),
            )
            ->when($marketerIds !== null, function ($q) use ($marketerIds) {
                $q->whereHas('marketingSource', fn ($sq) => $sq->whereIn('marketer_user_id', $marketerIds));
            })
            ->when($filter->marketerId, function ($q) use ($filter) {
                $q->whereHas('marketingSource', fn ($sq) => $sq->where('marketer_user_id', $filter->marketerId));
            });

        $leads = $leadsQuery->get();

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

        $rows = $marketerIdsInScope->map(function (int $marketerId) use ($orders, $leads) {
            $group = $orders->where('marketer_user_id', $marketerId);
            $marketerLeads = $leads->filter(fn (LeadIngestion $l) => (int) $l->marketingSource?->marketer_user_id === $marketerId);
            $closed = $this->closed($group);
            $revenue = (int) $closed->sum(fn (Order $o) => $o->netRevenue());
            $contactCount = max($marketerLeads->count(), $group->count());
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
                'closed' => $closed->count(),
                'rate' => self::pct($closed->count(), $contactCount),
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

        $rows = $orders->groupBy('marketing_source_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $revenue = (int) $closed->sum(fn (Order $o) => $o->netRevenue());
            $closedItems = $closed->flatMap(fn (Order $o) => $o->items);
            $qtySold = (int) $closedItems->sum('quantity');
            $upsellItems = $closedItems->filter(fn (OrderItem $i) => $i->item_type === 'upsell');
            $source = $group->first()->marketingSource;

            return [
                'name' => $source?->name ?? '—',
                'channel' => $source?->ad_channel ?? '—',
                'contacts' => $group->count(),
                'closed' => $closed->count(),
                'rate' => self::pct($closed->count(), $group->count()),
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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function fetchOrders(
        User $user,
        ReportFilterData $filter,
        bool $scopeSales = false,
        bool $scopeMarketing = false,
    ): Collection {
        $column = $this->dateColumn($filter);
        $saleIds = $scopeSales ? $this->visibleSaleIds($user, $filter) : null;
        $marketerIds = $scopeMarketing ? $this->visibleMarketerIds($user, $filter) : null;

        return Order::query()
            ->with(['saleUser:id,name', 'marketerUser:id,name', 'warehouse:id,name', 'product:id,name,sku'])
            ->when(
                $filter->dateFrom && $filter->dateTo,
                fn ($q) => $q->whereBetween($column, [$filter->dateFrom, $filter->dateTo]),
            )
            ->when($saleIds !== null, fn ($q) => $q->whereIn('sale_user_id', $saleIds))
            ->when($marketerIds !== null, fn ($q) => $q->whereIn('marketer_user_id', $marketerIds))
            ->when($filter->productId, fn ($q) => $q->where('product_id', $filter->productId))
            ->when($filter->warehouseId, fn ($q) => $q->where('warehouse_id', $filter->warehouseId))
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
        $column = $this->dateColumn($filter);
        $saleIds = $scopeSales ? $this->visibleSaleIds($user, $filter) : null;
        $marketerIds = $scopeMarketing ? $this->visibleMarketerIds($user, $filter) : null;

        return Order::query()
            ->with([
                'saleUser:id,name',
                'marketerUser:id,name',
                'marketingSource:id,name,ad_channel',
                'items:id,order_id,product_id,product_name,item_type,quantity,unit_price,discount_amount',
            ])
            ->when(
                $filter->dateFrom && $filter->dateTo,
                fn ($q) => $q->whereBetween($column, [$filter->dateFrom, $filter->dateTo]),
            )
            ->when($saleIds !== null, fn ($q) => $q->whereIn('sale_user_id', $saleIds))
            ->when($marketerIds !== null, fn ($q) => $q->whereIn('marketer_user_id', $marketerIds))
            ->when($filter->productId, fn ($q) => $q->where('product_id', $filter->productId))
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

        if ($user->org_level === OrgLevel::Head) {
            return null;
        }

        return $this->scope->allowedSaleIds($user);
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

        if ($user->org_level === OrgLevel::Head) {
            return null;
        }

        return $this->scope->allowedMarketerIds($user);
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
