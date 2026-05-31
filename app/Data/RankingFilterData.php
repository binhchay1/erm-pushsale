<?php

namespace App\Data;

use App\Enums\DiscountMode;
use App\Enums\RankingPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;

readonly class RankingFilterData
{
    public function __construct(
        public Carbon $dateFrom,
        public Carbon $dateTo,
        public RankingPeriod $period,
        public DiscountMode $discountMode,
        public ?string $operationStage = null,
        public ?int $teamLeaderId = null,
        public ?int $teamId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $period = RankingPeriod::fromRequest($request->input('period'));
        [$periodStart, $periodEnd] = $period->range();

        $dateFrom = $request->input('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : $periodStart;

        $dateTo = $request->input('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : $periodEnd;

        return new self(
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            period: $period,
            discountMode: DiscountMode::tryFrom($request->input('discount_mode', '')) ?? DiscountMode::AfterDiscount,
            operationStage: $request->input('operation_stage') ?: null,
            teamLeaderId: $request->integer('team_leader_id') ?: null,
            teamId: $request->integer('team_id') ?: null,
        );
    }

    /** @return array<string, mixed> */
    public function toInertia(): array
    {
        return [
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'period' => $this->period->value,
            'discount_mode' => $this->discountMode->value,
            'operation_stage' => $this->operationStage,
            'team_leader_id' => $this->teamLeaderId,
            'team_id' => $this->teamId,
        ];
    }
}
