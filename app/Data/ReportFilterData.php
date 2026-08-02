<?php

namespace App\Data;

use App\Enums\DateType;
use App\Enums\DiscountMode;
use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Reports\ReportDateRange;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

readonly class ReportFilterData
{
    public function __construct(
        public ?string $sourceType = null,
        public ?int $marketingSourceId = null,
        public string $preset = ReportDateRange::PRESET_LAST_7_DAYS,
        public ?Carbon $dateFrom = null,
        public ?Carbon $dateTo = null,
        public DateType $dateType = DateType::DataArrival,
        public ?string $customerType = null,
        public ?string $deliveryStatus = null,
        public ?string $reconciliationStatus = null,
        public DiscountMode $discountMode = DiscountMode::AfterDiscount,
        public ?int $parentProductId = null,
        public ?int $productId = null,
        public ?int $teamLeaderId = null,
        public ?int $teamId = null,
        public ?int $saleId = null,
        public ?int $marketingTeamLeaderId = null,
        public ?int $marketingTeamId = null,
        public ?int $marketerId = null,
        public ?int $warehouseId = null,
        public ?string $shippingMethod = null,
        public ?string $shippingProvider = null,
        public ?string $warehouseCareStatus = null,
        public ?string $printedStatus = null,
        public ?string $depositStatus = null,
        public ?int $minProductQuantity = null,
        public ?int $maxProductQuantity = null,
        public ?string $trackingAlert = null,
        public ?string $careStatus = null,
        public ?string $operationActivityStatus = null,
        public ?string $operationStage = null,
        public ?string $operationResult = null,
        public ?string $closingStatus = null,
        public ?string $search = null,
        public ?int $orderId = null,
        public int $page = 1,
        public int $perPage = 20,
        public bool $noClosingDateLimit = false,
        public bool $hideZeroStatus = false,
        public bool $hideNoPhone = false,
    ) {}

    public static function fromRequest(Request $request, ?User $user = null): self
    {
        $dateRange = ReportDateRange::fromRequest($request);

        $saleId = self::optionalPositiveInt($request, 'sale_id');
        if ($user?->isSales()) {
            $allowed = app(\App\Services\Reports\ReportScopeResolver::class)->allowedSaleIds($user);
            if ($allowed !== null) {
                if (count($allowed) === 1 && $allowed[0] === (int) $user->id) {
                    $saleId = $user->id;
                } elseif ($saleId !== null && ! in_array($saleId, $allowed, true)) {
                    $saleId = null;
                }
            }
        }

        $marketerId = self::optionalPositiveInt($request, 'marketer_id');
        if ($user?->role === UserRole::Marketing && ! self::isElevatedOperator($user)) {
            $marketerId = $user->id;
        }

        return new self(
            sourceType: $request->input('source_type'),
            marketingSourceId: self::optionalPositiveInt($request, 'marketing_source_id'),
            preset: $dateRange->preset,
            dateFrom: $dateRange->from,
            dateTo: $dateRange->to,
            dateType: self::normalizeDateType($request->input('date_type', '')),
            customerType: self::normalizeCustomerType($request->input('customer_type')),
            deliveryStatus: $request->input('delivery_status'),
            reconciliationStatus: $request->input('reconciliation_status'),
            discountMode: DiscountMode::tryFrom($request->input('discount_mode', '')) ?? DiscountMode::AfterDiscount,
            parentProductId: self::optionalPositiveInt($request, 'parent_product_id'),
            productId: self::optionalPositiveInt($request, 'product_id'),
            teamLeaderId: self::optionalPositiveInt($request, 'team_leader_id'),
            teamId: self::optionalPositiveInt($request, 'team_id'),
            saleId: $saleId,
            marketingTeamLeaderId: self::optionalPositiveInt($request, 'marketing_team_leader_id'),
            marketingTeamId: self::optionalPositiveInt($request, 'marketing_team_id'),
            marketerId: $marketerId,
            warehouseId: self::optionalPositiveInt($request, 'warehouse_id'),
            shippingMethod: $request->input('shipping_method'),
            shippingProvider: $request->input('shipping_provider'),
            warehouseCareStatus: $request->input('warehouse_care_status'),
            printedStatus: $request->input('printed_status'),
            depositStatus: $request->input('deposit_status'),
            minProductQuantity: $request->integer('min_product_quantity') ?: null,
            maxProductQuantity: $request->integer('max_product_quantity') ?: null,
            trackingAlert: $request->input('tracking_alert'),
            careStatus: $request->input('care_status'),
            operationActivityStatus: $request->input('operation_activity_status'),
            operationStage: $request->input('operation_stage'),
            operationResult: $request->input('operation_result'),
            closingStatus: $request->input('closing_status'),
            search: $request->input('search'),
            orderId: self::optionalPositiveInt($request, 'order_id'),
            page: max(1, $request->integer('page', 1)),
            perPage: min(999999, max(10, $request->integer('per_page', 20))),
            noClosingDateLimit: $request->boolean('no_closing_date_limit'),
            hideZeroStatus: $request->boolean('hide_zero_status'),
            hideNoPhone: $request->boolean('hide_no_phone'),
        );
    }


    private static function optionalPositiveInt(Request $request, string $key): ?int
    {
        $value = $request->integer($key);

        return $value > 0 ? $value : null;
    }

    private static function normalizeDateType(mixed $value): DateType
    {
        return match ((string) $value) {
            '1' => DateType::CareUpdate,
            '2', '-1', '' => DateType::DataArrival,
            '3' => DateType::SaleReceived,
            '4' => DateType::Closing,
            '5' => DateType::Posting,
            '6' => DateType::DeliveryUpdate,
            default => DateType::tryFrom((string) $value) ?? DateType::DataArrival,
        };
    }

    private static function normalizeCustomerType(mixed $value): ?string
    {
        return match ((string) $value) {
            'new', '0' => 'new',
            'old', '1' => 'old',
            default => null,
        };
    }

    private static function isElevatedOperator(User $user): bool
    {
        return $user->is_team_leader
            || in_array($user->org_level, [OrgLevel::Head, OrgLevel::Supervisor], true);
    }

    /** Giữ nguyên mọi bộ lọc nhưng bỏ tab tác nghiệp để tính tổng số theo từng bước. */
    public function withoutOperationStage(): self
    {
        if ($this->operationStage === null) {
            return $this;
        }

        return new self(...array_merge(get_object_vars($this), ['operationStage' => null]));
    }

    /**
     * Bỏ deliveryStatus khỏi filter — dùng cho màn có tab gộp nhóm trạng thái,
     * nơi việc lọc theo tab được xử lý in-memory thay vì ở SQL.
     */
    public function withoutDeliveryStatus(): self
    {
        if ($this->deliveryStatus === null) {
            return $this;
        }

        return new self(...array_merge(get_object_vars($this), ['deliveryStatus' => null]));
    }

    /**
     * Giữ nguyên mọi scope (marketer, team, product…) nhưng ép khoảng ngày mới.
     * Dùng cho các KPI "hôm nay" trên dashboard: cùng phạm vi dữ liệu nhưng chỉ tính ngày hiện tại.
     */
    public function withDateRange(CarbonInterface $from, CarbonInterface $to): self
    {
        return new self(...array_merge(get_object_vars($this), [
            'preset' => ReportDateRange::PRESET_CUSTOM,
            'dateFrom' => Carbon::instance($from)->copy(),
            'dateTo' => Carbon::instance($to)->copy(),
        ]));
    }

    public function forToday(): self
    {
        return $this->withDateRange(Carbon::now()->startOfDay(), Carbon::now()->endOfDay());
    }

    public function withDateType(DateType $dateType): self
    {
        if ($this->dateType === $dateType) {
            return $this;
        }

        return new self(...array_merge(get_object_vars($this), ['dateType' => $dateType]));
    }

    /** @return array<string, mixed> */
    public function toInertia(): array
    {
        return [
            'source_type' => $this->sourceType,
            'marketing_source_id' => $this->marketingSourceId,
            'preset' => $this->preset,
            'date_from' => $this->dateFrom?->toDateString(),
            'date_to' => $this->dateTo?->toDateString(),
            'date_type' => $this->dateType->value,
            'customer_type' => $this->customerType,
            'delivery_status' => $this->deliveryStatus,
            'reconciliation_status' => $this->reconciliationStatus,
            'discount_mode' => $this->discountMode->value,
            'parent_product_id' => $this->parentProductId,
            'product_id' => $this->productId,
            'team_leader_id' => $this->teamLeaderId,
            'team_id' => $this->teamId,
            'sale_id' => $this->saleId,
            'marketing_team_leader_id' => $this->marketingTeamLeaderId,
            'marketing_team_id' => $this->marketingTeamId,
            'marketer_id' => $this->marketerId,
            'warehouse_id' => $this->warehouseId,
            'shipping_method' => $this->shippingMethod,
            'shipping_provider' => $this->shippingProvider,
            'warehouse_care_status' => $this->warehouseCareStatus,
            'printed_status' => $this->printedStatus,
            'deposit_status' => $this->depositStatus,
            'min_product_quantity' => $this->minProductQuantity,
            'max_product_quantity' => $this->maxProductQuantity,
            'tracking_alert' => $this->trackingAlert,
            'care_status' => $this->careStatus,
            'operation_activity_status' => $this->operationActivityStatus,
            'operation_stage' => $this->operationStage,
            'operation_result' => $this->operationResult,
            'closing_status' => $this->closingStatus,
            'search' => $this->search,
            'order_id' => $this->orderId,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'no_closing_date_limit' => $this->noClosingDateLimit,
            'hide_zero_status' => $this->hideZeroStatus,
            'hide_no_phone' => $this->hideNoPhone,
        ];
    }
}
