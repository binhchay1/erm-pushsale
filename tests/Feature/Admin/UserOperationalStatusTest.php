<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Pushsale\UserOperationalProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserOperationalStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_receive_data_off_and_on(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Sales]);

        UserOperationalProfile::query()->create([
            'user_id' => $target->id,
            'company_id' => $target->company_id,
            'employee_code' => 'NV10001',
            'base_salary' => 0,
            'receive_data' => true,
            'is_locked' => false,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.operational-status', $target), ['receive_data' => 0])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse((bool) $target->fresh()->operationalProfile?->receive_data);

        $this->actingAs($admin)
            ->patch(route('admin.users.operational-status', $target), ['receive_data' => 1])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue((bool) $target->fresh()->operationalProfile?->receive_data);
    }

    public function test_admin_can_toggle_lock_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Sales]);

        UserOperationalProfile::query()->create([
            'user_id' => $target->id,
            'company_id' => $target->company_id,
            'employee_code' => 'NV10002',
            'base_salary' => 0,
            'receive_data' => true,
            'is_locked' => false,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.operational-status', $target), ['is_locked' => 1])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue((bool) $target->fresh()->operationalProfile?->is_locked);

        $this->actingAs($admin)
            ->patch(route('admin.users.operational-status', $target), ['is_locked' => 0])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse((bool) $target->fresh()->operationalProfile?->is_locked);
    }
}
