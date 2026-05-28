<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_phase_zero_demo_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDemoUser('admin@saleops.local', UserRole::Admin);
        $this->assertDemoUser('sales@saleops.local', UserRole::Sales);
        $this->assertDemoUser('marketing@saleops.local', UserRole::Marketing);
        $this->assertDemoUser('warehouse@saleops.local', UserRole::Warehouse);
    }

    private function assertDemoUser(string $email, UserRole $role): void
    {
        $user = User::query()->where('email', $email)->first();

        $this->assertNotNull($user, "Demo user {$email} should exist.");
        $this->assertSame($role, $user->role);
        $this->assertTrue(Hash::check('password', $user->password));
    }
}
