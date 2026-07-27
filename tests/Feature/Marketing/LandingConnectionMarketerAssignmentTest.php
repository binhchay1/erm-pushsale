<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingConnectionMarketerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_user_creates_landing_connection_with_own_marketer_id(): void
    {
        $otherMarketer = User::factory()->create(['role' => UserRole::Marketing, 'name' => 'MKT A']);
        $targetMarketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'name' => 'MKT B',
            'company_id' => $otherMarketer->company_id,
        ]);
        $sale = User::factory()->create([
            'role' => UserRole::Sales,
            'company_id' => $otherMarketer->company_id,
        ]);

        $this->actingAs($targetMarketer);

        $payload = [
            'name' => 'Landing marketer tự gán',
            'marketer_user_id' => $otherMarketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'google_ads',
            'allocation_method' => 'inherit',
            'manual_import' => true,
            'is_approved' => true,
            'is_active' => true,
            'sources' => [[
                'client_key' => 'main-marketer-self',
                'name' => 'Landing marketer tự gán',
                'source_type' => 'main',
                'source_url' => 'https://landing.example.test/marketer-self',
                'redirect_url' => null,
                'sort_order' => 0,
                'is_active' => true,
            ]],
            'products' => [],
            'sale_user_ids' => [$sale->id],
        ];

        $this->post('/admin/marketing/landing-connections/records', $payload)
            ->assertRedirect('/admin/marketing/landing-connections')
            ->assertSessionHas('success');

        $connection = LandingConnection::query()
            ->where('name', 'Landing marketer tự gán')
            ->firstOrFail();

        $this->assertSame($targetMarketer->id, $connection->marketer_user_id);
        $this->assertFalse((bool) $connection->is_approved);
    }
}
