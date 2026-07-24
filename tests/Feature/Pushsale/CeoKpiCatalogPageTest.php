<?php

namespace Tests\Feature\Pushsale;

use App\Models\Pushsale\KpiCatalogItem;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\KpiCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoKpiCatalogPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_kpi_catalog_seed_creates_marketing_and_sales_templates(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(KpiCatalogSeeder::class);

        $this->assertGreaterThanOrEqual(3, KpiCatalogItem::query()->where('position_key', 'marketing')->count());
        $this->assertGreaterThanOrEqual(3, KpiCatalogItem::query()->where('position_key', 'sales')->count());
        $this->assertGreaterThan(0, KpiCatalogItem::query()->where('position_key', 'marketing')->sum('daily_budget'));
        $this->assertGreaterThan(0, KpiCatalogItem::query()->where('position_key', 'sales')->sum('daily_new_contacts'));
    }

    public function test_monthly_kpi_can_use_catalog_business_targets(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(KpiCatalogSeeder::class);

        $marketing = User::query()->where('role', 'marketing')->firstOrFail();
        $catalog = KpiCatalogItem::query()->where('position_key', 'marketing')->orderBy('sort_order')->firstOrFail();

        MonthlyKpiPlan::query()->create([
            'user_id' => $marketing->id,
            'year' => now()->year,
            'month' => now()->month,
            'kpi_name' => $catalog->kpi_name,
            'budget' => $catalog->daily_budget * 26,
            'clicks_target' => $catalog->daily_clicks * 26,
            'contacts_target' => $catalog->daily_contacts * 26,
            'revenue_target' => $catalog->daily_revenue * 26,
            'bonus_percent' => 1.5,
            'base_salary' => 9000000,
            'working_days' => 26,
        ]);

        $this->assertDatabaseHas('monthly_kpi_plans', [
            'user_id' => $marketing->id,
            'kpi_name' => $catalog->kpi_name,
            'budget' => $catalog->daily_budget * 26,
        ]);
    }
}
