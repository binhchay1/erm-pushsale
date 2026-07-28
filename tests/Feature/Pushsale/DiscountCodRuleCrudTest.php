<?php

namespace Tests\Feature\Pushsale;

use App\Enums\UserRole;
use App\Models\Pushsale\DiscountCodRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountCodRuleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_discount_and_cod_rules(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post('/admin/sales/discount-cod-rules/records', [
                'payload' => [
                    'rule_type' => 'discount',
                    'order_from' => 500000,
                    'discount_value' => 20000,
                    'calculation_type' => 'fixed',
                    'is_active' => true,
                ],
            ])
            ->assertRedirect();

        $discount = DiscountCodRule::query()
            ->where('rule_type', 'discount')
            ->where('order_from', 500000)
            ->firstOrFail();

        $this->actingAs($admin)
            ->put('/admin/sales/discount-cod-rules/records/'.$discount->id, [
                'payload' => [
                    'rule_type' => 'discount',
                    'order_from' => 500000,
                    'discount_value' => 5,
                    'calculation_type' => 'percent',
                    'is_active' => true,
                ],
            ])
            ->assertRedirect();

        $this->assertSame(5, (int) $discount->fresh()->discount_value);
        $this->assertSame('percent', $discount->fresh()->calculation_type);

        $this->actingAs($admin)
            ->post('/admin/sales/discount-cod-rules/records', [
                'payload' => [
                    'rule_type' => 'cod',
                    'order_from' => 0,
                    'discount_value' => 30000,
                    'calculation_type' => 'fixed',
                    'cod_from' => 0,
                    'cod_to' => 30000,
                    'is_active' => true,
                ],
            ])
            ->assertRedirect();

        $cod = DiscountCodRule::query()->where('rule_type', 'cod')->firstOrFail();
        $this->assertSame(30000, (int) $cod->discount_value);

        $this->actingAs($admin)
            ->delete('/admin/sales/discount-cod-rules/records/'.$cod->id)
            ->assertRedirect();

        $this->assertSoftDeleted('discount_cod_rules', ['id' => $cod->id]);
    }

    public function test_discount_cod_index_renders_split_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        DiscountCodRule::query()->create([
            'rule_type' => 'discount',
            'order_from' => 0,
            'discount_value' => 0,
            'calculation_type' => 'fixed',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/sales/discount-cod-rules')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/OperationsConfig/DiscountCodRules')
                ->has('rows', 1)
            );
    }
}
