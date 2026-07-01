<?php

namespace App\Enums;

enum ReconciliationStatus: string
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case Settled = 'settled';
    case ShortPaid = 'short_paid';
    case OverPaid = 'over_paid';
    case Returned = 'returned';
    case Mismatch = 'mismatch';
    case MissingSettlement = 'missing_settlement';

    /** Trạng thái cũ — map sang settled khi đọc báo cáo. */
    case Reconciled = 'reconciled';

    public function label(): string
    {
        return __('enums.reconciliation_status.'.$this->value);
    }

    /** @return list<string> */
    public static function exceptionStatuses(): array
    {
        return [
            self::ShortPaid->value,
            self::OverPaid->value,
            self::Mismatch->value,
            self::MissingSettlement->value,
        ];
    }

    /** @return list<string> */
    public static function settledStatuses(): array
    {
        return [self::Settled->value, self::Reconciled->value];
    }
}
