<?php

namespace Tests\Unit\Support;

use App\Models\MarketingSource;
use App\Models\Order;
use App\Support\MarketingMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_keeps_revenue_separate_from_ad_spend(): void
    {
        $campaign = MarketingSource::query()->create([
            'name' => 'Ads test',
            'budget' => 1_000_000,
            'utm_campaign' => 'ads-test',
            'webhook_token' => 'tok-ads',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_code' => 'ORD-MKT-1',
            'marketing_source_id' => $campaign->id,
            'customer_name' => 'Test',
            'customer_phone' => '0900111333',
            'delivery_status' => 'delivered',
            'total' => 3_000_000,
            'discount' => 0,
            'carrier_service_fee' => 30_000,
            'cod_fee' => 20_000,
        ]);

        $summary = MarketingMetrics::summarize(collect([$order]), collect([$campaign]));

        $this->assertSame(2_950_000, $summary['attributed_revenue']);
        $this->assertSame(1_000_000, $summary['ad_spend']);
        $this->assertSame(1_950_000, $summary['net_contribution']);
        $this->assertSame(2.95, $summary['roas']);
    }
}
