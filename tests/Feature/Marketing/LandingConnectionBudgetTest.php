<?php

namespace Tests\Feature\Marketing;

use App\Enums\UserRole;
use App\Models\LandingConnection;
use App\Models\MarketingSource;
use App\Models\MarketingSourceDailyMetric;
use App\Models\User;
use App\Services\Marketing\MarketingBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LandingConnectionBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_and_daily_budgets_are_allocated_only_for_intersecting_days(): void
    {
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $source = $this->source($marketer, 'Nguồn tổng');
        $connection = $this->connection($marketer, $source, [
            'budget_type' => 'total',
            'budget_amount' => 310_000,
            'budget_start_date' => '2026-07-01',
            'budget_end_date' => '2026-07-31',
        ]);
        $service = app(MarketingBudgetService::class);

        $this->assertSame(310_000, $service->plannedTotal($connection));
        $this->assertSame(
            100_000,
            $service->plannedForRange($connection, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-10')),
        );
        $this->assertSame(
            110_000,
            $service->plannedForRange($connection, Carbon::parse('2026-07-21'), Carbon::parse('2026-08-10')),
        );
        $this->assertSame(
            0,
            $service->plannedForRange($connection, Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31')),
        );

        $connection->update([
            'budget_amount' => 100,
            'budget_start_date' => '2026-07-01',
            'budget_end_date' => '2026-07-03',
        ]);
        $connection->refresh();
        $this->assertSame(33, $service->plannedForRange($connection, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-01')));
        $this->assertSame(33, $service->plannedForRange($connection, Carbon::parse('2026-07-02'), Carbon::parse('2026-07-02')));
        $this->assertSame(34, $service->plannedForRange($connection, Carbon::parse('2026-07-03'), Carbon::parse('2026-07-03')));
        $this->assertSame(100, $service->plannedForRange($connection, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-03')));

        $connection->update([
            'budget_type' => 'daily',
            'budget_amount' => 25_000,
            'budget_start_date' => '2026-07-01',
            'budget_end_date' => '2026-07-31',
        ]);
        $connection->refresh();

        $this->assertSame(775_000, $service->plannedTotal($connection));
        $this->assertSame(
            250_000,
            $service->plannedForRange($connection, Carbon::parse('2026-07-01'), Carbon::parse('2026-07-10')),
        );
    }

    public function test_actual_spend_overrides_only_the_source_day_that_has_been_entered(): void
    {
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $sourceA = $this->source($marketer, 'Facebook');
        $sourceB = $this->source($marketer, 'Google');
        $this->connection($marketer, $sourceA, [
            'budget_type' => 'daily',
            'budget_amount' => 100_000,
            'budget_start_date' => '2026-07-01',
            'budget_end_date' => '2026-07-03',
        ]);
        $this->connection($marketer, $sourceB, [
            'budget_type' => 'total',
            'budget_amount' => 300_000,
            'budget_start_date' => '2026-07-01',
            'budget_end_date' => '2026-07-03',
        ]);

        MarketingSourceDailyMetric::query()->create([
            'company_id' => $marketer->company_id,
            'marketing_source_id' => $sourceA->id,
            'metric_date' => '2026-07-02',
            'budget' => 120_000,
            'clicks' => 100,
            'created_by_user_id' => $marketer->id,
            'updated_by_user_id' => $marketer->id,
        ]);

        $result = app(MarketingBudgetService::class)->effectiveForSourceIds(
            collect([$sourceA->id, $sourceB->id]),
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-03'),
        );

        $this->assertSame(600_000, $result['planned']);
        $this->assertSame(120_000, $result['actual']);
        $this->assertSame(620_000, $result['amount']);
        $this->assertSame('mixed', $result['basis']);
    }

    private function source(User $marketer, string $name): MarketingSource
    {
        return MarketingSource::query()->create([
            'name' => $name,
            'marketer_user_id' => $marketer->id,
            'created_by_user_id' => $marketer->id,
            'ad_channel' => strtolower($name),
            'utm_source' => 'landing_connection',
            'utm_campaign' => (string) str($name)->slug(),
            'budget' => 0,
            'is_active' => true,
            'is_approved' => true,
            'lead_allocation' => 'inherit',
            'js_tracking_enabled' => false,
        ]);
    }

    /** @param array<string, mixed> $budget */
    private function connection(User $marketer, MarketingSource $source, array $budget): LandingConnection
    {
        return LandingConnection::query()->create([
            'company_id' => $marketer->company_id,
            'marketing_source_id' => $source->id,
            'name' => $source->name.' landing',
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => $source->ad_channel,
            'allocation_method' => 'inherit',
            ...$budget,
            'is_approved' => true,
            'is_active' => true,
            'created_by_user_id' => $marketer->id,
            'updated_by_user_id' => $marketer->id,
        ]);
    }
}
