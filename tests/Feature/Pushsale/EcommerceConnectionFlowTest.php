<?php

namespace Tests\Feature\Pushsale;

use App\Models\Pushsale\EcommerceProductLink;
use App\Models\Pushsale\EcommerceShopConnection;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\EcommerceDemoSeeder;
use Database\Seeders\MarketingCampaignSeeder;
use Database\Seeders\InventorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceConnectionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ecommerce_demo_seed_creates_shop_products_and_errors(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(CatalogSeeder::class);
        $this->seed(InventorySeeder::class);
        $this->seed(MarketingCampaignSeeder::class);
        $this->seed(EcommerceDemoSeeder::class);

        $this->assertDatabaseCount('ecommerce_shop_connections', 4);
        $this->assertGreaterThan(0, EcommerceProductLink::query()->count());
        $this->assertDatabaseHas('failed_partner_orders', ['platform' => 'TikTok']);
    }

    public function test_pages_return_new_pushsale_templates(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(CatalogSeeder::class);
        $this->seed(InventorySeeder::class);
        $this->seed(MarketingCampaignSeeder::class);
        $this->seed(EcommerceDemoSeeder::class);

        $admin = User::query()->where('email', 'admin@saleops.local')->firstOrFail();

        $this->actingAs($admin)->get('/admin/ecommerce/connect-shops')->assertOk();
        $this->actingAs($admin)->get('/admin/ecommerce/connect-products')->assertOk();
        $this->actingAs($admin)->get('/admin/ecommerce/sync-errors')->assertOk();
    }
}
