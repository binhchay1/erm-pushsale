<?php

namespace App\Data;

use App\Enums\DiscountMode;
use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class MarketingRankingFilterData
{
    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,
        public DiscountMode $discountMode,
        public ?string $operationScope = null,
        public ?int $teamLeaderId = null,
        public ?int $teamId = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $today = now();

        return new self(
            dateFrom: self::parseDate($request->input('date_from'), $today->copy()->startOfDay(), false),
            dateTo: self::parseDate($request->input('date_to'), $today->copy()->endOfDay(), true),
            discountMode: DiscountMode::tryFrom((string) $request->input('discount_mode')) ?? DiscountMode::AfterDiscount,
            operationScope: self::nullableString($request->input('operation_scope')),
            teamLeaderId: $request->integer('team_leader_id') ?: null,
            teamId: $request->integer('team_id') ?: null,
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
            'discount_mode' => $this->discountMode->value,
            'operation_scope' => $this->operationScope,
            'team_leader_id' => $this->teamLeaderId,
            'team_id' => $this->teamId,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }

    private static function parseDate(mixed $value, Carbon $fallback, bool $end): Carbon
    {
        try {
            $date = is_string($value) && trim($value) !== '' ? Carbon::parse($value) : $fallback;

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
