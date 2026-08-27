<?php

namespace Tests\Feature\Shipping;

use App\Enums\DeliveryStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingPartnerConnection;
use App\Services\Shipping\CarrierRegistry;
use App\Services\Shipping\CreateShipmentService;
use App\Services\Shipping\Gateways\NetShip\NetShipProxyCarrier;
use App\Services\Shipping\ShippingWebhookService;
use App\Support\ShippingProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NetShipGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shipping_partners.providers.viettel_post.fields.token.default' => null,
            'shipping_partners.providers.viettel_post.fields.customer_code.default' => null,
            'shipping_partners.providers.viettel_post.fields.username.default' => null,
            'shipping_partners.providers.viettel_post.fields.password.default' => null,
        ]);

        ShippingPartnerConnection::forProvider('netship')->update([
            'is_enabled' => true,
            'integration_mode' => 'gateway',
            'credentials' => [
                'token' => 'test-netship-token',
                'base_url' => 'https://test.netship.vn',
            ],
        ]);

        ShippingPartnerConnection::forProvider('viettel_post')->update([
            'is_enabled' => false,
            'credentials' => [],
        ]);
    }

    public function test_netship_is_excluded_from_selectable_providers(): void
    {
        $this->assertTrue(ShippingProviders::isGateway('netship'));
        $this->assertArrayNotHasKey('netship', ShippingProviders::selectableProviders());
        $this->assertFalse(collect(ShippingProviders::options())->contains(fn ($o) => $o['value'] === 'netship'));
    }

    public function test_registry_routes_to_netship_when_direct_carrier_not_ready(): void
    {
        $carrier = app(CarrierRegistry::class)->get('viettel_post');

        $this->assertInstanceOf(NetShipProxyCarrier::class, $carrier);
        $this->assertSame('viettel_post', $carrier->provider());
        $this->assertTrue($carrier->isReady());
    }

    public function test_registry_keeps_direct_when_carrier_ready(): void
    {
        ShippingPartnerConnection::forProvider('viettel_post')->update([
            'is_enabled' => true,
            'credentials' => [
                'token' => 'vtp-token',
                'customer_code' => 'VTP-CUST',
            ],
        ]);

        $carrier = app(CarrierRegistry::class)->get('viettel_post');

        $this->assertNotInstanceOf(NetShipProxyCarrier::class, $carrier);
        $this->assertSame('viettel_post', $carrier->provider());
    }

    public function test_create_shipment_via_netship_stores_business_provider(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/api/address/provinces')) {
                return Http::response([['id' => 1, 'name' => 'Hà Nội']], 200);
            }
            if (str_contains($url, '/api/address/districts')) {
                return Http::response([['id' => 10, 'name' => 'Quận Cầu Giấy']], 200);
            }
            if (str_contains($url, '/api/address/ward')) {
                return Http::response([['id' => 100, 'name' => 'Phường Dịch Vọng']], 200);
            }
            if (str_contains($url, '/api/third-party/order') && $request->method() === 'POST') {
                return Http::response([
                    'success' => true,
                    'data' => ['id' => 987654, 'tracking_number' => 'NS987654', 'fee' => 25000],
                ], 200);
            }

            return Http::response(['success' => false, 'message' => 'unexpected '.$url], 500);
        });

        config([
            'shipping_partners.pickup.province' => 'Hà Nội',
            'shipping_partners.pickup.district' => 'Quận Cầu Giấy',
            'shipping_partners.pickup.ward' => 'Phường Dịch Vọng',
            'shipping_partners.default_geo.province' => 'Hà Nội',
            'shipping_partners.default_geo.district' => 'Quận Cầu Giấy',
            'shipping_partners.default_geo.ward' => 'Phường Dịch Vọng',
        ]);

        $order = Order::query()->create([
            'order_code' => 'NS-ORDER-001',
            'customer_name' => 'Khách NetShip',
            'customer_phone' => '0901234567',
            'receiver_name' => 'Khách NetShip',
            'receiver_phone' => '0901234567',
            'shipping_address' => '1 Nguyễn Huệ',
            'shipping_provider' => 'viettel_post',
            'shipping_geo' => [
                'province' => 'Hà Nội',
                'district' => 'Quận Cầu Giấy',
                'ward' => 'Phường Dịch Vọng',
            ],
            'closed_at' => now(),
            'total' => 150_000,
            'amount_to_collect' => 150_000,
        ]);

        $shipment = app(CreateShipmentService::class)->createForOrder($order, 'viettel_post');

        $this->assertSame('viettel_post', $shipment->provider);
        $this->assertSame('netship', $shipment->response_payload['gateway'] ?? null);
        $this->assertSame('987654', (string) ($shipment->response_payload['netship_order_id'] ?? ''));
        $this->assertSame('NS-ORDER-001', $shipment->partner_order_id);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/third-party/order')
            && $request->method() === 'POST'
            && ($request['carrierCode'] ?? null) === 'VTP'
            && ($request['customerCode'] ?? null) === 'NS-ORDER-001');
    }

    public function test_webhook_matches_by_customer_code_without_overwriting_business_provider(): void
    {
        $order = Order::query()->create([
            'order_code' => 'NS-WH-001',
            'customer_name' => 'Webhook KH',
            'customer_phone' => '0909999888',
            'shipping_provider' => 'viettel_post',
            'tracking_number' => 'NS111',
            'closed_at' => now(),
            'total' => 200_000,
            'amount_to_collect' => 200_000,
        ]);

        Shipment::query()->create([
            'order_id' => $order->id,
            'provider' => 'viettel_post',
            'partner_order_id' => 'NS-WH-001',
            'tracking_number' => 'NS111',
            'tracking_id' => 111,
            'state' => Shipment::STATE_SUBMITTED,
            'response_payload' => [
                'gateway' => 'netship',
                'netship_order_id' => '111',
            ],
        ]);

        $event = app(ShippingWebhookService::class)->process('netship', [
            'id' => '111',
            'customerCode' => 'NS-WH-001',
            'status' => 3,
            'cod' => 200_000,
            'fee' => 22_000,
            'reason' => '',
        ]);

        $this->assertSame('matched', $event->result);
        $this->assertSame(DeliveryStatus::Delivered->value, $event->mapped_status);
        $this->assertSame('viettel_post', $order->fresh()->shipping_provider);
        $this->assertSame(DeliveryStatus::Delivered->value, $order->fresh()->delivery_status);
        $this->assertSame('viettel_post', $order->shipments()->first()->provider);
        $this->assertSame('netship', $order->shipments()->first()->response_payload['gateway'] ?? null);
    }

    public function test_address_resolver_throws_when_unmapped(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/address/provinces')) {
                return Http::response([['id' => 1, 'name' => 'Hà Nội']], 200);
            }

            return Http::response([], 200);
        });

        config([
            'shipping_partners.pickup.province' => 'Tỉnh Không Tồn Tại',
            'shipping_partners.pickup.district' => 'Huyện X',
            'shipping_partners.pickup.ward' => 'Xã Y',
        ]);

        $order = Order::query()->create([
            'order_code' => 'NS-ADDR-FAIL',
            'customer_name' => 'A',
            'customer_phone' => '0901111222',
            'receiver_name' => 'A',
            'receiver_phone' => '0901111222',
            'shipping_address' => 'x',
            'shipping_provider' => 'viettel_post',
            'shipping_geo' => [
                'province' => 'Tỉnh Không Tồn Tại',
                'district' => 'Huyện X',
                'ward' => 'Xã Y',
            ],
            'closed_at' => now(),
            'total' => 10_000,
            'amount_to_collect' => 10_000,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateShipmentService::class)->createForOrder($order, 'viettel_post');
    }
}
