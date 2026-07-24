<?php

namespace Tests\Feature\Pushsale;

use App\Models\Pushsale\AnnualBusinessPlanMetric;
use Database\Seeders\AccountSeeder;
use Database\Seeders\AnnualBusinessPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoYearlyBusinessPlanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_business_plan_seed_creates_all_metrics_for_current_year(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(AnnualBusinessPlanSeeder::class);

        $this->assertSame(12 * count(AnnualBusinessPlanMetric::metricDefinitions()), AnnualBusinessPlanMetric::query()
            ->where('year', now()->year)
            ->count());

        $this->assertGreaterThan(0, AnnualBusinessPlanMetric::query()
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->where('metric_code', '1')
            ->value('planned_value'));
    }
}
