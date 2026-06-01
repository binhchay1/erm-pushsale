<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\ShippingWebhookEvent;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_uses_real_stats_shape(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $sale = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sale A']);

        Order::query()->create([
            'order_code' => 'ORD-001',
            'sale_user_id' => $sale->id,
            'customer_name' => 'Nguyễn Văn A',
            'customer_phone' => '0900000001',
            'closed_at' => now(),
            'delivery_status' => 'paid',
            'total' => 1_500_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        LeadIngestion::query()->create([
            'platform' => 'Facebook',
            'external_id' => 'fb-001',
            'status' => LeadIngestionStatus::Processed,
            'payload' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ShippingWebhookEvent::query()->create([
            'provider' => 'ghtk',
            'event_type' => 'cod_update',
            'is_cod_mismatch' => true,
            'payload' => [],
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->loadDeferredProps('default', fn (Assert $reload) => $reload
                    ->has('stats')
                    ->where('stats.revenue_today', 1_500_000)
                    ->where('stats.orders_closed', 1)
                    ->where('stats.leads_today', 1)
                    ->where('stats.delivery_rate', 100)
                    ->where('stats.shipping_mismatch', 1)
                    ->has('stats.revenue_series')
                    ->has('stats.orders_series')
                    ->has('stats.lead_sources')
                    ->has('stats.lead_series')
                )
            );
    }

    public function test_dashboard_stats_service_returns_funnel_rankings_and_alerts(): void
    {
        $sale = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sale Top']);
        $source = MarketingSource::query()->create([
            'name' => 'Facebook Ads',
            'utm_source' => 'Facebook',
            'budget' => 1_000_000,
        ]);

        LeadIngestion::query()->create([
            'platform' => 'Facebook',
            'external_id' => 'fb-002',
            'status' => LeadIngestionStatus::Processed,
            'payload' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        LeadIngestion::query()->create([
            'platform' => 'Facebook',
            'external_id' => 'fb-003',
            'status' => LeadIngestionStatus::Failed,
            'payload' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Order::query()->create([
            'order_code' => 'ORD-002',
            'sale_user_id' => $sale->id,
            'marketing_source_id' => $source->id,
            'customer_name' => 'Trần Văn B',
            'customer_phone' => '0900000002',
            'closed_at' => now(),
            'delivery_status' => 'delivered',
            'total' => 2_000_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Order::query()->create([
            'order_code' => 'ORD-003',
            'sale_user_id' => $sale->id,
            'marketing_source_id' => $source->id,
            'customer_name' => 'Lê Văn C',
            'customer_phone' => '0900000003',
            'delivery_status' => 'failed',
            'total' => 800_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ShippingWebhookEvent::query()->create([
            'provider' => 'ghn',
            'event_type' => 'cod_update',
            'is_cod_mismatch' => true,
            'payload' => [],
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = DashboardStatsService::adminSnapshot();

        $this->assertArrayHasKey('funnel', $stats);
        $this->assertArrayHasKey('top_sales', $stats);
        $this->assertArrayHasKey('top_sources', $stats);
        $this->assertArrayHasKey('alerts', $stats);
        $this->assertArrayHasKey('lead_series', $stats);
        $this->assertCount(7, $stats['lead_series']);
        $this->assertSame(['label', 'value'], array_keys($stats['lead_series'][0]));
        $this->assertSame('Lead', $stats['funnel'][0]['label']);
        $this->assertSame(2, $stats['funnel'][0]['value']);
        $this->assertSame('Sale Top', $stats['top_sales'][0]['name']);
        $this->assertSame(2, $stats['top_sales'][0]['orders']);
        $this->assertSame('Facebook', $stats['top_sources'][0]['name']);
        $this->assertSame(2_000_000, $stats['top_sources'][0]['revenue']);
        $this->assertNotEmpty($stats['alerts']);
    }
}
