<?php

namespace Tests\Feature\Shops;

use App\Models\Company;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LeadImportLightweightTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_import_page_skips_heavy_catalog_payload(): void
    {
        $company = Company::query()->create([
            'name' => 'Import Co',
            'slug' => 'import-co-shop',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'trial',
        ]);
        $shop = Shop::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Main',
            'code' => 'main',
            'is_default' => true,
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_ADMIN,
            'default_shop_id' => $shop->id,
        ]);
        $shop->users()->attach($admin->id);

        $this->actingAs($admin)
            ->get('/admin/leads/import')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Integrations/LeadImportPage')
                ->where('rows', [])
                ->where('filterOptions', [])
                ->where('templateHtml', ''));
    }
}
