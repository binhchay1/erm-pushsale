<?php

namespace Tests\Feature\Reports;

use App\Data\ReportFilterData;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Services\Reports\ReportScopeResolver;
use App\Services\Reports\ReportMetricService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ReportScopeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_scope_cannot_be_widened_with_requested_sale_filter(): void
    {
        $salesA = User::factory()->create(['role' => UserRole::Sales]);
        $salesB = User::factory()->create(['role' => UserRole::Sales]);
        $orderA = $this->createOrder('ORD-SALES-A', ['sale_user_id' => $salesA->id]);
        $this->createOrder('ORD-SALES-B', ['sale_user_id' => $salesB->id]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['sale_id' => $salesB->id]), $salesA);

        $ids = app(ReportScopeResolver::class)
            ->applyOrderScope(Order::query(), $salesA, $filter)
            ->pluck('id')
            ->all();

        $this->assertSame([$orderA->id], $ids);
    }

    public function test_sales_team_leader_scope_includes_team_members(): void
    {
        $team = Team::query()->create(['name' => 'Sales Team', 'type' => 'sale']);
        $leader = User::factory()->create(['role' => UserRole::Sales, 'team_id' => $team->id, 'is_team_leader' => true]);
        $member = User::factory()->create(['role' => UserRole::Sales, 'team_id' => $team->id, 'manager_user_id' => $leader->id]);
        $outsider = User::factory()->create(['role' => UserRole::Sales]);
        $leaderOrder = $this->createOrder('ORD-LEADER', ['sale_user_id' => $leader->id]);
        $memberOrder = $this->createOrder('ORD-MEMBER', ['sale_user_id' => $member->id]);
        $this->createOrder('ORD-OUTSIDER', ['sale_user_id' => $outsider->id]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports'), $leader);

        $ids = app(ReportScopeResolver::class)
            ->applyOrderScope(Order::query()->orderBy('id'), $leader, $filter)
            ->pluck('id')
            ->all();

        $this->assertSame([$leaderOrder->id, $memberOrder->id], $ids);
    }

    public function test_sales_head_scope_is_unrestricted_within_tenant(): void
    {
        $head = User::factory()->create(['role' => UserRole::Sales, 'org_level' => \App\Enums\OrgLevel::Head]);
        $salesA = User::factory()->create(['role' => UserRole::Sales]);
        $salesB = User::factory()->create(['role' => UserRole::Sales]);
        $orderA = $this->createOrder('ORD-HEAD-A', ['sale_user_id' => $salesA->id]);
        $orderB = $this->createOrder('ORD-HEAD-B', ['sale_user_id' => $salesB->id]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports'), $head);

        $this->assertNull(app(ReportScopeResolver::class)->allowedSaleIds($head));

        $ids = app(ReportScopeResolver::class)
            ->applyOrderScope(Order::query()->orderBy('id'), $head, $filter)
            ->pluck('id')
            ->all();

        $this->assertSame([$orderA->id, $orderB->id], $ids);
    }

    public function test_sales_supervisor_scope_includes_same_team(): void
    {
        $team = Team::query()->create(['name' => 'Sales Sup Team', 'type' => 'sale']);
        $supervisor = User::factory()->create([
            'role' => UserRole::Sales,
            'team_id' => $team->id,
            'org_level' => \App\Enums\OrgLevel::Supervisor,
            'is_team_leader' => false,
        ]);
        $member = User::factory()->create(['role' => UserRole::Sales, 'team_id' => $team->id]);
        $outsider = User::factory()->create(['role' => UserRole::Sales]);
        $supOrder = $this->createOrder('ORD-SUP', ['sale_user_id' => $supervisor->id]);
        $memberOrder = $this->createOrder('ORD-SUP-MEMBER', ['sale_user_id' => $member->id]);
        $this->createOrder('ORD-SUP-OUT', ['sale_user_id' => $outsider->id]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports'), $supervisor);

        $ids = app(ReportScopeResolver::class)
            ->applyOrderScope(Order::query()->orderBy('id'), $supervisor, $filter)
            ->pluck('id')
            ->all();

        $this->assertSame([$supOrder->id, $memberOrder->id], $ids);
    }

    public function test_marketing_scope_uses_owned_campaigns_and_marketer(): void
    {
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $other = User::factory()->create(['role' => UserRole::Marketing]);
        $source = MarketingSource::query()->create(['name' => 'Ladipage A', 'marketer_user_id' => $marketer->id]);
        $ownedBySource = $this->createOrder('ORD-MKT-SOURCE', ['marketing_source_id' => $source->id]);
        $ownedByMarketer = $this->createOrder('ORD-MKT-USER', ['marketer_user_id' => $marketer->id]);
        $this->createOrder('ORD-MKT-OTHER', ['marketer_user_id' => $other->id]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', ['marketer_id' => $other->id]), $marketer);

        $ids = app(ReportScopeResolver::class)
            ->applyOrderScope(Order::query()->orderBy('id'), $marketer, $filter)
            ->pluck('id')
            ->all();

        $this->assertSame([$ownedBySource->id, $ownedByMarketer->id], $ids);
    }


    public function test_allocator_daily_order_series_respects_the_report_date_range(): void
    {
        $allocator = User::factory()->create(['role' => UserRole::Allocator]);

        $this->createOrder('ORD-ALLOCATOR-1', ['data_arrived_at' => now()->subDays(2)->setTime(9, 0)]);
        $this->createOrder('ORD-ALLOCATOR-2', ['data_arrived_at' => now()->subDay()->setTime(11, 30)]);
        $this->createOrder('ORD-ALLOCATOR-3', ['data_arrived_at' => now()->setTime(15, 0)]);
        $this->createOrder('ORD-ALLOCATOR-OUTSIDE', ['data_arrived_at' => now()->subDays(45)]);

        $filter = ReportFilterData::fromRequest(Request::create('/reports', 'GET', [
            'date_from' => now()->subDays(2)->toDateString(),
            'date_to' => now()->toDateString(),
            'date_type' => 'data_arrived',
        ]), $allocator);

        $series = app(ReportMetricService::class)->orderSeries($allocator, $filter);

        $this->assertSame(3, collect($series)->sum('value'));
        $this->assertSame([1, 1, 1], collect($series)->pluck('value')->all());
    }

    public function test_lead_scope_follows_order_scope_for_sales(): void
    {
        $salesA = User::factory()->create(['role' => UserRole::Sales]);
        $salesB = User::factory()->create(['role' => UserRole::Sales]);
        $orderA = $this->createOrder('ORD-LEAD-A', ['sale_user_id' => $salesA->id]);
        $orderB = $this->createOrder('ORD-LEAD-B', ['sale_user_id' => $salesB->id]);
        $leadA = $this->createLead('lead-a', ['order_id' => $orderA->id]);
        $this->createLead('lead-b', ['order_id' => $orderB->id]);
        $filter = ReportFilterData::fromRequest(Request::create('/reports'), $salesA);

        $ids = app(ReportScopeResolver::class)
            ->applyLeadScope(LeadIngestion::query(), $salesA, $filter)
            ->pluck('id')
            ->all();

        $this->assertSame([$leadA->id], $ids);
    }

    /** @param  array<string, mixed>  $attributes */
    private function createOrder(string $code, array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'order_code' => $code,
            'customer_name' => 'Customer '.$code,
            'customer_phone' => '0900000000',
            'delivery_status' => 'waiting_waybill',
            'data_arrived_at' => now(),
        ], $attributes));
    }

    /** @param  array<string, mixed>  $attributes */
    private function createLead(string $externalId, array $attributes = []): LeadIngestion
    {
        return LeadIngestion::query()->create(array_merge([
            'platform' => 'landing',
            'external_id' => $externalId,
            'status' => 'processed',
            'customer_name' => 'Lead '.$externalId,
            'customer_phone' => '0900000000',
            'payload' => [],
        ], $attributes));
    }
}
