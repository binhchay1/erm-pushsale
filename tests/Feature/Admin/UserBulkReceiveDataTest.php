<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Pushsale\UserOperationalProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBulkReceiveDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_bulk_enable_receive_data_by_user_id(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Sales]);

        UserOperationalProfile::query()->create([
            'user_id' => $target->id,
            'company_id' => $target->company_id,
            'employee_code' => 'NV00001',
            'base_salary' => 0,
            'receive_data' => false,
            'is_locked' => false,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.bulk-receive-data'), [
                'accounts' => (string) $target->id,
                'receive_data' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue((bool) $target->fresh()->operationalProfile?->receive_data);
    }

    public function test_admin_can_bulk_disable_receive_data_by_email_local(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create([
            'role' => UserRole::Sales,
            'email' => 'sale01@test-co.saleops.local',
        ]);

        UserOperationalProfile::query()->create([
            'user_id' => $target->id,
            'company_id' => $target->company_id,
            'employee_code' => 'NV00002',
            'base_salary' => 0,
            'receive_data' => true,
            'is_locked' => false,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.bulk-receive-data'), [
                'accounts' => 'sale01',
                'receive_data' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse((bool) $target->fresh()->operationalProfile?->receive_data);
    }

    public function test_sales_user_cannot_bulk_update_receive_data(): void
    {
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $target = User::factory()->create(['role' => UserRole::Sales]);

        $this->actingAs($sales)
            ->post(route('admin.users.bulk-receive-data'), [
                'accounts' => (string) $target->id,
                'receive_data' => true,
            ])
            ->assertForbidden();
    }
}
