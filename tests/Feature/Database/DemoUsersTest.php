<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_seeder_creates_demo_users_for_every_role(): void
    {
        $this->seed(AccountSeeder::class);

        $this->assertDemoUser('superadmin@saleops.local', UserRole::Admin);
        $this->assertDemoUser('admin@saleops.local', UserRole::Admin);
        $this->assertDemoUser('sales@saleops.local', UserRole::Sales);
        $this->assertDemoUser('marketing@saleops.local', UserRole::Marketing);
        $this->assertDemoUser('warehouse@saleops.local', UserRole::Warehouse);
        $this->assertDemoUser('allocator@saleops.local', UserRole::Allocator);
        $this->assertDemoUser('accounting@saleops.local', UserRole::Accounting);
        // Nhân viên các cấp cũng phải có mặt.
        $this->assertDemoUser('leader.sale.a@saleops.local', UserRole::Sales);
        $this->assertDemoUser('sale01@saleops.local', UserRole::Sales);
        $this->assertDemoUser('mkt01@saleops.local', UserRole::Marketing);
        $this->assertDemoUser('wh01@saleops.local', UserRole::Warehouse);
    }

    private function assertDemoUser(string $email, UserRole $role): void
    {
        $user = User::query()->where('email', $email)->first();

        $this->assertNotNull($user, "Demo user {$email} should exist.");
        $this->assertSame($role, $user->role);
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
