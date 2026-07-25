<?php

namespace Tests\Feature\Pushsale;

use App\Models\Company;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\TenantManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminViewRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_critical_admin_pages_do_not_return_500_with_unknown_query_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsDemoAdmin();

        $urls = [
            '/admin/products?_qa_route_smoke=1&search=QA%20route%20smoke&vat=KCT&active=unknown&marketing=bad&sale=bad&care=bad&sort=not_real&page=999&per_page=20',
            '/admin/catalog/combos?_qa_route_smoke=1&search=QA%20route%20smoke&product_id=999999&active_status=bad&date_from=not-a-date&date_to=not-a-date&sort=unknown&page=999&per_page=20',
            '/admin/warehouse/vouchers/entry?_qa_route_smoke=1&warehouse_id=999999&product_id=999999&type=bad',
            '/admin/warehouse/vouchers?_qa_route_smoke=1&search=QA%20route%20smoke&status=bad&type=bad&page=999&per_page=20',
            '/admin/warehouse/inventory?_qa_route_smoke=1&search=QA%20route%20smoke&warehouse_id=999999&page=999&per_page=20',
            '/admin/warehouses?_qa_route_smoke=1&search=QA%20route%20smoke&page=999&per_page=20',
            '/admin/marketing/dashboard?_qa_route_smoke=1&search=QA%20route%20smoke&date_from=bad&date_to=bad',
            '/admin/sales/workspace?_qa_route_smoke=1&search=QA%20route%20smoke&lead_status=bad',
            '/admin/accounting?_qa_route_smoke=1&search=QA%20route%20smoke&status=bad',
            '/docs?_qa_route_smoke=1',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertLessThan(500, $response->getStatusCode(), "{$url} returned {$response->getStatusCode()}.");
            $this->assertNoRuntimeErrorBanner($this->safeResponseBody($response), $url);
        }
    }

    public function test_safe_registered_get_routes_resolve_or_validate_without_500(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsDemoAdmin();

        $urls = $this->safeViewUrls();
        $this->assertContains('/admin/products?_qa_route_smoke=1&page=1&per_page=20&search=QA+route+smoke&sort=created_desc', $urls);
        $this->assertContains('/admin/catalog/combos?_qa_route_smoke=1&page=1&per_page=20&search=QA+route+smoke&sort=created_desc', $urls);

        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertLessThan(500, $response->getStatusCode(), "{$url} returned {$response->getStatusCode()}.");
            $this->assertNoRuntimeErrorBanner($this->safeResponseBody($response), $url);
        }
    }

    public function test_product_business_status_checkbox_updates_business_flags(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsDemoAdmin();

        $product = Product::withoutTenant()->where('type', 'product')->where('is_active', true)->firstOrFail();

        $this->patch("/admin/products/{$product->id}/business-status", ['is_active' => false])
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertFalse((bool) $product->is_active);
        $this->assertFalse((bool) $product->available_marketing);
        $this->assertFalse((bool) $product->available_sale);
        $this->assertFalse((bool) $product->available_care);

        $this->patch("/admin/products/{$product->id}/business-status", ['is_active' => true])
            ->assertSessionHas('success');

        $product->refresh();
        $this->assertTrue((bool) $product->is_active);
        $this->assertTrue((bool) $product->available_marketing);
        $this->assertTrue((bool) $product->available_sale);
        $this->assertTrue((bool) $product->available_care);
    }


    public function test_product_permission_assignment_persists_team_and_user_ids(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsDemoAdmin();

        $product = Product::withoutTenant()->where('type', 'product')->firstOrFail();
        $marketingTeam = Team::withoutTenant()->where('type', 'marketing')->firstOrFail();
        $saleTeam = Team::withoutTenant()->where('type', 'sale')->firstOrFail();
        $marketingUser = User::withoutTenant()->where('role', User::ROLE_MARKETING)->where('team_id', $marketingTeam->id)->firstOrFail();
        $saleUser = User::withoutTenant()->where('role', User::ROLE_SALES)->where('team_id', $saleTeam->id)->firstOrFail();

        $payload = [
            'name' => $product->name,
            'type' => 'product',
            'sku' => $product->sku,
            'unit' => $product->unit,
            'cost_price' => (int) $product->cost_price,
            'unit_price' => max(1, (int) $product->unit_price),
            'vat_percent' => (float) $product->vat_percent,
            'vat_code' => $product->vat_code ?: 'KCT',
            'barcode' => $product->barcode,
            'weight_grams' => (int) $product->weight_grams,
            'length_cm' => (float) $product->length_cm,
            'width_cm' => (float) $product->width_cm,
            'height_cm' => (float) $product->height_cm,
            'warehouse_location' => $product->warehouse_location,
            'is_active' => true,
            'available_marketing' => true,
            'available_sale' => true,
            'available_care' => true,
            'marketing_team_ids' => [$marketingTeam->id],
            'marketing_user_ids' => [$marketingUser->id],
            'sale_team_ids' => [$saleTeam->id],
            'sale_user_ids' => [$saleUser->id],
            'care_team_ids' => [$saleTeam->id],
            'care_user_ids' => [$saleUser->id],
            'category_ids' => $product->categories()->pluck('product_categories.id')->all(),
            'attribute_value_ids' => $product->attributeValues()->pluck('product_attribute_values.id')->all(),
        ];

        $this->put("/admin/products/{$product->id}", $payload)->assertRedirect('/admin/products');

        $product->refresh();
        $this->assertSame([(int) $marketingTeam->id], $product->marketing_team_ids);
        $this->assertSame([(int) $marketingUser->id], $product->marketing_user_ids);
        $this->assertSame([(int) $saleTeam->id], $product->sale_team_ids);
        $this->assertSame([(int) $saleUser->id], $product->sale_user_ids);
        $this->assertSame([(int) $saleTeam->id], $product->care_team_ids);
        $this->assertSame([(int) $saleUser->id], $product->care_user_ids);
    }


    public function test_warehouse_shipping_account_configuration_persists(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAsDemoAdmin();

        $warehouse = Warehouse::withoutTenant()->firstOrFail();

        $this->put("/admin/warehouses/{$warehouse->id}/shipping-account", [
            'default_shipping_provider' => 'manual',
            'default_shipping_service' => 'manual',
            'shipping_account_settings' => [
                'manual' => [
                    'account' => 'Kho thủ công',
                    'api_token' => 'demo-token',
                    'pickup_time' => 'Sáng',
                    'pickup_method' => 'carrier_pickup',
                    'order_label_note' => 'Thông tin người gửi khi in đơn',
                ],
            ],
        ])->assertSessionHas('success');

        $warehouse->refresh();
        $this->assertSame('manual', $warehouse->default_shipping_provider);
        $this->assertSame('manual', $warehouse->default_shipping_service);
        $this->assertSame('Kho thủ công', $warehouse->shipping_account_settings['manual']['account']);
    }

    private function safeResponseBody(mixed $response): string
    {
        try {
            return (string) $response->getContent();
        } catch (\Throwable) {
            return '';
        }
    }

    private function assertNoRuntimeErrorBanner(string $body, string $url): void
    {
        $this->assertStringNotContainsString('Trang này đang thiếu dữ liệu', $body, "{$url} returned Pushsale runtime error banner.");
        $this->assertStringNotContainsString('Không tải được dữ liệu bộ lọc', $body, "{$url} returned Pushsale filter runtime error banner.");
        $this->assertStringNotContainsString('Stack trace', $body, "{$url} returned a stack trace.");
    }

    private function loginAsDemoAdmin(): User
    {
        $company = Company::withoutTenant()->where('slug', 'internal')->first()
            ?: Company::withoutTenant()->firstOrFail();

        app(TenantManager::class)->set($company->id);

        $admin = User::withoutTenant()
            ->where('company_id', $company->id)
            ->where('role', User::ROLE_ADMIN)
            ->orderByDesc('is_platform_admin')
            ->orderByDesc('is_owner')
            ->firstOrFail();

        $this->actingAs($admin);

        return $admin;
    }

    /** @return list<string> */
    private function safeViewUrls(): array
    {
        $urls = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = trim($route->uri(), '/');
            $name = (string) ($route->getName() ?? '');
            if ($this->skipRoute($uri, $name)) {
                continue;
            }

            $materialized = $this->materializeRouteUri($uri);
            if ($materialized === null) {
                continue;
            }

            $url = '/'.ltrim($materialized, '/');
            $url = $url === '/' ? '/' : rtrim($url, '/');
            if ($this->appendQueryNoise($url)) {
                $url .= (str_contains($url, '?') ? '&' : '?').http_build_query([
                    '_qa_route_smoke' => 1,
                    'page' => 1,
                    'per_page' => 20,
                    'search' => 'QA route smoke',
                    'sort' => 'created_desc',
                ]);
            }

            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }

    private function appendQueryNoise(string $url): bool
    {
        return str_starts_with($url, '/admin/')
            || str_starts_with($url, '/bao-cao/')
            || str_starts_with($url, '/ld/')
            || str_starts_with($url, '/docs');
    }

    private function skipRoute(string $uri, string $name): bool
    {
        $lower = strtolower('/'.ltrim($uri, '/').' '.$name);

        foreach ([
            '/_ignition', '/api/', '/__erm-test', '/broadcasting/', '/sanctum/', '/horizon',
            '/storage/', '/webhooks/', '/livewire/', '/telescope', '/pulse', '/logout', '/download/',
        ] as $blocked) {
            if (str_contains($lower, $blocked)) {
                return true;
            }
        }

        return str_contains($lower, 'password')
            || str_contains($lower, 'verification')
            || str_contains($lower, 'two-factor')
            || str_contains($lower, 'signed')
            || str_contains($lower, 'token}');
    }

    private function materializeRouteUri(string $uri): ?string
    {
        if ($uri === '') {
            return '/';
        }

        $path = preg_replace_callback('/\{([^}:?]+)[^}]*\}/', function (array $matches) use ($uri): string {
            $value = $this->sampleRouteParameter((string) $matches[1], $uri);

            return $value === null ? '__SKIP_ROUTE_SMOKE__' : rawurlencode((string) $value);
        }, $uri);

        if (! $path || str_contains($path, '__SKIP_ROUTE_SMOKE__')) {
            return null;
        }

        return $path;
    }

    private function sampleRouteParameter(string $name, string $uri): string|int|null
    {
        $key = Str::of($name)->lower()->snake()->toString();

        return match ($key) {
            'product' => Product::withoutTenant()->where('type', 'product')->orderBy('id')->value('id') ?? 1,
            'warehouse' => Warehouse::withoutTenant()->orderBy('id')->value('id') ?? 1,
            'order' => Order::withoutTenant()->orderBy('id')->value('id') ?? 1,
            'lead_ingestion' => LeadIngestion::withoutTenant()->orderBy('id')->value('id') ?? 1,
            'shipment' => Shipment::withoutTenant()->orderBy('id')->value('id') ?? 1,
            'user', 'admin' => User::withoutTenant()->orderBy('id')->value('id') ?? 1,
            'company', 'company_id' => Company::withoutTenant()->orderBy('id')->value('id') ?? 1,
            'category' => $this->sampleTableId('product_categories') ?? 1,
            'attribute' => $this->sampleTableId('product_attributes') ?? 1,
            'attribute_value' => $this->sampleTableId('product_attribute_values') ?? 1,
            'inventory' => $this->sampleTableId('warehouse_inventories') ?? 1,
            'campaign' => $this->sampleTableId('customer_care_campaigns') ?? 1,
            'report' => 'business',
            'id' => 1,
            'province' => '01',
            'district' => '001',
            'provider' => 'manual',
            'record' => $this->sampleRecordForRoute($uri),
            default => null,
        };
    }

    private function sampleRecordForRoute(string $uri): int|null
    {
        $lower = strtolower($uri);
        if (str_contains($lower, 'catalog/combos')) {
            return Product::withoutTenant()->where('type', 'combo')->orderBy('id')->value('id');
        }
        if (str_contains($lower, 'warehouse/vouchers')) {
            return WarehouseVoucher::withoutTenant()->orderBy('id')->value('id');
        }

        return null;
    }

    private function sampleTableId(string $table): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->orderBy('id')->value('id');
    }
}
