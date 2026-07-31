<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserRole;
use App\Models\AppSetting;
use App\Models\LandingConnection;
use App\Models\LandingConnectionSource;
use App\Models\Pushsale\PartnerConnection;
use App\Models\User;
use App\Services\Marketing\PartnerConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerConnectionsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_partner_connections_page_with_provider_logos(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/admin/marketing/partner-connections')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Marketing/PartnerConnections')
                ->has('providers', 7)
                ->where('selectedPartner', 'cnvloyalty')
                ->where('providers.0.logo', '/images/partners/cnvloyalty.png'));
    }

    public function test_admin_can_toggle_provider_and_attach_landing_sources(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);

        $landing = LandingConnection::query()->create([
            'company_id' => $admin->company_id,
            'name' => 'Landing Cuccu test',
            'marketer_user_id' => $marketer->id,
            'created_by_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'facebook_ads',
            'allocation_method' => 'round_robin',
            'manual_import' => true,
            'is_approved' => true,
            'is_active' => true,
            'public_token' => 'landingtoken123456789012345678901234',
        ]);

        LandingConnectionSource::query()->create([
            'company_id' => $admin->company_id,
            'landing_connection_id' => $landing->id,
            'name' => 'Main',
            'source_type' => LandingConnectionSource::TYPE_MAIN,
            'source_url' => 'https://landing.example.test/cuccu',
            'sort_order' => 0,
            'is_active' => true,
            'public_token' => 'sourcetoken1234567890123456789012345',
        ]);

        $this->actingAs($admin)
            ->patchJson('/admin/marketing/partner-connections/provider', [
                'partner' => 'cuccu',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('provider.is_active', true);

        $active = json_decode((string) AppSetting::get(PartnerConnectionService::ACTIVE_SETTING_KEY, '{}'), true);
        $this->assertTrue((bool) ($active['cuccu'] ?? false));

        $this->actingAs($admin)
            ->postJson('/admin/marketing/partner-connections/attach-sources', [
                'partner' => 'cuccu',
                'landing_connection_ids' => [$landing->id],
            ])
            ->assertOk()
            ->assertJsonPath('count', 1);

        $connection = PartnerConnection::query()
            ->where('partner_type', 'cuccu')
            ->where('landing_connection_id', $landing->id)
            ->first();

        $this->assertNotNull($connection);
        $this->assertSame('Landing Cuccu test', $connection->name);
        $this->assertNotEmpty($connection->public_token);

        $this->actingAs($admin)
            ->getJson('/admin/marketing/partner-connections/eligible-sources?partner=cuccu&connection_type=landing')
            ->assertOk()
            ->assertJsonPath('data.0.already_attached', true);
    }
}
