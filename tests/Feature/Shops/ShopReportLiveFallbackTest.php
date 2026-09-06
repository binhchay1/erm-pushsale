<?php

namespace Tests\Feature\Shops;

use App\Data\ReportFilterData;
use App\Models\Company;
use App\Models\Shop;
use App\Models\User;
use App\Services\Reporting\ReportFactReader;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopReportLiveFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_fact_reader_rejects_facts_when_shop_is_selected(): void
    {
        $company = Company::query()->create([
            'name' => 'Report Co',
            'slug' => 'report-co-shop',
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
        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => User::ROLE_ADMIN,
            'default_shop_id' => $shop->id,
        ]);

        $filter = ReportFilterData::fromRequest(
            request()->merge([
                'date_from' => now()->subDays(3)->toDateString(),
                'date_to' => now()->toDateString(),
            ]),
            $user,
        );

        $tenant = app(TenantManager::class);
        $tenant->set($company->id);
        $tenant->setShop($shop->id);

        $this->assertFalse(app(ReportFactReader::class)->supports($filter, $user));
    }
}
