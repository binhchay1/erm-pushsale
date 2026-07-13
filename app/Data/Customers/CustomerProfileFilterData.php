<?php

namespace App\Data\Customers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class CustomerProfileFilterData
{
    public function __construct(
        public ?Carbon $dateFrom,
        public ?Carbon $dateTo,
        public string $dateType,
        public ?int $sourceId,
        public ?int $saleLeaderId,
        public ?int $saleTeamId,
        public ?int $saleId,
        public ?int $marketingLeaderId,
        public ?int $marketingTeamId,
        public ?int $marketerId,
        public ?int $productId,
        public ?int $warehouseId,
        public ?string $careStatus,
        public ?string $closingStatus,
        public ?string $operationStage,
        public ?string $operationResult,
        public ?string $deliveryStatus,
        public ?string $reconciliationStatus,
        public ?string $duplicateStatus,
        public ?string $customerType,
        public ?string $allocationStatus,
        public ?string $shippingMethod,
        public ?string $search,
        public int $page,
        public int $perPage,
    ) {}

    public static function fromRequest(Request $request, ?User $user = null): self
    {
        $from = self::parseDate($request->input('date_from'), false);
        $to = self::parseDate($request->input('date_to'), true);

        if (! $from && ! $to) {
            $from = now()->subDays(6)->startOfDay();
            $to = now()->endOfDay();
        } elseif ($from && ! $to) {
            $to = $from->copy()->endOfDay();
        } elseif (! $from && $to) {
            $from = $to->copy()->startOfDay();
        }

        $saleId = $request->integer('sale_id') ?: null;
        if ($user?->isSales()) {
            $saleId = $user->id;
        }

        $marketerId = $request->integer('marketer_id') ?: null;
        if ($user?->role?->value === 'marketing') {
            $marketerId = $user->id;
        }

        return new self(
            dateFrom: $from,
            dateTo: $to,
            dateType: (string) $request->input('date_type', 'data_arrival'),
            sourceId: $request->integer('source_id') ?: ($request->integer('marketing_source_id') ?: null),
            saleLeaderId: $request->integer('sale_leader_id') ?: null,
            saleTeamId: $request->integer('sale_team_id') ?: ($request->integer('team_id') ?: null),
            saleId: $saleId,
            marketingLeaderId: $request->integer('marketing_leader_id') ?: null,
            marketingTeamId: $request->integer('marketing_team_id') ?: null,
            marketerId: $marketerId,
            productId: $request->integer('product_id') ?: null,
            warehouseId: $request->integer('warehouse_id') ?: null,
            careStatus: self::nullableString($request->input('care_status')),
            closingStatus: self::nullableString($request->input('closing_status')),
            operationStage: self::nullableString($request->input('operation_stage')),
            operationResult: self::nullableString($request->input('operation_result')),
            deliveryStatus: self::nullableString($request->input('delivery_status')),
            reconciliationStatus: self::nullableString($request->input('reconciliation_status')),
            duplicateStatus: self::nullableString($request->input('duplicate_status')),
            customerType: self::nullableString($request->input('customer_type')),
            allocationStatus: self::nullableString($request->input('allocation_status')),
            shippingMethod: self::nullableString($request->input('shipping_method')),
            search: self::nullableString($request->input('search')),
            page: max(1, $request->integer('page', 1)),
            perPage: min(100, max(10, $request->integer('per_page', 20))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'date_from' => $this->dateFrom?->toDateString(),
            'date_to' => $this->dateTo?->toDateString(),
            'date_type' => $this->dateType,
            'source_id' => $this->sourceId,
            'sale_leader_id' => $this->saleLeaderId,
            'sale_team_id' => $this->saleTeamId,
            'sale_id' => $this->saleId,
            'marketing_leader_id' => $this->marketingLeaderId,
            'marketing_team_id' => $this->marketingTeamId,
            'marketer_id' => $this->marketerId,
            'product_id' => $this->productId,
            'warehouse_id' => $this->warehouseId,
            'care_status' => $this->careStatus,
            'closing_status' => $this->closingStatus,
            'operation_stage' => $this->operationStage,
            'operation_result' => $this->operationResult,
            'delivery_status' => $this->deliveryStatus,
            'reconciliation_status' => $this->reconciliationStatus,
            'duplicate_status' => $this->duplicateStatus,
            'customer_type' => $this->customerType,
            'allocation_status' => $this->allocationStatus,
            'shipping_method' => $this->shippingMethod,
            'search' => $this->search,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    private static function parseDate(mixed $value, bool $endOfDay): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $endOfDay ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' || $string === 'all' ? null : $string;
    }
}
