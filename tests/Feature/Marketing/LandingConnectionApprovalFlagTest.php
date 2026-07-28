<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingConnectionApprovalFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_toggle_inline_approval_flag_and_it_persists(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);
        $sale = User::factory()->create([
            'role' => UserRole::Sales,
            'company_id' => $admin->company_id,
        ]);

        $this->actingAs($admin);

        $createPayload = [
            'name' => 'Landing inline duyệt',
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'google_ads',
            'allocation_method' => 'inherit',
            'manual_import' => true,
            'is_approved' => false,
            'is_active' => true,
            'sources' => [[
                'client_key' => 'main-inline',
                'name' => 'Landing inline duyệt',
                'source_type' => 'main',
                'source_url' => 'https://landing.example.test/inline',
                'redirect_url' => null,
                'sort_order' => 0,
                'is_active' => true,
            ]],
            'products' => [],
            'sale_user_ids' => [$sale->id],
        ];

        $this->post('/admin/marketing/landing-connections/records', $createPayload)
            ->assertRedirect('/admin/marketing/landing-connections');

        $connection = LandingConnection::query()->where('name', 'Landing inline duyệt')->firstOrFail();
        $this->assertFalse((bool) $connection->is_approved);
        $this->assertTrue((bool) $connection->manual_import);

        $this->patch('/admin/marketing/landing-connections/records/'.$connection->id.'/flags', [
            'is_approved' => 1,
        ])->assertSessionHas('success');

        $connection->refresh();
        $this->assertTrue((bool) $connection->is_approved);
        $this->assertSame($admin->id, $connection->approved_by_user_id);
        $this->assertNotNull($connection->marketing_source_id);

        $this->patch('/admin/marketing/landing-connections/records/'.$connection->id.'/flags', [
            'is_approved' => 0,
        ])->assertSessionHas('success');

        $connection->refresh();
        $this->assertFalse((bool) $connection->is_approved);

        $this->patch('/admin/marketing/landing-connections/records/'.$connection->id.'/flags', [
            'manual_import' => 0,
        ])->assertSessionHas('success');

        $connection->refresh();
        $this->assertFalse((bool) $connection->manual_import);

        $this->patch('/admin/marketing/landing-connections/records/'.$connection->id.'/flags', [
            'manual_import' => 1,
        ])->assertSessionHas('success');

        $this->assertTrue((bool) $connection->fresh()->manual_import);
    }

    public function test_marketing_user_cannot_toggle_inline_approval_flag(): void
    {
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $connection = LandingConnection::query()->create([
            'company_id' => $marketer->company_id,
            'name' => 'Landing MKT không duyệt inline',
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'allocation_method' => 'inherit',
            'manual_import' => true,
            'is_approved' => false,
            'is_active' => true,
            'created_by_user_id' => $marketer->id,
            'updated_by_user_id' => $marketer->id,
        ]);

        $this->actingAs($marketer)
            ->patch('/admin/marketing/landing-connections/records/'.$connection->id.'/flags', [
                'is_approved' => true,
            ])
            ->assertForbidden();

        $this->assertFalse((bool) $connection->fresh()->is_approved);
    }

    public function test_create_defaults_manual_import_off_and_respects_dialog_tick(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);

        $this->actingAs($admin);

        $base = [
            'name' => 'Landing không nhập TC',
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'facebook_ads',
            'allocation_method' => 'inherit',
            'is_approved' => false,
            'is_active' => true,
            'sources' => [[
                'client_key' => 'main-no-manual',
                'name' => 'Landing không nhập TC',
                'source_type' => 'main',
                'source_url' => 'https://landing.example.test/no-manual',
                'redirect_url' => null,
                'sort_order' => 0,
                'is_active' => true,
            ]],
            'products' => [],
            'sale_user_ids' => [],
        ];

        $this->post('/admin/marketing/landing-connections/records', [
            ...$base,
            'manual_import' => 0,
        ])->assertRedirect('/admin/marketing/landing-connections');

        $off = LandingConnection::query()->where('name', 'Landing không nhập TC')->firstOrFail();
        $this->assertFalse((bool) $off->manual_import);

        $this->post('/admin/marketing/landing-connections/records', [
            ...$base,
            'name' => 'Landing có nhập TC',
            'manual_import' => 1,
            'sources' => [[
                'client_key' => 'main-yes-manual',
                'name' => 'Landing có nhập TC',
                'source_type' => 'main',
                'source_url' => 'https://landing.example.test/yes-manual',
                'redirect_url' => null,
                'sort_order' => 0,
                'is_active' => true,
            ]],
        ])->assertRedirect('/admin/marketing/landing-connections');

        $on = LandingConnection::query()->where('name', 'Landing có nhập TC')->firstOrFail();
        $this->assertTrue((bool) $on->manual_import);
    }
}
