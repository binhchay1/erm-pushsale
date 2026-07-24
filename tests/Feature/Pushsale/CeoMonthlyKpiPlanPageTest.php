<?php

namespace Tests\Feature\Pushsale;

use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\BusinessKpiPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CeoMonthlyKpiPlanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_kpi_demo_seed_creates_marketing_rows_for_current_period(): void
    {
        $this->seed(AccountSeeder::class);
        $this->seed(BusinessKpiPlanSeeder::class);

        $marketingUserIds = User::query()->where('role', 'marketing')->pluck('id');

        $this->assertGreaterThan(0, MonthlyKpiPlan::query()
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->whereIn('user_id', $marketingUserIds)
            ->count());
    }
}
