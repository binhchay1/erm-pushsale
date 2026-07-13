<?php

namespace App\Data;

use App\Enums\DateType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class MarketingDashboardFilterData
{
    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,
        public DateType $dateType = DateType::DataArrival,
        public ?int $teamLeaderId = null,
        public ?int $teamId = null,
        public ?int $marketerId = null,
        public ?string $operationScope = null,
        public ?string $customerType = null,
        public ?string $contactMode = null,
        public ?string $sourceType = null,
        public ?string $adChannel = null,
        public ?int $parentProductId = null,
        public ?int $productId = null,
        public ?string $utmKeyword = null,
        public ?string $sourceKeyword = null,
        public ?string $sortBy = null,
        public ?string $revenueMode = null,
        public bool $advancedUtm = false,
        public int $page = 1,
        public int $perPage = 10,
    ) {}

    public static function fromRequest(Request $request, ?User $user = null): self
    {
        $today = now();
        $from = self::parseDate($request->input('date_from'), $today->copy()->startOfDay(), false);
        $to = self::parseDate($request->input('date_to'), $today->copy()->endOfDay(), true);

        $marketerId = $request->integer('marketer_id') ?: null;
        if ($user?->role?->value === User::ROLE_MARKETING) {
            $marketerId = $user->id;
        }

        return new self(
            dateFrom: $from,
            dateTo: $to,
            dateType: DateType::tryFrom((string) $request->input('date_type')) ?? DateType::DataArrival,
            teamLeaderId: $request->integer('team_leader_id') ?: null,
            teamId: $request->integer('team_id') ?: null,
            marketerId: $marketerId,
            operationScope: self::nullableString($request->input('operation_scope')),
            customerType: self::nullableString($request->input('customer_type')),
            contactMode: self::nullableString($request->input('contact_mode')),
            sourceType: self::nullableString($request->input('source_type')),
            adChannel: self::nullableString($request->input('ad_channel')),
            parentProductId: $request->integer('parent_product_id') ?: null,
            productId: $request->integer('product_id') ?: null,
            utmKeyword: self::nullableString($request->input('utm_keyword')),
            sourceKeyword: self::nullableString($request->input('source_keyword')),
            sortBy: self::nullableString($request->input('sort_by')),
            revenueMode: self::nullableString($request->input('revenue_mode')) ?: 'total',
            advancedUtm: $request->boolean('advanced_utm'),
            page: max(1, $request->integer('page', 1)),
            perPage: min(100, max(10, $request->integer('per_page', 10))),
        );
    }

    /** @return array<string, mixed> */
    public function toInertia(): array
    {
        return [
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'date_type' => $this->dateType->value,
            'team_leader_id' => $this->teamLeaderId,
            'team_id' => $this->teamId,
            'marketer_id' => $this->marketerId,
            'operation_scope' => $this->operationScope,
            'customer_type' => $this->customerType,
            'contact_mode' => $this->contactMode,
            'source_type' => $this->sourceType,
            'ad_channel' => $this->adChannel,
            'parent_product_id' => $this->parentProductId,
            'product_id' => $this->productId,
            'utm_keyword' => $this->utmKeyword,
            'source_keyword' => $this->sourceKeyword,
            'sort_by' => $this->sortBy,
            'revenue_mode' => $this->revenueMode,
            'advanced_utm' => $this->advancedUtm,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    private static function parseDate(mixed $value, Carbon $fallback, bool $end): Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        try {
            $date = Carbon::parse($value);

            return $end ? $date->endOfDay() : $date->startOfDay();
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === '-1' ? null : $value;
    }
}
