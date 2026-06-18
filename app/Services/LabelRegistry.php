<?php

namespace App\Services;

use App\Enums\ClosingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\LeadIngestionStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\OrgLevel;
use App\Enums\TeamType;
use App\Enums\UserRole;

/**
 * Nhãn enum đã dịch — chia sẻ cho Inertia (tránh hardcode JS).
 */
class LabelRegistry
{
    /** @return array<string, array<string, string>> */
    public function all(): array
    {
        return [
            'delivery_status' => $this->mapEnum(DeliveryStatus::cases()),
            'closing_status' => $this->mapEnum(ClosingStatus::cases()),
            'lead_ingestion_status' => $this->mapEnum(LeadIngestionStatus::cases()),
            'operation_stage' => $this->mapEnum(OperationStage::cases()),
            'operation_result' => $this->mapEnum(OperationResult::cases()),
            'user_role' => $this->mapEnum(UserRole::cases()),
            'org_level' => $this->mapEnum(OrgLevel::cases()),
            'team_type' => $this->mapEnum(TeamType::cases()),
        ];
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<string, string>
     */
    private function mapEnum(array $cases): array
    {
        $out = [];
        foreach ($cases as $case) {
            if (method_exists($case, 'label')) {
                $out[$case->value] = $case->label();
            }
        }

        return $out;
    }
}
