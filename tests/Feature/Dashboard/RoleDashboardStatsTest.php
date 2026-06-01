<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_snapshot_includes_layout_series(): void
    {
        $user = User::factory()->create(['role' => UserRole::Marketing]);

        $stats = DashboardStatsService::marketingSnapshot($user);

        $this->assertArrayHasKey('lead_sources', $stats);
        $this->assertArrayHasKey('revenue_series', $stats);
        $this->assertArrayHasKey('funnel', $stats);
        $this->assertCount(7, $stats['revenue_series']);
        $this->assertSame(['label', 'value'], array_keys($stats['revenue_series'][0]));
        $this->assertSame('Lead', $stats['funnel'][0]['label']);
    }

    public function test_sales_snapshot_includes_layout_series(): void
    {
        $user = User::factory()->create(['role' => UserRole::Sales]);

        $stats = DashboardStatsService::salesSnapshot($user);

        $this->assertArrayHasKey('orders_closed_series', $stats);
        $this->assertArrayHasKey('funnel', $stats);
        $this->assertCount(7, $stats['orders_closed_series']);
        $this->assertSame(['label', 'value'], array_keys($stats['orders_closed_series'][0]));
        $this->assertSame('Lead', $stats['funnel'][0]['label']);
    }

    public function test_warehouse_snapshot_includes_delivery_breakdown(): void
    {
        $stats = DashboardStatsService::warehouseSnapshot();

        $this->assertArrayHasKey('delivery_breakdown', $stats);
        $this->assertIsArray($stats['delivery_breakdown']);
        if ($stats['delivery_breakdown'] !== []) {
            $this->assertSame(['label', 'value'], array_keys($stats['delivery_breakdown'][0]));
        }
    }

    public function test_accounting_snapshot_includes_paid_orders_series(): void
    {
        $stats = DashboardStatsService::accountingSnapshot();

        $this->assertArrayHasKey('paid_orders_series', $stats);
        $this->assertCount(7, $stats['paid_orders_series']);
        $this->assertSame(['label', 'value'], array_keys($stats['paid_orders_series'][0]));
    }

    public function test_allocator_snapshot_includes_layout_series(): void
    {
        $stats = DashboardStatsService::allocatorSnapshot();

        $this->assertArrayHasKey('processed_series', $stats);
        $this->assertArrayHasKey('routing_status_breakdown', $stats);
        $this->assertArrayHasKey('funnel', $stats);
        $this->assertCount(7, $stats['processed_series']);
        $this->assertSame(['label', 'value'], array_keys($stats['processed_series'][0]));
        $this->assertSame('Lead ingest', $stats['funnel'][0]['label']);
    }
}
