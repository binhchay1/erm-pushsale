<?php

namespace App\Services\Reports;

use App\Data\ReportFilterData;
use App\Enums\DateType;
use App\Enums\OperationStage;
use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\Order;
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

    public function __construct(
        private readonly ReportScopeResolver $scope,
    ) {}

    /**
     * @return array<string, array{title: string, description: string, roles: list<string>, level: string, filters: list<string>}>
     */
    public static function registry(): array
    {
        return [
            'sale-1' => [
                'title' => 'Báo cáo công việc sale',
                'description' => 'Số khách được gán và tiến độ gọi theo từng bước tác nghiệp của mỗi telesale.',
                'roles' => ['sales', 'admin'],
                'level' => 'staff',
                'filters' => ['date_from', 'date_to', 'product_id'],
            ],
            'sale-2' => [
                'title' => 'Bảng tổng hợp chốt đơn',
                'description' => 'Số contact, đơn chốt, tỷ lệ chốt và doanh số trước / sau chiết khấu theo telesale.',
                'roles' => ['sales', 'accounting', 'admin'],
                'level' => 'staff',
                'filters' => ['date_from', 'date_to', 'date_type', 'product_id'],
            ],
            'sale-3' => [
                'title' => 'Báo cáo doanh số chi tiết sale',
                'description' => 'Đơn chốt theo từng trạng thái giao hàng: đang giao, đã giao, đã thanh toán, hoàn, hủy.',
                'roles' => ['sales', 'accounting', 'admin'],
                'level' => 'staff',
                'filters' => ['date_from', 'date_to', 'date_type', 'product_id'],
            ],
            'sale-4' => [
                'title' => 'Sale KPI',
                'description' => 'Contact khách mới / khách cũ, tỷ lệ chốt, doanh số dự kiến và doanh số thực nhận.',
                'roles' => ['sales', 'admin'],
                'level' => 'staff',
                'filters' => ['date_from', 'date_to', 'product_id'],
            ],
            'sale-5' => [
                'title' => 'Báo cáo lịch hẹn telesales',
                'description' => 'Số khách đã hẹn gọi lại trong 7 ngày tới — sắp xếp công việc theo ngày.',
                'roles' => ['sales', 'admin'],
                'level' => 'staff',
                'filters' => [],
            ],
            'marketing-1' => [
                'title' => 'Báo cáo doanh số marketing',
                'description' => 'Đơn và doanh số theo từng nhân viên marketing, chia theo trạng thái giao hàng.',
                'roles' => ['marketing', 'accounting', 'admin'],
                'level' => 'staff',
                'filters' => ['date_from', 'date_to', 'date_type', 'product_id'],
            ],
            'marketing-2' => [
                'title' => 'Tỉ lệ chốt đơn sản phẩm',
                'description' => 'Contact, đơn chốt, tỷ lệ chốt và giá trị trung bình của từng sản phẩm.',
                'roles' => ['marketing', 'sales', 'accounting', 'admin'],
                'level' => 'leader',
                'filters' => ['date_from', 'date_to', 'date_type'],
            ],
            'kho-1' => [
                'title' => 'Báo cáo doanh số theo kho',
                'description' => 'Doanh số chốt, xác nhận giao, đang giao và hàng hoàn của từng kho.',
                'roles' => ['accounting', 'warehouse', 'admin'],
                'level' => 'leader',
                'filters' => ['date_from', 'date_to', 'date_type', 'product_id', 'warehouse_id'],
            ],
            'kho-2' => [
                'title' => 'Báo cáo kinh doanh hệ thống',
                'description' => 'Doanh số theo kho, tách khách mới và khách mua lại để đánh giá chất lượng tệp khách.',
                'roles' => ['accounting', 'admin'],
                'level' => 'leader',
                'filters' => ['date_from', 'date_to', 'date_type', 'warehouse_id'],
            ],
        ];
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
            'kho-1' => $this->warehouseRevenue($user, $filter),
            'kho-2' => $this->systemBusiness($user, $filter),
        };

        $filterFields = $report['filters'];

        // Admin có thể lọc theo từng nhân viên; các role khác đã bị giới hạn phạm vi sẵn
        if ($user->role === UserRole::Admin && $filterFields !== []) {
            if (in_array($key, ['sale-1', 'sale-2', 'sale-3', 'sale-4'], true)) {
                $filterFields[] = 'sale_id';
            }

            if ($key === 'marketing-1') {
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
            ['key' => 'name', 'label' => 'Telesale', 'format' => 'text'],
            ['key' => 'contacts', 'label' => 'Tổng contact', 'format' => 'number'],
            ['key' => 'untouched', 'label' => 'Chưa tác nghiệp', 'format' => 'number'],
        ], array_map(fn (OperationStage $stage) => [
            'key' => 'stage_'.$stage->value,
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
            ['key' => 'name', 'label' => 'Telesale', 'format' => 'text'],
            ['key' => 'contacts', 'label' => 'Số contact', 'format' => 'number'],
            ['key' => 'closed', 'label' => 'Chốt đơn', 'format' => 'number'],
            ['key' => 'rate', 'label' => 'Tỷ lệ chốt', 'format' => 'percent', 'tone' => 'positive'],
            ['key' => 'gross', 'label' => 'Doanh số', 'format' => 'currency'],
            ['key' => 'discount', 'label' => 'Chiết khấu', 'format' => 'currency'],
            ['key' => 'net', 'label' => 'Doanh số sau CK', 'format' => 'currency'],
        ];

        $rows = $orders->groupBy('sale_user_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $net = (int) $closed->sum('total');
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

    /** Doanh số chi tiết theo telesale hoặc marketer — cùng cấu trúc cột. */
    private function revenueDetail(User $user, ReportFilterData $filter, string $groupRole): array
    {
        $bySale = $groupRole === 'sale';
        $orders = $this->fetchOrders($user, $filter, scopeSales: $bySale, scopeMarketing: ! $bySale)
            ->filter(fn (Order $o) => $bySale ? $o->sale_user_id !== null : $o->marketer_user_id !== null);

        $columns = [
            ['key' => 'name', 'label' => $bySale ? 'Telesale' : 'Marketer', 'format' => 'text'],
            ['key' => 'contacts', 'label' => 'Contact', 'format' => 'number'],
            ['key' => 'closed_qty', 'label' => 'Đơn chốt', 'format' => 'number'],
            ['key' => 'closed_rev', 'label' => 'DS chốt', 'format' => 'currency'],
            ['key' => 'delivering_qty', 'label' => 'Đang giao (SL)', 'format' => 'number'],
            ['key' => 'delivering_rev', 'label' => 'Đang giao (DS)', 'format' => 'currency'],
            ['key' => 'delivered_qty', 'label' => 'Đã giao (SL)', 'format' => 'number'],
            ['key' => 'delivered_rev', 'label' => 'Đã giao (DS)', 'format' => 'currency'],
            ['key' => 'paid_qty', 'label' => 'Đã thanh toán (SL)', 'format' => 'number'],
            ['key' => 'paid_rev', 'label' => 'Đã thanh toán (DS)', 'format' => 'currency'],
            ['key' => 'returned_qty', 'label' => 'Hoàn (SL)', 'format' => 'number'],
            ['key' => 'returned_rev', 'label' => 'Hoàn (DS)', 'format' => 'currency'],
            ['key' => 'cancelled_qty', 'label' => 'Hủy (SL)', 'format' => 'number'],
            ['key' => 'return_rate', 'label' => '% Hoàn', 'format' => 'percent', 'tone' => 'negative'],
            ['key' => 'close_rate', 'label' => 'Tỷ lệ chốt', 'format' => 'percent', 'tone' => 'positive'],
            ['key' => 'avg_order', 'label' => 'Giá trị TB/đơn', 'format' => 'currency'],
        ];

        $groupKey = $bySale ? 'sale_user_id' : 'marketer_user_id';

        $rows = $orders->groupBy($groupKey)->map(function (Collection $group) use ($bySale) {
            $closed = $this->closed($group);
            $closedRev = (int) $closed->sum('total');
            $delivering = $this->bucket($closed, self::DELIVERING);
            $delivered = $this->bucket($closed, self::DELIVERED);
            $paid = $this->bucket($closed, self::PAID);
            $returned = $this->bucket($closed, self::RETURNED);
            $cancelled = $this->bucket($closed, self::CANCELLED);

            return [
                'name' => ($bySale ? $group->first()->saleUser?->name : $group->first()->marketerUser?->name) ?? '—',
                'contacts' => $group->count(),
                'closed_qty' => $closed->count(),
                'closed_rev' => $closedRev,
                'delivering_qty' => $delivering->count(),
                'delivering_rev' => (int) $delivering->sum('total'),
                'delivered_qty' => $delivered->count(),
                'delivered_rev' => (int) $delivered->sum('total'),
                'paid_qty' => $paid->count(),
                'paid_rev' => (int) $paid->sum('total'),
                'returned_qty' => $returned->count(),
                'returned_rev' => (int) $returned->sum('total'),
                'cancelled_qty' => $cancelled->count(),
                'return_rate' => self::pct($returned->count(), $closed->count()),
                'close_rate' => self::pct($closed->count(), $group->count()),
                'avg_order' => $closed->count() > 0 ? (int) round($closedRev / $closed->count()) : null,
            ];
        })->sortByDesc('closed_rev')->values()->all();

        $totals = $this->sumTotals($columns, $rows);
        $totals['return_rate'] = self::pct($totals['returned_qty'] ?? 0, $totals['closed_qty'] ?? 0);
        $totals['close_rate'] = self::pct($totals['closed_qty'] ?? 0, $totals['contacts'] ?? 0);
        $totals['avg_order'] = ($totals['closed_qty'] ?? 0) > 0
            ? (int) round(($totals['closed_rev'] ?? 0) / $totals['closed_qty'])
            : null;

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    private function saleKpi(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrders($user, $filter, scopeSales: true)
            ->filter(fn (Order $o) => $o->sale_user_id !== null);

        $columns = [
            ['key' => 'name', 'label' => 'Telesale', 'format' => 'text'],
            ['key' => 'new_contacts', 'label' => 'Contact mới', 'format' => 'number'],
            ['key' => 'new_closed', 'label' => 'Chốt (mới)', 'format' => 'number'],
            ['key' => 'new_rate', 'label' => 'Tỷ lệ (mới)', 'format' => 'percent', 'tone' => 'positive'],
            ['key' => 'old_contacts', 'label' => 'Contact khách cũ', 'format' => 'number'],
            ['key' => 'old_closed', 'label' => 'Chốt (cũ)', 'format' => 'number'],
            ['key' => 'old_rate', 'label' => 'Tỷ lệ (cũ)', 'format' => 'percent', 'tone' => 'positive'],
            ['key' => 'total_closed', 'label' => 'Tổng đơn chốt', 'format' => 'number'],
            ['key' => 'expected_rev', 'label' => 'Doanh số dự kiến', 'format' => 'currency'],
            ['key' => 'actual_rev', 'label' => 'Doanh số thực nhận', 'format' => 'currency'],
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
                'expected_rev' => (int) $closed->sum('total'),
                'actual_rev' => (int) $actual->sum('total'),
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
            ['key' => 'date', 'label' => 'Ngày', 'format' => 'text'],
            ['key' => 'weekday', 'label' => 'Thứ', 'format' => 'text'],
            ['key' => 'count', 'label' => 'Khách hẹn gọi lại', 'format' => 'number'],
            ['key' => 'sales', 'label' => 'Telesale phụ trách', 'format' => 'text'],
        ];

        $weekdays = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
        $rows = [];

        for ($i = 0; $i <= 6; $i++) {
            $day = now()->addDays($i);
            $dayOrders = $orders->filter(fn (Order $o) => $o->next_operation_at?->isSameDay($day));

            $rows[] = [
                'date' => $day->format('d/m/Y'),
                'weekday' => $i === 0 ? 'Hôm nay' : $weekdays[$day->dayOfWeek],
                'count' => $dayOrders->count(),
                'sales' => $dayOrders->pluck('saleUser.name')->filter()->unique()->implode(', ') ?: '—',
            ];
        }

        $totals = ['date' => 'Tổng', 'count' => array_sum(array_column($rows, 'count'))];

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
            ['key' => 'name', 'label' => 'Sản phẩm', 'format' => 'text'],
            ['key' => 'sku', 'label' => 'SKU', 'format' => 'text'],
            ['key' => 'contacts', 'label' => 'Số contact', 'format' => 'number'],
            ['key' => 'closed', 'label' => 'Chốt đơn', 'format' => 'number'],
            ['key' => 'rate', 'label' => 'Tỷ lệ chốt', 'format' => 'percent', 'tone' => 'positive'],
            ['key' => 'revenue', 'label' => 'Doanh số', 'format' => 'currency'],
            ['key' => 'avg', 'label' => 'Giá trị TB/đơn', 'format' => 'currency'],
        ];

        $rows = $orders->groupBy('product_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $revenue = (int) $closed->sum('total');

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

    private function warehouseRevenue(User $user, ReportFilterData $filter): array
    {
        $orders = $this->fetchOrders($user, $filter)
            ->filter(fn (Order $o) => $o->warehouse_id !== null);

        $columns = [
            ['key' => 'name', 'label' => 'Kho', 'format' => 'text'],
            ['key' => 'closed_qty', 'label' => 'Đơn chốt', 'format' => 'number'],
            ['key' => 'closed_rev', 'label' => 'Doanh số chốt', 'format' => 'currency'],
            ['key' => 'confirmed_qty', 'label' => 'Đã giao + TT (SL)', 'format' => 'number'],
            ['key' => 'confirmed_rev', 'label' => 'Doanh số xác nhận', 'format' => 'currency'],
            ['key' => 'delivering_qty', 'label' => 'Đang giao (SL)', 'format' => 'number'],
            ['key' => 'delivering_rev', 'label' => 'Đang giao (DS)', 'format' => 'currency'],
            ['key' => 'returned_qty', 'label' => 'Hoàn (SL)', 'format' => 'number'],
            ['key' => 'returned_rev', 'label' => 'Hoàn (DS)', 'format' => 'currency'],
            ['key' => 'discount', 'label' => 'Chiết khấu', 'format' => 'currency'],
        ];

        $rows = $orders->groupBy('warehouse_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $confirmed = $this->bucket($closed, array_merge(self::DELIVERED, self::PAID));
            $delivering = $this->bucket($closed, self::DELIVERING);
            $returned = $this->bucket($closed, self::RETURNED);

            return [
                'name' => $group->first()->warehouse?->name ?? '—',
                'closed_qty' => $closed->count(),
                'closed_rev' => (int) $closed->sum('total'),
                'confirmed_qty' => $confirmed->count(),
                'confirmed_rev' => (int) $confirmed->sum('total'),
                'delivering_qty' => $delivering->count(),
                'delivering_rev' => (int) $delivering->sum('total'),
                'returned_qty' => $returned->count(),
                'returned_rev' => (int) $returned->sum('total'),
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
            ['key' => 'name', 'label' => 'Kho', 'format' => 'text'],
            ['key' => 'closed_qty', 'label' => 'Đơn chốt', 'format' => 'number'],
            ['key' => 'revenue', 'label' => 'Tổng doanh số', 'format' => 'currency'],
            ['key' => 'avg', 'label' => 'DS trung bình/đơn', 'format' => 'currency'],
            ['key' => 'new_qty', 'label' => 'KH mới (SL)', 'format' => 'number'],
            ['key' => 'new_rev', 'label' => 'KH mới (DS)', 'format' => 'currency'],
            ['key' => 'new_share', 'label' => '% DS khách mới', 'format' => 'percent'],
            ['key' => 'old_qty', 'label' => 'KH cũ (SL)', 'format' => 'number'],
            ['key' => 'old_rev', 'label' => 'KH cũ (DS)', 'format' => 'currency'],
            ['key' => 'old_share', 'label' => '% DS khách cũ', 'format' => 'percent'],
        ];

        $rows = $orders->groupBy('warehouse_id')->map(function (Collection $group) {
            $closed = $this->closed($group);
            $revenue = (int) $closed->sum('total');
            $new = $closed->where('is_returning_customer', false);
            $old = $closed->where('is_returning_customer', true);
            $newRev = (int) $new->sum('total');
            $oldRev = (int) $old->sum('total');

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
        $totals = ['name' => 'Tổng cộng'];

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
}
