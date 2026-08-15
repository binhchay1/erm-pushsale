<?php

namespace Tests\Feature\Customers;

use App\Enums\ClosingStatus;
use App\Enums\OperationStage;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerProfilePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_is_paginated_on_the_server(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);

        foreach (range(1, 25) as $number) {
            Order::query()->create([
                'order_code' => sprintf('ORD-PAGE-%03d', $number),
                'sale_user_id' => $sales->id,
                'customer_name' => "Khách {$number}",
                'customer_phone' => '0912'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
                'operation_stage' => OperationStage::NewCustomer->value,
                'closing_status' => ClosingStatus::Open->value,
                'data_arrived_at' => now()->subMinutes($number),
                'assigned_at' => now()->subMinutes($number),
            ]);
        }

        $this->actingAs($sales)
            ->get('/sales/customers?per_page=10&page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/CustomerProfile')
                ->has('report.rows.data', 10)
                ->where('report.rows.meta.current_page', 2)
                ->where('report.rows.meta.last_page', 3)
                ->where('report.rows.meta.per_page', 10)
                ->where('report.rows.meta.total', 25)
                ->where('report.rows.meta.from', 11)
                ->where('report.rows.meta.to', 20)
                ->where('filters.page', 2)
                ->where('filters.per_page', 10)
            );
    }

    public function test_customer_profile_exposes_duplicate_and_returning_flags_plus_delete_wiring(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $phone = '0912888777';

        Order::query()->create([
            'order_code' => 'ORD-DUP-1',
            'sale_user_id' => $sales->id,
            'customer_name' => 'Khách trùng A',
            'customer_phone' => $phone,
            'is_duplicate_phone' => false,
            'is_returning_customer' => false,
            'operation_stage' => OperationStage::NewCustomer->value,
            'closing_status' => ClosingStatus::Open->value,
            'data_arrived_at' => now()->subHour(),
            'assigned_at' => now()->subHour(),
        ]);

        Order::query()->create([
            'order_code' => 'ORD-DUP-2',
            'sale_user_id' => $sales->id,
            'customer_name' => 'Khách trùng B',
            'customer_phone' => '84912888777',
            'is_duplicate_phone' => false,
            'is_returning_customer' => true,
            'operation_stage' => OperationStage::NewCustomer->value,
            'closing_status' => ClosingStatus::Open->value,
            'data_arrived_at' => now()->subMinutes(10),
            'assigned_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($sales)
            ->get('/sales/customers?per_page=20&page=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/CustomerProfile')
                ->where('saleOrderActionBaseUrl', '/sales')
                ->where('filterOptions.permissions.canDeleteOrders', true)
                ->has('report.rows.data', 1)
                ->where('report.rows.data.0.isDuplicatePhone', true)
                ->where('report.rows.data.0.isReturningCustomer', true)
            );
    }
}
