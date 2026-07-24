<?php

namespace Tests\Feature\Pushsale;

use App\Integrations\Facebook\FacebookLeadDriver;
use App\Models\Company;
use App\Models\MarketingSource;
use App\Models\Pushsale\FacebookPageMapping;
use App\Services\Leads\LeadIngestionService;
use App\Services\Pushsale\PushsalePageService;
use App\Support\TenantManager;
use Database\Seeders\AccountSeeder;
use Database\Seeders\FacebookPageMappingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookPageMappingBusinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_111_seed_populates_page_mapping_and_marketing_source(): void
    {
        $company = Company::query()->create([
            'name' => 'Công ty Facebook',
            'slug' => 'facebook-unit-config',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'standard',
            'max_users' => 50,
        ]);

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $this->seed(AccountSeeder::class);
            $this->seed(FacebookPageMappingSeeder::class);

            $this->assertGreaterThanOrEqual(5, FacebookPageMapping::query()->count());
            $this->assertGreaterThanOrEqual(5, MarketingSource::query()->where('utm_source', 'facebook')->count());

            $rows = app(PushsalePageService::class)->rows('1.11', request())['data'];

            $this->assertNotEmpty($rows);
            $this->assertArrayHasKey('fanpage', $rows[0]);
            $this->assertArrayHasKey('fb_creator', $rows[0]);
            $this->assertArrayHasKey('pushsale_user', $rows[0]);
        });
    }

    public function test_facebook_driver_maps_page_id_to_marketing_user(): void
    {
        $company = Company::query()->create([
            'name' => 'Công ty Facebook Driver',
            'slug' => 'facebook-driver-config',
            'status' => Company::STATUS_ACTIVE,
            'plan' => 'standard',
            'max_users' => 50,
        ]);

        app(TenantManager::class)->forCompany($company->id, function (): void {
            $this->seed(AccountSeeder::class);
            $this->seed(FacebookPageMappingSeeder::class);

            $mapping = FacebookPageMapping::query()->with('marketer')->firstOrFail();
            $normalized = (new FacebookLeadDriver)->normalize([
                'entry' => [[
                    'id' => $mapping->page_id,
                    'changes' => [[
                        'field' => 'leadgen',
                        'value' => [
                            'leadgen_id' => 'lead-test-001',
                            'ad_id' => 'ad-001',
                            'field_data' => [
                                ['name' => 'full_name', 'values' => ['Nguyễn Demo']],
                                ['name' => 'phone_number', 'values' => ['0912345678']],
                            ],
                        ],
                    ]],
                ]],
            ]);

            $this->assertSame($mapping->page_id, $normalized['facebook_page_id']);
            $this->assertSame($mapping->marketer_user_id, $normalized['marketer_user_id']);
            $this->assertSame('facebook', $normalized['utm_source']);
            $this->assertSame($mapping->page_id, $normalized['utm_campaign']);
        });
    }
}
