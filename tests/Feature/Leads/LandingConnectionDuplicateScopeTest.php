<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadAllocationMode;
use App\Enums\LeadIngestionStatus;
use App\Enums\UserRole;
use App\Models\CustomerPhoneLock;
use App\Models\LandingConnection;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Leads\LeadAllocationModeService;
use App\Services\Marketing\LandingConnectionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LandingConnectionDuplicateScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-16 09:00:00');
        config()->set('saleops.landing.hold_seconds', 90);
        config()->set('saleops.landing.max_hold_seconds', 90);
        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Auto);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_same_phone_on_two_landing_connections_creates_two_real_orders_not_duplicate_leads(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $product = $this->product('SP-DUP-A');

        $first = $this->connection($admin, $marketer, $sale, $product, 'Landing A');
        $second = $this->connection($admin, $marketer, $sale, $product, 'Landing B');

        $this->postJson($this->submitPath($first), [
            'submission_id' => 'first-submit',
            'name' => 'Khách A',
            'phone' => '0911111111',
        ])->assertCreated()->assertJsonPath('ok', true);

        $this->postJson($this->submitPath($second), [
            'submission_id' => 'second-submit',
            'name' => 'Khách A nguồn khác',
            'phone' => '0911111111',
        ])->assertCreated()->assertJsonPath('ok', true);

        $this->assertSame(2, Order::query()->where('customer_phone', '0911111111')->count());
        $this->assertSame(2, LeadIngestion::query()->where('customer_phone', '0911111111')->where('counts_as_lead', true)->count());
        $this->assertSame(0, LeadIngestion::query()->where('customer_phone', '0911111111')->where('status', LeadIngestionStatus::Duplicate)->count());
    }


    public function test_same_phone_on_two_connections_keeps_two_orders_but_same_sale_owner(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $saleA = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sale A']);
        $saleB = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sale B']);
        $product = $this->product('SP-PHONE-LOCK');

        $first = $this->connection($admin, $marketer, $saleA, $product, 'Landing owner A');
        $second = $this->connection($admin, $marketer, $saleB, $product, 'Landing owner B');

        $this->postJson($this->submitPath($first), [
            'submission_id' => 'owner-first-submit',
            'name' => 'Khách chung số',
            'phone' => '0933333333',
        ])->assertCreated()->assertJsonPath('ok', true);

        $this->postJson($this->submitPath($second), [
            'submission_id' => 'owner-second-submit',
            'name' => 'Khách chung số nguồn khác',
            'phone' => '0933333333',
        ])->assertCreated()->assertJsonPath('ok', true);

        $orders = Order::query()->where('customer_phone', '0933333333')->orderBy('id')->get();

        $this->assertCount(2, $orders);
        $this->assertSame($saleA->id, (int) $orders[0]->sale_user_id);
        $this->assertSame($saleA->id, (int) $orders[1]->sale_user_id);
        $this->assertTrue((bool) $orders[1]->phone_lock_conflict);

        $lock = CustomerPhoneLock::query()->where('phone_key', '0933333333')->firstOrFail();
        $this->assertSame($saleA->id, (int) $lock->owner_sale_user_id);
        $this->assertSame($orders[1]->id, (int) $lock->active_order_id);
    }

    public function test_same_phone_re_submit_on_same_landing_connection_is_duplicate_and_does_not_create_second_order(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $sale = User::factory()->create(['role' => UserRole::Sales]);
        $product = $this->product('SP-DUP-B');
        $connection = $this->connection($admin, $marketer, $sale, $product, 'Landing duplicate scope');

        $this->postJson($this->submitPath($connection), [
            'submission_id' => 'first-submit',
            'name' => 'Khách duplicate',
            'phone' => '0922222222',
        ])->assertCreated()->assertJsonPath('ok', true);

        $this->postJson($this->submitPath($connection), [
            'submission_id' => 'second-submit-new-vendor-id',
            'name' => 'Khách duplicate lần 2',
            'phone' => '0922222222',
        ])->assertCreated()->assertJsonPath('ok', true)->assertJsonPath('requires_review', true);

        $this->assertSame(1, Order::query()->where('customer_phone', '0922222222')->count());
        $this->assertSame(1, LeadIngestion::query()->where('customer_phone', '0922222222')->where('counts_as_lead', true)->count());

        $duplicate = LeadIngestion::query()
            ->where('customer_phone', '0922222222')
            ->where('status', LeadIngestionStatus::Duplicate)
            ->firstOrFail();

        $this->assertFalse((bool) $duplicate->counts_as_lead);
        $this->assertTrue((bool) $duplicate->requires_review);
        $this->assertSame($connection->id, (int) $duplicate->landing_connection_id);
    }

    private function product(string $sku): Product
    {
        return Product::query()->create([
            'name' => 'Sản phẩm '.$sku,
            'sku' => $sku,
            'unit_price' => 120_000,
            'cost_price' => 60_000,
            'is_active' => true,
            'available_marketing' => true,
        ]);
    }

    private function connection(User $admin, User $marketer, User $sale, Product $product, string $name): LandingConnection
    {
        return app(LandingConnectionManager::class)->create([
            'name' => $name,
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'facebook',
            'allocation_method' => 'round_robin',
            'is_approved' => true,
            'is_active' => true,
            'budget_type' => 'total',
            'budget_amount' => 1_000_000,
            'sources' => [
                ['client_key' => 'main', 'name' => 'Landing chính', 'source_type' => 'main', 'source_url' => 'https://landing.example/'.$name, 'is_active' => true],
            ],
            'products' => [
                ['product_id' => $product->id, 'source_key' => 'main', 'item_type' => 'product', 'quantity' => 1, 'is_default' => true],
            ],
            'sale_user_ids' => [$sale->id],
        ], $admin);
    }

    private function submitPath(LandingConnection $connection): string
    {
        $main = $connection->sources->firstWhere('source_type', 'main');

        return '/api/v1/landing-connections/'.$connection->public_token.'/sources/'.$main->public_token.'/submit';
    }
}
