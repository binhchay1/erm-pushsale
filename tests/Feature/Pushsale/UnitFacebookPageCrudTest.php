<?php

namespace Tests\Feature\Pushsale;

use App\Enums\UserRole;
use App\Models\MarketingSource;
use App\Models\Pushsale\FacebookPageMapping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitFacebookPageCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_facebook_page_mapping(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);

        $this->actingAs($admin)
            ->post('/admin/integrations/facebook-pages/records', [
                'payload' => [
                    'page_id' => '99887766554433',
                    'page_name' => 'Fanpage Demo CRUD',
                    'creator_name' => 'Creator Demo',
                    'marketer_user_id' => $marketer->id,
                    'is_active' => true,
                ],
            ])
            ->assertRedirect();

        $mapping = FacebookPageMapping::query()->where('page_id', '99887766554433')->firstOrFail();
        $this->assertSame('Fanpage Demo CRUD', $mapping->page_name);
        $this->assertSame($marketer->id, $mapping->marketer_user_id);
        $this->assertDatabaseHas('marketing_sources', [
            'utm_source' => 'facebook',
            'utm_campaign' => '99887766554433',
            'marketer_user_id' => $marketer->id,
        ]);

        $other = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);

        $this->actingAs($admin)
            ->patch('/admin/integrations/facebook-pages/records/'.$mapping->id, [
                'payload' => [
                    'page_id' => '99887766554433',
                    'page_name' => 'Fanpage Demo Updated',
                    'creator_name' => 'Creator Updated',
                    'marketer_user_id' => $other->id,
                    'is_active' => true,
                ],
            ])
            ->assertRedirect();

        $mapping->refresh();
        $this->assertSame('Fanpage Demo Updated', $mapping->page_name);
        $this->assertSame($other->id, $mapping->marketer_user_id);
        $this->assertSame($other->id, MarketingSource::query()
            ->where('utm_source', 'facebook')
            ->where('utm_campaign', '99887766554433')
            ->value('marketer_user_id'));
    }

    public function test_facebook_page_index_renders_and_lists_records(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create([
            'role' => UserRole::Marketing,
            'company_id' => $admin->company_id,
        ]);

        FacebookPageMapping::query()->create([
            'page_id' => '112233445566',
            'page_name' => 'Fanpage Index',
            'creator_name' => 'Creator',
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/integrations/facebook-pages')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Integrations/UnitFacebookPages')
                ->has('rows', 1)
                ->where('rows.0._record_id', FacebookPageMapping::query()->value('id'))
                ->has('filterOptions.marketers')
            );
    }
}
