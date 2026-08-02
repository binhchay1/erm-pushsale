<?php

namespace Tests\Feature\Operations;

use App\Data\ReportFilterData;
use App\Enums\OrgLevel;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;
use App\Services\Operations\SaleOperationService;
use App\Services\Operations\SalesVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SalesVisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_only_sees_and_operates_own_orders(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Sales, 'org_level' => OrgLevel::Staff]);
        $other = User::factory()->create(['role' => UserRole::Sales]);
        $own = $this->createOrder('ORD-OWN', ['sale_user_id' => $staff->id]);
        $foreign = $this->createOrder('ORD-FOREIGN', ['sale_user_id' => $other->id]);

        $visibility = app(SalesVisibilityScope::class);
        $this->assertSame([(int) $staff->id], $visibility->allowedSaleIds($staff));
        $this->assertTrue($visibility->canOperateOrder($staff, $own));
        $this->assertFalse($visibility->canOperateOrder($staff, $foreign));

        $report = app(SaleOperationService::class)->buildPaginated(
            ReportFilterData::fromRequest(Request::create('/sales/workspace', 'GET', [
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString(),
                'no_closing_date_limit' => 1,
            ]), $staff),
            $staff,
        );

        $ids = collect($report['rows']['data'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertSame([$own->id], $ids);
    }

    public function test_team_leader_sees_and_operates_team_orders(): void
    {
        $team = Team::query()->create(['name' => 'Team A', 'type' => 'sale']);
        $leader = User::factory()->create([
            'role' => UserRole::Sales,
            'team_id' => $team->id,
            'is_team_leader' => true,
        ]);
        $member = User::factory()->create([
            'role' => UserRole::Sales,
            'team_id' => $team->id,
            'manager_user_id' => $leader->id,
        ]);
        $outsider = User::factory()->create(['role' => UserRole::Sales]);
        $memberOrder = $this->createOrder('ORD-TM', ['sale_user_id' => $member->id]);
        $outsiderOrder = $this->createOrder('ORD-OUT', ['sale_user_id' => $outsider->id]);

        $visibility = app(SalesVisibilityScope::class);
        $this->assertTrue($visibility->canOperateOrder($leader, $memberOrder));
        $this->assertFalse($visibility->canOperateOrder($leader, $outsiderOrder));

        $report = app(SaleOperationService::class)->buildPaginated(
            ReportFilterData::fromRequest(Request::create('/sales/workspace', 'GET', [
                'date_from' => now()->subDays(7)->toDateString(),
                'date_to' => now()->toDateString(),
                'no_closing_date_limit' => 1,
            ]), $leader),
            $leader,
        );

        $ids = collect($report['rows']['data'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($memberOrder->id, $ids);
        $this->assertNotContains($outsiderOrder->id, $ids);
    }

    public function test_head_operates_any_sale_order_in_tenant(): void
    {
        $head = User::factory()->create(['role' => UserRole::Sales, 'org_level' => OrgLevel::Head]);
        $sales = User::factory()->create(['role' => UserRole::Sales]);
        $order = $this->createOrder('ORD-HEAD-OP', ['sale_user_id' => $sales->id]);

        $visibility = app(SalesVisibilityScope::class);
        $this->assertNull($visibility->allowedSaleIds($head));
        $this->assertTrue($visibility->canOperateOrder($head, $order));
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
}
