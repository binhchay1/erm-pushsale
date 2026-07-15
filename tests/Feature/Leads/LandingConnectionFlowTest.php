<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadAllocationMode;
use App\Enums\UserRole;
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

class LandingConnectionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-14 08:00:00');
        config()->set('saleops.landing.hold_seconds', 90);
        config()->set('saleops.landing.max_hold_seconds', 90);
        app(LeadAllocationModeService::class)->set(LeadAllocationMode::Auto);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_main_and_upsell_sources_create_one_authoritative_order_and_keep_one_sale(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $selectedSale = User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sale được chọn']);
        User::factory()->create(['role' => UserRole::Sales, 'name' => 'Sale không thuộc kết nối']);

        $baseProduct = Product::query()->create([
            'name' => 'Gói chính backend',
            'sku' => 'LC-BASE',
            'unit_price' => 149_000,
            'cost_price' => 80_000,
            'is_active' => true,
            'available_marketing' => true,
        ]);
        $alternateProduct = Product::query()->create([
            'name' => 'Gói nâng cao backend',
            'sku' => 'LC-ALT',
            'unit_price' => 199_000,
            'cost_price' => 110_000,
            'is_active' => true,
            'available_marketing' => true,
        ]);
        $upsellProduct = Product::query()->create([
            'name' => 'Gói upsale backend',
            'sku' => 'LC-UP',
            'unit_price' => 69_000,
            'cost_price' => 30_000,
            'is_active' => true,
            'available_marketing' => true,
        ]);

        $connection = app(LandingConnectionManager::class)->create([
            'name' => 'Luồng Landing khép kín',
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'facebook',
            'allocation_method' => 'round_robin',
            'success_url' => 'https://landing.example/cam-on',
            'manual_import' => false,
            'is_approved' => true,
            'is_active' => true,
            'sources' => [
                [
                    'client_key' => 'main',
                    'name' => 'Landing chính',
                    'source_type' => 'main',
                    'source_url' => 'https://landing.example/main',
                    'redirect_url' => 'https://landing.example/upsale',
                    'is_active' => true,
                ],
                [
                    'client_key' => 'upsell',
                    'name' => 'Trang upsale',
                    'source_type' => 'upsell',
                    'source_url' => 'https://landing.example/upsale',
                    'redirect_url' => 'https://landing.example/cam-on',
                    'is_active' => true,
                ],
            ],
            'products' => [
                [
                    'product_id' => $baseProduct->id,
                    'source_key' => 'main',
                    'item_type' => 'product',
                    'external_field' => 'package',
                    'external_value' => 'package-base',
                    'quantity' => 1,
                    'unit_price_override' => null,
                    'is_default' => true,
                ],
                [
                    'product_id' => $alternateProduct->id,
                    'source_key' => 'main',
                    'item_type' => 'product',
                    'external_field' => 'package',
                    'external_value' => 'package-alt|Gói nâng cao',
                    'quantity' => 1,
                    'unit_price_override' => null,
                    'is_default' => false,
                ],
                [
                    'product_id' => $upsellProduct->id,
                    'source_key' => 'upsell',
                    'item_type' => 'upsell',
                    'quantity' => 1,
                    'unit_price_override' => null,
                    'is_default' => false,
                ],
            ],
            'sale_user_ids' => [$selectedSale->id],
        ], $admin);

        $main = $connection->sources->firstWhere('source_type', 'main');
        $upsell = $connection->sources->firstWhere('source_type', 'upsell');

        $mainResponse = $this->postJson($this->submitPath($connection, $main->public_token), [
            'submission_id' => 'vendor-shared-id-001',
            'name' => 'Khách Landing Connection',
            'phone' => '0909000001',
            'address' => 'Hà Nội',
            'price' => 1,
            'discount' => 999_999,
            'shipping_fee' => 888_888,
            'items' => [[
                'name' => 'Sản phẩm giả từ client',
                'price' => 1,
                'quantity' => 999,
            ]],
            'fields' => [
                ['name' => 'package', 'value' => 'Gói nâng cao'],
                ['name' => 'discount', 'value' => '999k'],
                ['name' => 'shipping_fee', 'value' => '888k'],
            ],
        ])->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('lead_id')
            ->assertJsonMissingPath('order_id');

        $flowToken = (string) $mainResponse->json('flow_token');
        $this->assertNotSame('', $flowToken);
        $this->assertStringContainsString('ps_flow=', (string) $mainResponse->json('redirect_url'));

        $otherConnection = app(LandingConnectionManager::class)->create([
            'name' => 'Kết nối khác cùng tenant',
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'allocation_method' => 'round_robin',
            'is_approved' => true,
            'is_active' => true,
            'sources' => [
                ['client_key' => 'other-main', 'name' => 'Other main', 'source_type' => 'main', 'source_url' => 'https://other.example/main', 'is_active' => true],
                ['client_key' => 'other-up', 'name' => 'Other upsell', 'source_type' => 'upsell', 'source_url' => 'https://other.example/up', 'is_active' => true],
            ],
            'products' => [
                ['product_id' => $baseProduct->id, 'source_key' => 'other-main', 'item_type' => 'product', 'quantity' => 1, 'is_default' => true],
                ['product_id' => $upsellProduct->id, 'source_key' => 'other-up', 'item_type' => 'upsell', 'quantity' => 1, 'is_default' => true],
            ],
            'sale_user_ids' => [$selectedSale->id],
        ], $admin);
        $otherUpsell = $otherConnection->sources->firstWhere('source_type', 'upsell');

        $this->postJson($this->submitPath($otherConnection, $otherUpsell->public_token), [
            'submission_id' => 'cross-connection-attempt',
            'ps_flow' => $flowToken,
        ])->assertStatus(409)->assertJsonPath('ok', false);

        $order = Order::query()->where('customer_phone', '0909000001')->with('items')->firstOrFail();
        $this->assertSame($selectedSale->id, (int) $order->sale_user_id);
        $this->assertSame($connection->id, (int) $order->landing_connection_id);
        $this->assertSame($main->id, (int) $order->landing_connection_source_id);
        $this->assertCount(1, $order->items);
        $this->assertSame($alternateProduct->id, (int) $order->items->first()->product_id);
        $this->assertSame(110_000, (int) $order->items->first()->cost_price, 'Giá vốn phải được chụp từ catalog backend.');
        $this->assertSame(199_000, (int) $order->total);
        $this->assertSame(0, (int) $order->discount);
        $this->assertSame(0, (int) $order->shipping_fee_collected);

        $this->postJson($this->submitPath($connection, $upsell->public_token), [
            // Cùng id nhà cung cấp với form chính vẫn không xung đột vì được namespace theo source.
            'submission_id' => 'vendor-shared-id-001',
            'ps_flow' => $flowToken,
            'price' => 1,
            'discount' => 777_777,
        ])->assertCreated()->assertJsonPath('ok', true);

        // Retry đúng cùng source + submission id phải idempotent.
        $this->postJson($this->submitPath($connection, $upsell->public_token), [
            'submission_id' => 'vendor-shared-id-001',
            'ps_flow' => $flowToken,
        ])->assertCreated()->assertJsonPath('ok', true);

        $orders = Order::query()->where('customer_phone', '0909000001')->with('items')->get();
        $this->assertCount(1, $orders);

        $order = $orders->first();
        $this->assertSame($selectedSale->id, (int) $order->sale_user_id, 'Upsale không được chia Sale lần hai.');
        $this->assertSame($main->id, (int) $order->landing_connection_source_id, 'Đơn giữ nguồn vào chính.');
        $this->assertCount(2, $order->items, 'Retry upsale không được cộng hàng lần hai.');
        $this->assertSame(140_000, (int) $order->items->sum('cost_price'), 'Giá vốn đơn phải bao gồm cả sản phẩm upsale.');
        $this->assertSame(268_000, (int) $order->total);

        $packets = LeadIngestion::query()->where('landing_connection_id', $connection->id)->orderBy('id')->get();
        $this->assertCount(2, $packets);
        $this->assertSame(1, $packets->where('counts_as_lead', true)->count());
        $this->assertSame($main->id, (int) $packets[0]->landing_connection_source_id);
        $this->assertSame($upsell->id, (int) $packets[1]->landing_connection_source_id);
        $this->assertSame($order->id, (int) $packets[0]->order_id);
        $this->assertSame($order->id, (int) $packets[1]->order_id);

        $upsell->delete();
        $this->postJson($this->submitPath($connection, $upsell->public_token), [
            'submission_id' => 'retired-source-attempt',
            'ps_flow' => $flowToken,
        ])->assertNotFound();
        $this->assertSame($upsell->id, $packets[1]->fresh()->landingConnectionSource?->id);
    }

    public function test_thank_you_source_is_registry_only_and_cannot_receive_form_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $marketer = User::factory()->create(['role' => UserRole::Marketing]);
        $product = Product::query()->create([
            'name' => 'Sản phẩm test',
            'sku' => 'LC-THANKS',
            'unit_price' => 100_000,
            'is_active' => true,
            'available_marketing' => true,
        ]);

        $connection = app(LandingConnectionManager::class)->create([
            'name' => 'Kết nối có trang cảm ơn',
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'allocation_method' => 'manual',
            'is_approved' => true,
            'is_active' => true,
            'sources' => [
                ['client_key' => 'main', 'name' => 'Main', 'source_type' => 'main', 'source_url' => 'https://landing.example/main', 'is_active' => true],
                ['client_key' => 'thanks', 'name' => 'Thanks', 'source_type' => 'thank_you', 'source_url' => 'https://landing.example/thanks', 'is_active' => true],
            ],
            'products' => [[
                'product_id' => $product->id,
                'source_key' => 'main',
                'item_type' => 'product',
                'quantity' => 1,
                'is_default' => true,
            ]],
            'sale_user_ids' => [],
        ], $admin);

        $thanks = $connection->sources->firstWhere('source_type', 'thank_you');

        $this->postJson($this->submitPath($connection, $thanks->public_token), [
            'phone' => '0909000002',
        ])->assertStatus(405)->assertJsonPath('ok', false);

        $this->assertDatabaseCount('lead_ingestions', 0);
        $this->assertDatabaseCount('orders', 0);
    }

    private function submitPath(LandingConnection $connection, string $sourceToken): string
    {
        return '/api/v1/landing-connections/'.$connection->public_token.'/sources/'.$sourceToken.'/submit';
    }
}
