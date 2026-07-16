<?php

namespace Tests\Feature\Leads;

use App\Data\ReportFilterData;
use App\Enums\CampaignLeadAllocation;
use App\Enums\ClosingStatus;
use App\Enums\LeadAllocationMode;
use App\Enums\LeadIngestionStatus;
use App\Enums\LeadPacketType;
use App\Enums\UserRole;
use App\Jobs\Leads\ProcessLeadIngestionJob;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Leads\LeadIngestionService;
use App\Services\Leads\LeadSupplementReviewService;
use App\Services\Operations\SaleOperationStatusService;
use App\Support\LeadContactMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Test tổng hợp toàn bộ quy tắc Landing -> lead -> order -> upsell.
 *
 * Source of truth:
 * - Nhật ký lead: mỗi request là một packet riêng để audit.
 * - Contact/lead thật: chỉ packet counts_as_lead=true và không thuộc nhóm lỗi.
 * - Trong cửa sổ 90 giây: mọi packet bổ sung phải về cùng một order, không chia sale lại.
 * - Sau 90 giây hoặc sale đã tác nghiệp: packet chỉ được đưa vào hàng review.
 * - Chỉ thao tác review chủ động mới được gộp muộn hoặc tạo order mua thêm.
 */
class LandingUpsellCompleteBusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    private int $handledProcessJobs = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-11 10:00:00');

        // Không để delayed job phụ thuộc Redis trong test. Những case cần finalize
        // được gọi service trực tiếp để thời điểm và kết quả hoàn toàn deterministic.
        Queue::fake();

        config()->set('saleops.landing.hold_seconds', 90);
        config()->set('saleops.landing.max_hold_seconds', 90);
        config()->set('saleops.landing.grouping_window_minutes', 15);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_normal_flow_keeps_one_customer_one_order_one_sale_but_preserves_all_packets_for_audit(): void
    {
        $context = $this->createContext(jsTracking: true, salesCount: 2);
        $campaign = $context['campaign'];
        $phone = '0906000001';
        $clientRef = 'client-normal-flow-001';
        $sessionId = 'session-normal-flow-001';

        $this->postBase($campaign, [
            'submission_id' => 'normal-base-001',
            'name' => 'Nguyễn Văn Normal',
            'phone' => $phone,
            'address' => '12 Nguyễn Trãi, Hà Nội',
            'message' => 'Giao giờ hành chính',
            'combo' => 'Mua 1 Thỏi: 149k',
            'saleops_client_ref' => $clientRef,
            'session_id' => $sessionId,
        ]);

        $originalOrder = Order::query()->where('customer_phone', $phone)->firstOrFail();
        $originalSaleId = (int) $originalOrder->sale_user_id;

        // Trang cảm ơn khác domain: không cần gửi lại SĐT, chỉ dùng opaque ref.
        $this->postUpsell($campaign, [
            'submission_id' => 'normal-upsell-001',
            'parent_submission_id' => $clientRef,
            'session_id' => $sessionId,
            'product' => '1 hộp Bàn Chải (8 chiếc): 69k',
            'item_type' => 'upsell',
            'is_upsell' => '1',
        ]);

        // Cấu hình nhầm packet bổ sung vào /receive vẫn phải nhận diện follow-up.
        $this->postBase($campaign, [
            'submission_id' => 'normal-follow-up-001',
            'phone' => $phone,
            'session_id' => $sessionId,
            'mua_them_2' => 'Mua Thêm 1 Kem Thoa Tay: 79K',
        ]);

        $orders = Order::query()->where('customer_phone', $phone)->with('items')->get();
        $this->assertCount(1, $orders, 'Trong 90 giây không được tách thành hai order/hồ sơ.');

        $order = $orders->first();
        $this->assertSame($originalSaleId, (int) $order->sale_user_id, 'Packet bổ sung không được chạy chia sale lần hai.');
        $this->assertCount(3, $order->items);
        $this->assertSame(297_000, (int) $order->total);
        $this->assertSame('Giao giờ hành chính', $order->customer_note);
        $this->assertStringNotContainsString('bàn chải', mb_strtolower((string) $order->customer_note));
        $this->assertStringNotContainsString('kem thoa tay', mb_strtolower((string) $order->customer_note));

        $packets = LeadIngestion::query()
            ->where('customer_phone', $phone)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $packets, 'Nhật ký lead phải giữ đủ ba request để audit.');
        $this->assertSame(1, $packets->where('counts_as_lead', true)->count());
        $this->assertSame(2, $packets->where('counts_as_lead', false)->count());
        $this->assertSame(LeadPacketType::Lead, $packets[0]->packet_type);
        $this->assertSame(LeadPacketType::Upsell, $packets[1]->packet_type);
        $this->assertSame(LeadPacketType::FollowUp, $packets[2]->packet_type);

        foreach ($packets as $packet) {
            $this->assertSame($phone, $packet->customer_phone, 'Packet bổ sung phải kế thừa SĐT từ order gốc.');
            $this->assertSame(
                $order->customer_name,
                $packet->customer_name,
                'Packet bổ sung không có name phải kế thừa đúng tên khách từ order gốc.',
            );
            $this->assertSame($order->id, $packet->order_id);
        }

        $this->assertSame($packets[0]->id, $packets[1]->parent_ingestion_id);
        $this->assertSame($packets[0]->id, $packets[2]->parent_ingestion_id);
        $this->assertSame(1, (int) $campaign->fresh()->contacts);
        $this->assertCanonicalContactCount($campaign, expected: 1);

        // Hồ sơ khách hàng phải chỉ có một row/order; packet thô được giữ ở DB để audit kỹ thuật.
        $this->actingAs($context['sales']->first())
            ->get('/sales/customers?search='.$phone)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/CustomerProfile')
                ->has('report.rows.data', 1)
                ->where('report.rows.meta.total', 1)
                ->where('report.rows.data.0.customerPhone', $phone)
                ->where('report.rows.data.0.saleId', (string) $originalSaleId)
                ->has('report.rows.data.0.products', 3)
            );

        $this->actingAs($context['admin'])
            ->get('/admin/leads')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DataDistribution/Index')
                ->has('products')
                ->has('sales')
            );
    }

    public function test_retries_and_same_submission_reference_are_exactly_once(): void
    {
        $context = $this->createContext();
        $campaign = $context['campaign'];
        $phone = '0906000002';
        $sharedSubmission = 'shared-submission-002';

        $basePayload = [
            'submission_id' => $sharedSubmission,
            'name' => 'Khách Idempotent',
            'phone' => $phone,
            'combo' => 'Mua 1 Thỏi: 149k',
            'saleops_client_ref' => 'client-idempotent-002',
        ];

        $this->postBase($campaign, $basePayload);
        $this->postBase($campaign, $basePayload); // retry base

        $upsellPayload = [
            // Cùng submission_id với form chính vẫn hợp lệ vì packet upsell có hậu tố :upsell.
            'submission_id' => $sharedSubmission,
            'phone' => $phone,
            'mua_them_1' => '1 hộp Bàn Chải (8 chiếc): 69k',
        ];

        $this->postUpsell($campaign, $upsellPayload);
        $this->postUpsell($campaign, $upsellPayload); // retry upsell

        $order = Order::query()->where('customer_phone', $phone)->with('items')->firstOrFail();
        $packets = LeadIngestion::query()->where('customer_phone', $phone)->orderBy('id')->get();

        $this->assertSame(1, Order::query()->where('customer_phone', $phone)->count());
        $this->assertCount(2, $order->items, 'Retry không được cộng item lần hai.');
        $this->assertSame(218_000, (int) $order->total);
        $this->assertCount(2, $packets);
        $this->assertSame(1, $packets->where('counts_as_lead', true)->count());
        $this->assertDatabaseHas('lead_ingestions', ['external_id' => $sharedSubmission]);
        $this->assertDatabaseHas('lead_ingestions', ['external_id' => $sharedSubmission.':upsell']);
        $this->assertCanonicalContactCount($campaign, expected: 1);
    }

    public function test_multiple_unique_upsells_and_duplicate_deliveries_do_not_lose_or_multiply_items(): void
    {
        $context = $this->createContext();
        $campaign = $context['campaign'];
        $phone = '0906000003';

        $this->postBase($campaign, [
            'submission_id' => 'multi-base-003',
            'name' => 'Khách Multi Upsell',
            'phone' => $phone,
            'combo' => 'Mua 1 Thỏi: 149k',
        ]);

        $upsells = [
            [
                'submission_id' => 'multi-up-003-a',
                'phone' => $phone,
                'mua_them_1' => '1 hộp Bàn Chải (8 chiếc): 69k',
            ],
            [
                'submission_id' => 'multi-up-003-b',
                'phone' => $phone,
                'mua_them_1' => 'Mua Thêm 1 Kem Thoa Tay: 79K',
            ],
        ];

        foreach ($upsells as $payload) {
            $this->postUpsell($campaign, $payload);
        }
        foreach (array_reverse($upsells) as $payload) {
            $this->postUpsell($campaign, $payload); // giả lập retry/race delivery
        }

        $order = Order::query()->where('customer_phone', $phone)->with('items')->firstOrFail();
        $this->assertSame(1, Order::query()->where('customer_phone', $phone)->count());
        $this->assertCount(3, $order->items);
        $this->assertSame(297_000, (int) $order->total);
        $this->assertSame(3, LeadIngestion::query()->where('customer_phone', $phone)->count());
        $this->assertSame(1, LeadIngestion::query()->where('customer_phone', $phone)->where('counts_as_lead', true)->count());
    }

    public function test_upsell_arriving_before_base_is_reconciled_without_creating_or_routing_a_standalone_order(): void
    {
        $context = $this->createContext();
        $campaign = $context['campaign'];
        $phone = '0906000004';
        $sessionId = 'session-before-base-004';
        $clientRef = 'client-before-base-004';

        $this->postUpsell($campaign, [
            'submission_id' => 'before-base-upsell-004',
            'phone' => $phone,
            'session_id' => $sessionId,
            'parent_submission_id' => $clientRef,
            'mua_them_1' => '1 hộp Bàn Chải (8 chiếc): 69k',
        ]);

        $pending = LeadIngestion::query()->where('customer_phone', $phone)->firstOrFail();
        $this->assertSame(LeadIngestionStatus::Gathering, $pending->status);
        $this->assertFalse($pending->counts_as_lead);
        $this->assertDatabaseMissing('orders', ['customer_phone' => $phone]);

        $this->postBase($campaign, [
            'submission_id' => 'before-base-main-004',
            'name' => 'Khách Queue Lệch',
            'phone' => $phone,
            'session_id' => $sessionId,
            'saleops_client_ref' => $clientRef,
            'combo' => 'Mua 1 Thỏi: 149k',
        ]);

        $order = Order::query()->where('customer_phone', $phone)->with('items')->firstOrFail();
        $pending->refresh();
        $primary = LeadIngestion::query()
            ->where('customer_phone', $phone)
            ->where('counts_as_lead', true)
            ->firstOrFail();

        $this->assertSame(1, Order::query()->where('customer_phone', $phone)->count());
        $this->assertCount(2, $order->items);
        $this->assertSame(218_000, (int) $order->total);
        $this->assertSame(LeadIngestionStatus::Processed, $pending->status);
        $this->assertFalse($pending->requires_review);
        $this->assertSame($order->id, $pending->order_id);
        $this->assertSame($primary->id, $pending->parent_ingestion_id);
        $this->assertSame(1, LeadIngestion::query()->where('customer_phone', $phone)->where('counts_as_lead', true)->count());
    }

    public function test_orphan_upsell_becomes_review_exception_and_never_creates_customer_order_or_contact(): void
    {
        $context = $this->createContext();
        $campaign = $context['campaign'];
        $phone = '0906000005';

        $this->postUpsell($campaign, [
            'submission_id' => 'orphan-upsell-005',
            'name' => 'Khách Orphan',
            'phone' => $phone,
            'mua_them_1' => 'Mua Thêm 1 Kem Thoa Tay: 79K',
        ]);

        $packet = LeadIngestion::query()->where('customer_phone', $phone)->firstOrFail();
        $this->assertSame(LeadIngestionStatus::Gathering, $packet->status);
        $this->assertFalse($packet->counts_as_lead);
        $this->assertDatabaseMissing('orders', ['customer_phone' => $phone]);

        $packet->forceFill(['created_at' => now()->subSeconds(95)])->save();
        app(LeadIngestionService::class)->resolvePendingSupplementPacket($packet->fresh());

        $packet->refresh();
        $this->assertSame(LeadIngestionStatus::NeedsReview, $packet->status);
        $this->assertSame(LeadPacketType::OrphanUpsell, $packet->packet_type);
        $this->assertTrue($packet->requires_review);
        $this->assertFalse($packet->counts_as_lead);
        $this->assertNull($packet->order_id);
        $this->assertNull($packet->related_order_id);
        $this->assertDatabaseMissing('orders', ['customer_phone' => $phone]);
        $this->assertCanonicalContactCount($campaign, expected: 0);

        $this->actingAs($context['sales']->first())
            ->get('/sales/customers?search='.$phone)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/CustomerProfile')
                ->has('report.rows.data', 0)
                ->where('report.rows.meta.total', 0)
            );
    }

    public function test_late_upsell_after_absolute_90_seconds_is_linked_for_review_without_rerouting_or_auto_merging(): void
    {
        $context = $this->createContext();
        [$order, $packet] = $this->createLateUpsell($context, '006');

        $order->refresh()->load('items');
        $packet->refresh();

        $this->assertCount(1, $order->items);
        $this->assertSame(149_000, (int) $order->total);
        $this->assertSame(1, Order::query()->where('customer_phone', $order->customer_phone)->count());
        $this->assertSame(LeadIngestionStatus::NeedsReview, $packet->status);
        $this->assertSame(LeadPacketType::LateUpsell, $packet->packet_type);
        $this->assertFalse($packet->counts_as_lead);
        $this->assertTrue($packet->requires_review);
        $this->assertNull($packet->order_id);
        $this->assertSame($order->id, $packet->related_order_id);
        $this->assertSame($order->sale_user_id, $packet->relatedOrder?->sale_user_id);
        $this->assertCanonicalContactCount($context['campaign'], expected: 1);

        $this->actingAs($context['sales']->first())
            ->get('/sales/customers?search='.$order->customer_phone)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/CustomerProfile')
                ->has('report.rows.data', 1)
                ->where('report.rows.data.0.pendingSupplementCount', 1)
            );
    }

    public function test_sale_action_locks_order_and_converts_next_upsell_to_review_instead_of_silent_second_order(): void
    {
        $context = $this->createContext();
        $campaign = $context['campaign'];
        $phone = '0906000007';
        $clientRef = 'client-sale-lock-007';

        $this->postBase($campaign, [
            'submission_id' => 'sale-lock-base-007',
            'name' => 'Khách Sale Lock',
            'phone' => $phone,
            'combo' => 'Mua 1 Thỏi: 149k',
            'saleops_client_ref' => $clientRef,
        ]);

        $order = Order::query()->where('customer_phone', $phone)->firstOrFail();
        $sale = User::query()->findOrFail($order->sale_user_id);

        app(SaleOperationStatusService::class)->logCall($order, $sale);
        $order->refresh();

        $this->assertTrue($order->isLandingUpsellLocked());
        $this->assertFalse($order->isAwaitingLandingUpsell());

        $this->postUpsell($campaign, [
            'submission_id' => 'sale-lock-upsell-007',
            'parent_submission_id' => $clientRef,
            'mua_them_1' => '1 hộp Bàn Chải (8 chiếc): 69k',
        ]);

        $packet = LeadIngestion::query()
            ->where('customer_phone', $phone)
            ->where('counts_as_lead', false)
            ->latest('id')
            ->firstOrFail();

        $order->refresh()->load('items');
        $this->assertCount(1, $order->items);
        $this->assertSame(149_000, (int) $order->total);
        $this->assertSame(1, Order::query()->where('customer_phone', $phone)->count());
        $this->assertSame(LeadIngestionStatus::NeedsReview, $packet->status);
        $this->assertSame(LeadPacketType::LateUpsell, $packet->packet_type);
        $this->assertSame($order->id, $packet->related_order_id);
        $this->assertSame($sale->id, $order->sale_user_id);
    }

    public function test_safe_manual_review_merges_late_packet_back_into_original_without_creating_second_profile(): void
    {
        $context = $this->createContext();
        [$order, $packet] = $this->createLateUpsell($context, '008');

        // Mở lại trạng thái nghiệp vụ của đơn để operator có thể quyết định gộp tay.
        $order->forceFill([
            'landing_upsell_hold_until' => null,
            'landing_upsell_locked' => false,
        ])->save();

        app(LeadSupplementReviewService::class)->resolve(
            $packet,
            $context['admin'],
            LeadSupplementReviewService::MERGE_ORIGINAL,
            'Đã xác minh đây là sản phẩm mua thêm của đơn gốc.',
        );

        $order->refresh()->load('items');
        $packet->refresh();

        $this->assertSame(1, Order::query()->where('customer_phone', $order->customer_phone)->count());
        $this->assertCount(2, $order->items);
        $this->assertSame(218_000, (int) $order->total);
        $this->assertSame(LeadIngestionStatus::Processed, $packet->status);
        $this->assertSame(LeadPacketType::Upsell, $packet->packet_type);
        $this->assertSame($order->id, $packet->order_id);
        $this->assertNull($packet->related_order_id);
        $this->assertFalse($packet->requires_review);
        $this->assertFalse($packet->counts_as_lead);
        $this->assertSame(LeadSupplementReviewService::MERGE_ORIGINAL, $packet->review_resolution);
        $this->assertCanonicalContactCount($context['campaign'], expected: 1);

        $this->actingAs($context['sales']->first())
            ->get('/sales/customers?search='.$order->customer_phone)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/CustomerProfile')
                ->has('report.rows.data', 1)
                ->has('report.rows.data.0.products', 2)
            );
    }

    public function test_unsafe_closed_order_can_only_create_explicit_supplemental_order_with_same_sale_and_no_extra_contact(): void
    {
        $context = $this->createContext();
        [$original, $packet] = $this->createLateUpsell($context, '009');

        $original->forceFill([
            'closing_status' => ClosingStatus::Closed->value,
            'closed_at' => now(),
            'tracking_number' => 'TRACK-009',
        ])->save();

        $service = app(LeadSupplementReviewService::class);

        try {
            $service->resolve(
                $packet,
                $context['admin'],
                LeadSupplementReviewService::MERGE_ORIGINAL,
            );
            $this->fail('Đơn đã chốt/có vận đơn không được phép bị sửa item bằng merge_original.');
        } catch (ValidationException) {
            $this->assertNull($packet->fresh()->reviewed_at);
        }

        $service->resolve(
            $packet->fresh(),
            $context['admin'],
            LeadSupplementReviewService::CREATE_SUPPLEMENTAL_ORDER,
            'Đơn gốc đã chốt nên tạo đơn mua thêm có liên kết.',
        );

        $packet->refresh();
        $newOrder = Order::query()
            ->whereKey($packet->order_id)
            ->with(['items', 'supplementalOriginPacket.relatedOrder'])
            ->firstOrFail();

        $this->assertSame(2, Order::query()->where('customer_phone', $original->customer_phone)->count());
        $this->assertNotSame($original->id, $newOrder->id);
        $this->assertSame($original->id, $packet->related_order_id);
        $this->assertSame($original->sale_user_id, $newOrder->sale_user_id);
        $this->assertSame($original->team_id, $newOrder->team_id);
        $this->assertSame($original->marketing_source_id, $newOrder->marketing_source_id);
        $this->assertSame($original->customer_name, $newOrder->customer_name);
        $this->assertSame($original->customer_phone, $newOrder->customer_phone);
        $this->assertTrue($newOrder->is_returning_customer);
        $this->assertCount(1, $newOrder->items);
        $this->assertSame(69_000, (int) $newOrder->total);
        $this->assertFalse($packet->counts_as_lead);
        $this->assertSame(LeadSupplementReviewService::CREATE_SUPPLEMENTAL_ORDER, $packet->review_resolution);
        $this->assertSame($original->order_code, $newOrder->supplementalOriginPacket?->relatedOrder?->order_code);
        $this->assertCanonicalContactCount($context['campaign'], expected: 1);

        // Hai row ở Hồ sơ khách hàng trong case này là CÓ CHỦ ĐÍCH, không phải lỗi
        // gộp: row thứ hai phải mang badge/link về mã đơn gốc.
        $this->actingAs($context['sales']->first())
            ->get('/sales/customers?search='.$original->customer_phone)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/CustomerProfile')
                ->has('report.rows.data', 2)
                ->where('report.rows.data', function ($rows) use ($original): bool {
                    $rows = collect($rows);
                    $supplemental = $rows->firstWhere('isSupplementalOrder', true);

                    return $supplemental !== null
                        && ($supplemental['supplementalOriginalOrderCode'] ?? null) === $original->order_code
                        && (string) ($supplemental['saleId'] ?? '') === (string) $original->sale_user_id;
                })
            );
    }

    public function test_duplicate_new_base_after_window_is_audited_but_does_not_create_second_order_or_contact(): void
    {
        $context = $this->createContext();
        $campaign = $context['campaign'];
        $phone = '0906000010';

        $this->postBase($campaign, [
            'submission_id' => 'duplicate-base-010-a',
            'name' => 'Khách Duplicate',
            'phone' => $phone,
            'combo' => 'Mua 1 Thỏi: 149k',
        ]);

        $order = Order::query()->where('customer_phone', $phone)->firstOrFail();
        $order->forceFill([
            'created_at' => now()->subHour(),
            'landing_upsell_hold_until' => null,
        ])->save();

        $this->postBase($campaign, [
            'submission_id' => 'duplicate-base-010-b',
            'name' => 'Khách Duplicate',
            'phone' => $phone,
            'combo' => 'Mua 2 Thỏi: 289k',
        ]);

        $duplicate = LeadIngestion::query()
            ->where('customer_phone', $phone)
            ->where('status', LeadIngestionStatus::Duplicate)
            ->firstOrFail();

        $this->assertSame(1, Order::query()->where('customer_phone', $phone)->count());
        $this->assertNotEmpty($duplicate->error_message);
        $this->assertSame($order->order_code, $duplicate->payload['conflict_order_code'] ?? null);
        $this->assertCanonicalContactCount($campaign, expected: 1);
    }

    public function test_session_ping_and_close_cannot_extend_or_shorten_absolute_order_deadline(): void
    {
        $context = $this->createContext(jsTracking: true);
        $campaign = $context['campaign'];
        $phone = '0906000011';
        $sessionId = 'absolute-session-011';

        $this->postBase($campaign, [
            'submission_id' => 'absolute-base-011',
            'name' => 'Khách Absolute Window',
            'phone' => $phone,
            'combo' => 'Mua 1 Thỏi: 149k',
            'session_id' => $sessionId,
        ]);

        $order = Order::query()->where('customer_phone', $phone)->firstOrFail();
        $originalDeadline = $order->landing_upsell_hold_until?->copy();
        $this->assertNotNull($originalDeadline);

        Carbon::setTestNow(now()->addSeconds(30));

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/session/ping', [
            'session_id' => $sessionId,
        ])->assertAccepted();

        $order->refresh();
        $this->assertTrue($order->landing_upsell_hold_until?->equalTo($originalDeadline));

        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/session/close', [
            'session_id' => $sessionId,
        ])->assertAccepted();

        $order->refresh();
        $this->assertTrue($order->landing_upsell_hold_until?->equalTo($originalDeadline));
        $this->assertTrue($order->isAwaitingLandingUpsell());

        Carbon::setTestNow($originalDeadline->copy()->addSecond());
        app(LeadIngestionService::class)->releaseLandingUpsellHold(
            LeadIngestion::query()->where('customer_phone', $phone)->where('counts_as_lead', true)->firstOrFail(),
        );

        $this->assertFalse($order->fresh()->isAwaitingLandingUpsell());
    }

    public function test_review_permissions_follow_role_and_order_ownership(): void
    {
        $context = $this->createContext(salesCount: 2);
        [$order, $packet] = $this->createLateUpsell($context, '012');
        $assignedSale = User::query()->findOrFail($order->sale_user_id);
        $unrelatedSale = $context['sales']->first(fn (User $sale): bool => $sale->id !== $assignedSale->id);
        $allocator = User::factory()->create(['role' => UserRole::Allocator]);
        $warehouse = User::factory()->create(['role' => UserRole::Warehouse]);
        $marketing = User::factory()->create(['role' => UserRole::Marketing]);

        $service = app(LeadSupplementReviewService::class);

        $this->assertTrue($service->canReview($context['admin'], $packet, $order));
        $this->assertTrue($service->canReview($allocator, $packet, $order));
        $this->assertTrue($service->canReview($assignedSale, $packet, $order));
        $this->assertNotNull($unrelatedSale);
        $this->assertFalse($service->canReview($unrelatedSale, $packet, $order));
        $this->assertFalse($service->canReview($warehouse, $packet, $order));
        $this->assertFalse($service->canReview($marketing, $packet, $order));
    }

    public function test_upsell_without_phone_session_or_parent_reference_is_rejected(): void
    {
        $context = $this->createContext();

        $this->postJson('/api/v1/landing/'.$context['campaign']->webhook_token.'/upsell', [
            'submission_id' => 'invalid-orphan-013',
            'mua_them_1' => '1 hộp Bàn Chải (8 chiếc): 69k',
        ])->assertUnprocessable();

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, LeadIngestion::query()->count(), 'Request bị chặn ở validation, chưa được tạo packet nghiệp vụ.');
        $this->assertSame(0, LeadContactMetrics::countToday());
    }

    /**
     * @return array{
     *     admin: User,
     *     marketer: User,
     *     sales: Collection<int, User>,
     *     campaign: MarketingSource
     * }
     */
    private function createContext(bool $jsTracking = false, int $salesCount = 1): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $sales = collect();

        foreach (range(1, max(1, $salesCount)) as $index) {
            $sales->push(User::factory()->create([
                'role' => UserRole::Sales,
                'name' => 'Sale Test '.$index,
            ]));
        }

        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Auto);

        $campaign = MarketingSource::query()->create([
            'name' => 'Complete Landing Flow Campaign',
            'utm_source' => 'ladipage',
            'utm_campaign' => 'complete-flow-campaign',
            'webhook_token' => 'completeflowtoken1234567890123456',
            'created_by_user_id' => $marketer->id,
            'marketer_user_id' => $marketer->id,
            'is_active' => true,
            'is_approved' => true,
            'js_tracking_enabled' => $jsTracking,
            'lead_allocation' => CampaignLeadAllocation::Auto,
        ]);

        return compact('admin', 'marketer', 'sales', 'campaign');
    }

    /** @param array<string, mixed> $payload */
    private function postBase(MarketingSource $campaign, array $payload): void
    {
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/receive', $payload)
            ->assertAccepted();

        $this->runNextProcessLeadJob();
    }

    /** @param array<string, mixed> $payload */
    private function postUpsell(MarketingSource $campaign, array $payload): void
    {
        $this->postJson('/api/v1/landing/'.$campaign->webhook_token.'/upsell', $payload)
            ->assertAccepted();

        $this->runNextProcessLeadJob();
    }


    /** Thực thi đồng bộ đúng job intake vừa được endpoint HTTP đưa vào queue fake. */
    private function runNextProcessLeadJob(): void
    {
        $records = Queue::pushed(ProcessLeadIngestionJob::class)->values();
        $record = $records->get($this->handledProcessJobs);

        $this->assertNotNull($record, 'Endpoint phải dispatch ProcessLeadIngestionJob.');
        $this->handledProcessJobs++;

        $job = is_array($record) ? ($record['job'] ?? null) : $record;
        $this->assertInstanceOf(ProcessLeadIngestionJob::class, $job);

        app()->call([$job, 'handle']);
    }

    /**
     * Tạo base order, hết cửa sổ 90 giây rồi gửi một packet upsell có ref tường minh.
     *
     * @param array{admin: User, marketer: User, sales: Collection<int, User>, campaign: MarketingSource} $context
     * @return array{Order, LeadIngestion}
     */
    private function createLateUpsell(array $context, string $suffix): array
    {
        $campaign = $context['campaign'];
        $phone = '0907'.str_pad($suffix, 6, '0', STR_PAD_LEFT);
        $clientRef = 'client-late-'.$suffix;

        $this->postBase($campaign, [
            'submission_id' => 'late-base-'.$suffix,
            'name' => 'Khách Late '.$suffix,
            'phone' => $phone,
            'address' => 'Địa chỉ late '.$suffix,
            'combo' => 'Mua 1 Thỏi: 149k',
            'saleops_client_ref' => $clientRef,
        ]);

        $order = Order::query()->where('customer_phone', $phone)->firstOrFail();
        $order->forceFill([
            'created_at' => now()->subSeconds(120),
            'landing_upsell_hold_until' => now()->subSecond(),
        ])->save();

        $this->postUpsell($campaign, [
            'submission_id' => 'late-upsell-'.$suffix,
            'parent_submission_id' => $clientRef,
            'mua_them_1' => '1 hộp Bàn Chải (8 chiếc): 69k',
        ]);

        $packet = LeadIngestion::query()
            ->where('customer_phone', $phone)
            ->where('counts_as_lead', false)
            ->latest('id')
            ->firstOrFail();

        return [$order->fresh(), $packet];
    }

    private function assertCanonicalContactCount(MarketingSource $campaign, int $expected): void
    {
        $filter = new ReportFilterData(
            dateFrom: now()->copy()->startOfDay(),
            dateTo: now()->copy()->endOfDay(),
        );

        $this->assertSame($expected, LeadContactMetrics::countToday());
        $this->assertSame($expected, (int) LeadContactMetrics::countsBySource($filter)->get($campaign->id, 0));
        $this->assertSame($expected, (int) LeadContactMetrics::countsByMarketer($filter)->get($campaign->marketer_user_id, 0));
    }
}
