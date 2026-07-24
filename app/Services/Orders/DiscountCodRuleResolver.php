<?php

namespace App\Services\Orders;

use App\Models\Pushsale\DiscountCodRule;

class DiscountCodRuleResolver
{
    public function discountForSubtotal(int $subtotal): int
    {
        $rule = $this->ruleFor('discount', $subtotal);
        if (! $rule) {
            return 0;
        }

        if ($rule->calculation_type === 'percent') {
            return max(0, (int) round($subtotal * ((int) $rule->discount_value) / 100));
        }

        return max(0, (int) $rule->discount_value);
    }

    public function codFeeForSubtotal(int $subtotal): int
    {
        $rule = $this->ruleFor('cod', $subtotal);

        return $rule ? max(0, (int) $rule->discount_value) : 0;
    }

    private function ruleFor(string $type, int $subtotal): ?DiscountCodRule
    {
        return DiscountCodRule::query()
            ->where('rule_type', $type)
            ->where('is_active', true)
            ->where('order_from', '<=', max(0, $subtotal))
            ->orderByDesc('order_from')
            ->orderByDesc('id')
            ->first();
    }
}
