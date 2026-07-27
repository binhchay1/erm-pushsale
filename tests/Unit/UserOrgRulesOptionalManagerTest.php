<?php

namespace Tests\Unit;

use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Users\UserOrgRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UserOrgRulesOptionalManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_staff_can_be_created_without_manager(): void
    {
        $validator = Validator::make([
            'role' => UserRole::Sales->value,
            'org_level' => OrgLevel::Staff->value,
            'manager_user_id' => null,
        ], [
            'role' => ['required'],
            'org_level' => ['nullable'],
            'manager_user_id' => ['nullable'],
        ]);

        UserOrgRules::validate($validator);

        $this->assertFalse($validator->errors()->has('manager_user_id'));
    }

    public function test_marketing_staff_can_be_created_without_manager(): void
    {
        $validator = Validator::make([
            'role' => UserRole::Marketing->value,
            'org_level' => OrgLevel::Staff->value,
            'manager_user_id' => '',
        ], [
            'role' => ['required'],
            'org_level' => ['nullable'],
            'manager_user_id' => ['nullable'],
        ]);

        UserOrgRules::validate($validator);

        $this->assertFalse($validator->errors()->has('manager_user_id'));
    }

    public function test_invalid_manager_still_rejected_when_provided(): void
    {
        $sales = User::factory()->create([
            'role' => UserRole::Sales,
            'org_level' => OrgLevel::Staff,
            'is_team_leader' => false,
        ]);

        $validator = Validator::make([
            'role' => UserRole::Marketing->value,
            'org_level' => OrgLevel::Staff->value,
            'manager_user_id' => $sales->id,
        ], [
            'role' => ['required'],
            'org_level' => ['nullable'],
            'manager_user_id' => ['nullable'],
        ]);

        UserOrgRules::validate($validator);

        $this->assertTrue($validator->errors()->has('manager_user_id'));
    }
}
