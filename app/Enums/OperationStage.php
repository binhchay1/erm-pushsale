<?php

namespace App\Enums;

enum OperationStage: string
{
    case NewCustomer = 'new_customer';
    case Call2 = 'call_2';
    case Call3 = 'call_3';
    case Call4 = 'call_4';
    case Call5 = 'call_5';
    case Call6 = 'call_6';
    /** @deprecated Legacy care workflow — keep for historical orders, hide from active UX. */
    case Care1 = 'care_1';
    /** @deprecated */
    case Care2 = 'care_2';
    /** @deprecated */
    case Care3 = 'care_3';
    case Skipped = 'skipped';
    case NoOperation = 'no_operation';

    public function label(): string
    {
        return __('enums.operation_stage.'.$this->value);
    }

    public function isLegacyCare(): bool
    {
        return in_array($this, [self::Care1, self::Care2, self::Care3], true);
    }

    /** Active sale workflow: 6 call stages (+ skipped / no_operation when requested). */
    /** @return list<self> */
    public static function activeWorkflowCases(bool $includeSkipped = true, bool $includeNoOperation = false): array
    {
        $cases = [
            self::NewCustomer,
            self::Call2,
            self::Call3,
            self::Call4,
            self::Call5,
            self::Call6,
        ];

        if ($includeSkipped) {
            $cases[] = self::Skipped;
        }

        if ($includeNoOperation) {
            $cases[] = self::NoOperation;
        }

        return $cases;
    }

    /** @return list<string> */
    public static function activeWorkflowValues(bool $includeSkipped = true, bool $includeNoOperation = false): array
    {
        return array_map(
            fn (self $stage): string => $stage->value,
            self::activeWorkflowCases($includeSkipped, $includeNoOperation),
        );
    }
}
