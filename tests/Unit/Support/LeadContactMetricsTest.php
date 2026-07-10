<?php

namespace Tests\Unit\Support;

use App\Data\ReportFilterData;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\UserRole;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Support\LeadContactMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeadContactMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_only_primary_contacts_not_duplicate_or_upsell_audit(): void
    {
        Carbon::setTestNow('2026-07-07 10:00:00');

        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $source = MarketingSource::query()->create([
            'name' => 'Test campaign',
            'marketer_user_id' => $marketer->id,
            'webhook_token' => 'tok-metrics',
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'order_code' => 'ORD-M1',
            'marketer_user_id' => $marketer->id,
            'marketing_source_id' => $source->id,
            'customer_name' => 'Test',
            'customer_phone' => '0900111222',
            'data_arrived_at' => now(),
            'total' => 100_000,
        ]);

        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'sub-1',
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Lead,
            'counts_as_lead' => true,
            'customer_phone' => '0900111222',
            'marketing_source_id' => $source->id,
            'order_id' => $order->id,
            'payload' => [],
            'processed_at' => now(),
        ]);

        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'sub-1:upsell',
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::Upsell,
            'counts_as_lead' => false,
            'customer_phone' => '0900111222',
            'marketing_source_id' => $source->id,
            'order_id' => $order->id,
            'payload' => [],
            'processed_at' => now(),
        ]);

        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'sub-dup',
            'status' => LeadIngestionStatus::Duplicate,
            'packet_type' => LeadPacketType::Lead,
            'counts_as_lead' => true,
            'customer_phone' => '0900333444',
            'marketing_source_id' => $source->id,
            'payload' => [],
        ]);

        // Follow-up bắn lại /receive không có hậu tố :upsell vẫn không được tính lead.
        LeadIngestion::query()->create([
            'platform' => 'landing',
            'external_id' => 'sub-follow-up-no-suffix',
            'status' => LeadIngestionStatus::Processed,
            'packet_type' => LeadPacketType::FollowUp,
            'counts_as_lead' => false,
            'customer_phone' => '0900111222',
            'marketing_source_id' => $source->id,
            'order_id' => $order->id,
            'payload' => [],
            'processed_at' => now(),
        ]);

        $filter = new ReportFilterData(
            dateFrom: now()->startOfDay(),
            dateTo: now()->endOfDay(),
        );

        $this->assertSame(1, LeadContactMetrics::countsByMarketer($filter)->get($marketer->id));
        $this->assertSame(1, LeadContactMetrics::countsBySource($filter)->get($source->id));
        $this->assertSame(1, LeadContactMetrics::countToday($marketer->id));
    }
}
