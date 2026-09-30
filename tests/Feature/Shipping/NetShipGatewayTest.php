<?php

namespace Tests\Feature\Shipping;

use App\Enums\DeliveryStatus;
use App\Enums\InboundEventSource;
use App\Enums\InboundEventStatus;
use App\Jobs\Orders\RecordOrderTraceJob;
use App\Jobs\Shipping\ProcessShippingWebhookJob;
use App\Models\InboundEvent;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingPartnerConnection;
use App\Services\Shipping\CarrierRegistry;
use App\Services\Shipping\CreateShipmentService;
use App\Services\Shipping\Gateways\NetShip\NetShipProxyCarrier;
use App\Services\Shipping\ShippingWebhookService;
use App\Support\ShippingProviders;
use App\Support\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
                'shop_id' => 530,
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
        $this->assertArrayHasKey('netship', ShippingProviders::gatewayProviders());
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
            && data_get($request->data(), 'customerCode') === 'NS-ORDER-001'
            && ! array_key_exists('myRequest', $request->data()));
    }

    public function test_sync_status_searches_by_external_code_not_customer_code(): void
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
            if (str_ends_with($url, '/api/third-party/order') && $request->method() === 'POST') {
                return Http::response([
                    'data' => ['order' => [
                        'id' => 3461315,
                        'linkId' => 'NSN3461315',
                        'externalCode' => 'GYRDVHFF',
                        'status' => 0,
                    ]],
                ], 201);
            }
            if (str_contains($url, '/api/third-party/order') && $request->method() === 'GET') {
                // NetShip `search` only matches externalCode / linkId.
                $rows = str_contains($url, 'search=GYRDVHFF')
                    ? [['id' => 3461315, 'linkId' => 'NSN3461315', 'externalCode' => 'GYRDVHFF', 'status' => 2]]
                    : [];

                return Http::response(['data' => ['data' => $rows, 'total' => count($rows)]], 200);
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
            'order_code' => 'NS-SYNC-001',
            'customer_name' => 'Khách Sync',
            'customer_phone' => '0901234567',
            'receiver_name' => 'Khách Sync',
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
        $this->assertSame('GYRDVHFF', $shipment->response_payload['netship_external_code'] ?? null);

        $synced = app(CarrierRegistry::class)->get('viettel_post')->syncStatus($order->fresh(), $shipment);

        $this->assertSame('Đang giao hàng', $synced->status_text);
        $this->assertSame(DeliveryStatus::Delivering->value, $order->fresh()->delivery_status);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), 'search=GYRDVHFF'));
    }

    public function test_netship_error_body_surfaces_as_create_failure_message(): void
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
                return Http::response(['error' => 'ghn: phường gửi "" chưa được map mã GHN'], 500);
            }

            return Http::response(['success' => false, 'message' => 'unexpected'], 500);
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
            'order_code' => 'NS-ERR-001',
            'customer_name' => 'Khách NetShip',
            'customer_phone' => '0901234567',
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

        try {
            app(CreateShipmentService::class)->createForOrder($order, 'viettel_post');
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('map mã GHN', $e->getMessage());
        }
    }

    public function test_create_payload_matches_netship_docs_fields_exactly(): void
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
                    'data' => ['id' => 555, 'tracking_number' => 'NS555', 'fee' => 10000],
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
            'order_code' => 'NS-DOCS-FIELDS',
            'customer_name' => 'KH Map',
            'customer_phone' => '0902222333',
            'receiver_name' => 'KH Map',
            'receiver_phone' => '0902222333',
            'shipping_address' => '2 Lê Lợi',
            'shipping_provider' => 'viettel_post',
            'shipping_geo' => [
                'province' => 'Hà Nội',
                'district' => 'Quận Cầu Giấy',
                'ward' => 'Phường Dịch Vọng',
            ],
            'closed_at' => now(),
            'total' => 80_000,
            'amount_to_collect' => 80_000,
        ]);

        app(CreateShipmentService::class)->createForOrder($order, 'viettel_post');

        $docsFields = [
            'customerCode', 'senderName', 'senderPhone', 'senderAddress',
            'senderProvinceId', 'senderDistrictId', 'senderWardId',
            'receiverName', 'receiverPhone', 'receiverAddress',
            'receiverProvinceId', 'receiverDistrictId', 'receiverWardId',
            'productName', 'quantity', 'productType', 'codPrice', 'price',
            'weight', 'length', 'width', 'height', 'orderNote', 'deliveryNote',
            'receiverPay', 'pickupType',
        ];

        Http::assertSent(function (Request $request) use ($docsFields) {
            if (! str_ends_with($request->url(), '/api/third-party/order') || $request->method() !== 'POST') {
                return false;
            }

            $sent = array_keys($request->data());
            sort($sent);
            $expected = $docsFields;
            sort($expected);

            return $sent === $expected
                && data_get($request->data(), 'senderWardId') === 100
                && data_get($request->data(), 'receiverWardId') === 100;
        });
    }

    public function test_create_shipment_works_without_netship_shop_id(): void
    {
        ShippingPartnerConnection::forProvider('netship')->update([
            'credentials' => [
                'token' => 'test-netship-token',
                'base_url' => 'https://test.netship.vn',
            ],
        ]);

        Http::fake([
            '*/api/address/provinces' => Http::response([['id' => 1, 'name' => 'Hà Nội']], 200),
            '*/api/address/districts*' => Http::response([['id' => 10, 'name' => 'Quận Cầu Giấy']], 200),
            '*/api/address/ward*' => Http::response([['id' => 100, 'name' => 'Phường Dịch Vọng']], 200),
            '*/api/third-party/order' => Http::response([
                'data' => ['order' => ['id' => 3461315, 'linkId' => 'NSN3461315', 'status' => 0]],
            ], 201),
        ]);

        config([
            'shipping_partners.pickup.province' => 'Hà Nội',
            'shipping_partners.pickup.district' => 'Quận Cầu Giấy',
            'shipping_partners.pickup.ward' => 'Phường Dịch Vọng',
            'shipping_partners.default_geo.province' => 'Hà Nội',
            'shipping_partners.default_geo.district' => 'Quận Cầu Giấy',
            'shipping_partners.default_geo.ward' => 'Phường Dịch Vọng',
        ]);

        $order = Order::query()->create([
            'order_code' => 'NS-NO-SHOP',
            'customer_name' => 'No Shop',
            'customer_phone' => '0903333444',
            'receiver_name' => 'No Shop',
            'receiver_phone' => '0903333444',
            'shipping_address' => '3 Pasteur',
            'shipping_provider' => 'viettel_post',
            'shipping_geo' => [
                'province' => 'Hà Nội',
                'district' => 'Quận Cầu Giấy',
                'ward' => 'Phường Dịch Vọng',
            ],
            'closed_at' => now(),
            'total' => 50_000,
            'amount_to_collect' => 50_000,
        ]);

        $shipment = app(CreateShipmentService::class)->createForOrder($order, 'viettel_post');

        $this->assertSame('3461315', (string) ($shipment->response_payload['netship_order_id'] ?? ''));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/third-party/order')
            && ! array_key_exists('ShopID', $request->data())
            && ! array_key_exists('myRequest', $request->data()));
    }

    public function test_api_client_surfaces_netship_error_body(): void
    {
        Http::fake([
            '*/api/third-party/order' => Http::response([
                'error' => 'Lỗi gọi API: master_data_validate_phone - số điện thoại 0123456789 không đúng',
            ], 500),
        ]);

        $result = app(\App\Services\Shipping\Gateways\NetShip\NetShipApiClient::class)
            ->createOrder(['customerCode' => 'NS-BAD-PHONE', 'receiverPhone' => '0123456789']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('số điện thoại 0123456789 không đúng', (string) $result['message']);
        Http::assertSent(fn (Request $request) => data_get($request->data(), 'customerCode') === 'NS-BAD-PHONE');
    }

    public function test_proxy_for_unmapped_provider_throws_without_carrier_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('NetShip không map được provider [unknown_carrier_xyz].');

        app(\App\Services\Shipping\Gateways\NetShip\NetShipGateway::class)
            ->proxyFor('unknown_carrier_xyz');
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

    public function test_address_resolver_maps_2025_two_level_address_to_old_structure(): void
    {
        // NetShip vẫn trả danh mục 3 cấp cũ: "Hòa Bình" là tỉnh riêng, chưa gộp vào Phú Thọ.
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/api/address/provinces')) {
                return Http::response([
                    ['id' => 17, 'name' => 'Tỉnh Hoà Bình'],
                    ['id' => 25, 'name' => 'Tỉnh Phú Thọ'],
                ], 200);
            }
            if (str_contains($url, '/api/address/districts')) {
                return Http::response(str_contains($url, 'provinceId=17')
                    ? [['id' => 148, 'name' => 'Thành phố Hòa Bình']]
                    : [['id' => 227, 'name' => 'Thành phố Việt Trì']], 200);
            }
            if (str_contains($url, '/api/address/ward')) {
                return Http::response(str_contains($url, 'districtId=148')
                    ? [['id' => 4825, 'name' => 'Xã Hòa Bình'], ['id' => 4807, 'name' => 'Phường Phương Lâm']]
                    : [['id' => 9001, 'name' => 'Phường Gia Cẩm']], 200);
            }

            return Http::response([], 200);
        });

        $resolver = app(\App\Services\Shipping\Gateways\NetShip\NetShipAddressResolver::class);

        // Kho 2 cấp: tỉnh mới "Phú Thọ", phường "Hòa Bình", district suy ra từ ward.
        $this->assertSame(
            ['provinceId' => 17, 'districtId' => 148, 'wardId' => 4825],
            $resolver->resolve('Phú Thọ', 'Hòa Bình', 'Hòa Bình'),
        );

        // Địa chỉ 3 cấp hợp lệ trong chính tỉnh đó vẫn giữ nguyên kết quả.
        $this->assertSame(
            ['provinceId' => 25, 'districtId' => 227, 'wardId' => 9001],
            $resolver->resolve('Phú Thọ', 'Thành phố Việt Trì', 'Phường Gia Cẩm'),
        );
    }

    public function test_receiver_ids_prefer_stored_gso_codes_over_name_matching(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/api/address/provinces')) {
                return Http::response([['id' => 79, 'name' => 'Thành phố Hồ Chí Minh']], 200);
            }
            if (str_contains($url, '/api/address/districts')) {
                return Http::response([['id' => 764, 'name' => 'Quận Gò Vấp']], 200);
            }
            if (str_contains($url, '/api/address/ward')) {
                // Trùng tên "Phường 15" — dò theo tên sẽ lấy nhầm phường đầu tiên.
                return Http::response([
                    ['id' => 26869, 'name' => 'Phường 15'],
                    ['id' => 26872, 'name' => 'Phường 15'],
                ], 200);
            }
            if (str_ends_with($url, '/api/third-party/order') && $request->method() === 'POST') {
                return Http::response(['data' => ['order' => [
                    'id' => 771,
                    'linkId' => 'NSN771',
                    'externalCode' => 'GYRDTEST',
                    'billCode' => '151568815318',
                    'codPrice' => 150000,
                    'senderProvinceId' => 79,
                ]]], 201);
            }

            return Http::response(['success' => false, 'message' => 'unexpected '.$url], 500);
        });

        config([
            'shipping_partners.pickup.province' => 'Thành phố Hồ Chí Minh',
            'shipping_partners.pickup.district' => 'Quận Gò Vấp',
            'shipping_partners.pickup.ward' => 'Phường 15',
        ]);

        $order = Order::query()->create([
            'order_code' => 'NS-GEO-CODE',
            'customer_name' => 'KH Code',
            'customer_phone' => '0901234567',
            'receiver_name' => 'KH Code',
            'receiver_phone' => '0901234567',
            'shipping_address' => '884/26 Lê Đức Thọ',
            'shipping_provider' => 'viettel_post',
            'shipping_geo' => [
                'mode' => 'old',
                'province' => 'Thành phố Hồ Chí Minh',
                'district' => 'Quận Gò Vấp',
                'ward' => 'Phường 15',
                'province_code' => '79',
                'district_code' => '764',
                'ward_code' => '26872',
            ],
            'closed_at' => now(),
            'total' => 150_000,
            'amount_to_collect' => 150_000,
        ]);

        app(CreateShipmentService::class)->createForOrder($order, 'viettel_post');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/third-party/order')
            && $request->method() === 'POST'
            && data_get($request->data(), 'receiverProvinceId') === 79
            && data_get($request->data(), 'receiverDistrictId') === 764
            && data_get($request->data(), 'receiverWardId') === 26872);

        $refs = $order->shipments()->first()?->response_payload['partner_refs'] ?? [];
        $this->assertContains('NSN771', $refs);
        $this->assertContains('GYRDTEST', $refs);
        $this->assertContains('151568815318', $refs);
        $this->assertContains('NS-GEO-CODE', $refs);
        $this->assertNotContains('150000', $refs);
        $this->assertNotContains('79', $refs);

        $shipment = $order->shipments()->first();
        $this->assertSame('GYRDTEST', $shipment?->tracking_number);
        $this->assertSame('GYRDTEST', $order->fresh()->tracking_number);

        Queue::pushed(RecordOrderTraceJob::class)->each(fn (RecordOrderTraceJob $job) => $job->handle());

        $trace = \App\Models\ShippingGatewayTrace::query()->where('external_code', 'GYRDTEST')->first();
        $this->assertNotNull($trace);
        $this->assertSame('create_order', $trace->action);
        $this->assertSame('771', $trace->gateway_order_id);
        $this->assertSame($shipment?->id, $trace->shipment_id);
        $this->assertDatabaseHas('order_traces', [
            'stage' => 'gateway_response',
            'action' => 'create_order',
            'external_code' => 'GYRDTEST',
            'shipment_id' => $shipment?->id,
        ]);
    }

    public function test_webhook_without_secret_is_accepted_only_from_netship_ip(): void
    {
        ShippingPartnerConnection::forProvider('netship')->update(['webhook_secret' => null]);
        config(['security.webhook.provider_ip_allowlist.netship' => ['45.32.108.164']]);
        // Local/testing có nhánh bỏ qua secret riêng — khoá lại để kiểm đúng hành vi production.
        $this->app->detectEnvironment(fn () => 'production');

        $payload = ['id' => 3461315, 'customerCode' => 'NS-IP-001', 'status' => 1, 'cod' => 200000, 'fee' => 20000];

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->postJson('/api/v1/shipping/webhooks/netship', $payload)
            ->assertStatus(401);

        $this->withServerVariables(['REMOTE_ADDR' => '45.32.108.164'])
            ->postJson('/api/v1/shipping/webhooks/netship', $payload)
            ->assertStatus(202);

        Queue::assertPushed(RecordOrderTraceJob::class, function (RecordOrderTraceJob $job): bool {
            return ($job->event['stage'] ?? null) === 'webhook_received'
                && ($job->event['gateway_order_id'] ?? null) === '3461315';
        });
    }

    public function test_webhook_job_marks_the_inbound_event_processed(): void
    {
        $event = InboundEvent::query()->create([
            'company_id' => 1,
            'source' => InboundEventSource::ShippingWebhook,
            'channel' => 'netship',
            'status' => InboundEventStatus::Queued,
            'payload' => [],
            'headers' => [],
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        (new ProcessShippingWebhookJob('netship', [
            'id' => '222',
            'customerCode' => 'NS-JOB-001',
            'status' => 1,
        ], $event->id, 1))->handle(app(ShippingWebhookService::class), app(TenantManager::class));

        $this->assertSame(InboundEventStatus::Processed, $event->fresh()->status);
    }
}
