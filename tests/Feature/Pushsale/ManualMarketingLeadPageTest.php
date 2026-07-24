<?php

namespace Tests\Feature\Pushsale;

use App\Models\Company;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Product;
use App\Services\Pushsale\PushsalePageService;
use App\Support\TenantManager;
use Database\Seeders\AccountSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ManualMarketingContactSeeder;
use Database\Seeders\MarketingCampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualMarketingLeadPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_262_has_source_product_options_and_manual_contact_rows(): void
    {
        $company = Company::query()->create([
            'name' => 'Công ty nhập tay',
            'slug' => 'manual-marketing-contact',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'standard',
            'max_users' => 50,
        ]);

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $this->seed(AccountSeeder::class);
            $this->seed(CatalogSeeder::class);
            $this->seed(MarketingCampaignSeeder::class);
            $this->seed(ManualMarketingContactSeeder::class);

            $this->assertGreaterThanOrEqual(20, Product::query()->count());
            $this->assertGreaterThanOrEqual(20, MarketingSource::query()->count());
            $this->assertGreaterThanOrEqual(100, LeadIngestion::query()->where('platform', 'manual')->count());

            $service = app(PushsalePageService::class);
            $options = $service->filterOptions('2.6.2');
            $rows = $service->rows('2.6.2', request())['data'];

            $this->assertNotEmpty($options['sources']);
            $this->assertNotEmpty($options['products']);
            $this->assertNotEmpty($rows);
            $this->assertSame('manual', $rows[0]['platform']);
        });
    }
}
