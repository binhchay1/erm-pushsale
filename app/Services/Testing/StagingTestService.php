<?php

namespace App\Services\Testing;

use App\Models\Company;
use App\Models\LandingConnection;
use App\Models\LandingConnectionProduct;
use App\Models\LandingConnectionSale;
use App\Models\LandingConnectionSource;
use App\Models\MarketingSource;
use App\Models\Warehouse;
use App\Services\Orders\OrderClosingService;
use App\Services\Shipping\ShippingWebhookService;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StagingTestService
{
    /** @return array<string, mixed> */
    public function health(): array
    {
        $checks = [];

        $checks['php'] = [
            'ok' => true,
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
        ];

        $checks['app'] = [
            'ok' => true,
            'name' => config('app.name'),
            'env' => config('app.env'),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'timezone' => config('app.timezone'),
        ];

        try {
            DB::select('select 1 as ok');
            $checks['database'] = [
                'ok' => true,
                'connection' => config('database.default'),
                'database' => config('database.connections.'.config('database.default').'.database'),
            ];
        } catch (\Throwable $e) {
            $checks['database'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $key = 'staging_test:'.Str::random(10);
            Cache::put($key, 'ok', 60);
            $checks['cache'] = [
                'ok' => Cache::get($key) === 'ok',
                'store' => config('cache.default'),
            ];
            Cache::forget($key);
        } catch (\Throwable $e) {
            $checks['cache'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $path = storage_path('framework/cache/staging-test-'.Str::random(8).'.txt');
            file_put_contents($path, 'ok');
            $checks['storage'] = [
                'ok' => is_file($path) && trim((string) file_get_contents($path)) === 'ok',
                'path' => storage_path(),
            ];
            @unlink($path);
        } catch (\Throwable $e) {
            $checks['storage'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        $checks['queue'] = [
            'ok' => true,
            'default' => config('queue.default'),
            'horizon_connection' => config('horizon.use') ?? 'n/a',
        ];

        $checks['data'] = $this->safeCounts();
        $checks['routes'] = [
            'ok' => true,
            'count' => count(Route::getRoutes()),
        ];

        $ok = collect($checks)->every(static fn ($check) => (bool) ($check['ok'] ?? false));

        return [
            'ok' => $ok,
            'generated_at' => now()->toISOString(),
            'checks' => $checks,
        ];
    }

    /** @return array<string, mixed> */
    public function bootstrapDemo(bool $reset = false, int $campaigns = 2, int $perCampaign = 8): array
    {
        $commands = [];

        $commands[] = $this->runArtisan('migrate', ['--force' => true]);
        $commands[] = $this->seedDemoSafely();

        $bulkOptions = [
            '--campaigns' => max(1, min(5, $campaigns)),
            '--per-campaign' => max(1, min(50, $perCampaign)),
            '--pending-campaign' => 1,
        ];
        if ($reset) {
            $bulkOptions['--reset'] = true;
        }

        $commands[] = $this->runArtisan('demo:bulk-flow', $bulkOptions);
        $commands[] = $this->runArtisan('reports:aggregate-daily', ['date' => 'today']);

        $ok = collect($commands)->every(static fn ($row) => (int) ($row['exit_code'] ?? 1) === 0);

        return [
            'ok' => $ok,
            'generated_at' => now()->toISOString(),
            'commands' => $commands,
            'counts' => $this->safeCounts(),
        ];
    }

    /** @return array<string, mixed> */
    public function fullFlow(?string $phone = null): array
    {
        $phone = $phone ?: '09'.random_int(10000000, 99999999);

        $command = $this->runArtisan('e2e:flow-test', ['--phone' => $phone]);

        return [
            'ok' => (int) $command['exit_code'] === 0,
            'phone' => $phone,
            'generated_at' => now()->toISOString(),
            'command' => $command,
            'counts' => $this->safeCounts(),
        ];
    }

    /** @param list<string>|null $urls
     *  @return array<string, mixed>
     */
    public function scanPages(?array $urls = null, bool $allStatic = false): array
    {
        $baseUrl = rtrim((string) config('staging_test.base_url'), '/');
        $timeout = max(5, (int) config('staging_test.timeout', 20));
        $urls = $urls ?: ($allStatic ? $this->allPageUrls() : (array) config('staging_test.page_urls', []));

        $results = [];

        foreach ($urls as $url) {
            $url = '/'.ltrim((string) $url, '/');
            $fullUrl = $baseUrl.$url;
            $started = microtime(true);

            try {
                $response = Http::timeout($timeout)
                    ->withOptions(['allow_redirects' => true, 'verify' => false])
                    ->get($fullUrl);

                $body = (string) $response->body();
                $title = $this->extractTitle($body);
                $hasInertiaRoot = str_contains($body, 'data-page=') || str_contains($body, 'id="app"');
                $hasPhpError = str_contains($body, 'Whoops')
                    || str_contains($body, 'Symfony\\Component\\ErrorHandler')
                    || str_contains($body, 'Illuminate\\Database')
                    || str_contains($body, 'Stack trace');

                $ok = $response->status() >= 200 && $response->status() < 400 && ! $hasPhpError;

                $results[] = [
                    'url' => $url,
                    'status' => $response->status(),
                    'ok' => $ok,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                    'title' => $title,
                    'inertia_root' => $hasInertiaRoot,
                    'bytes' => strlen($body),
                    'error_hint' => $hasPhpError ? 'php_error_signature_in_body' : (! $ok ? $this->compactErrorHint($body) : null),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'url' => $url,
                    'status' => 0,
                    'ok' => false,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                    'title' => null,
                    'inertia_root' => false,
                    'bytes' => 0,
                    'error_hint' => $e->getMessage(),
                ];
            }
        }

        $failed = array_values(array_filter($results, static fn ($row) => ! (bool) $row['ok']));

        return [
            'ok' => count($failed) === 0,
            'base_url' => $baseUrl,
            'all_static' => $allStatic,
            'generated_at' => now()->toISOString(),
            'total' => count($results),
            'failed' => count($failed),
            'failed_results' => $failed,
            'results' => $results,
        ];
    }

    /** @return array<string, mixed> */
    public function landingConnectionFlow(?string $phone = null): array
    {
        $phone = preg_replace('/\D+/', '', $phone ?: '09'.random_int(10000000, 99999999));
        $baseUrl = rtrim((string) config('staging_test.base_url'), '/');
        $admin = User::withoutTenant()->where('role', User::ROLE_ADMIN)->orderByDesc('is_owner')->first();
        if (! $admin) {
            $seed = $this->seedDemoSafely();
            $admin = User::withoutTenant()->where('role', User::ROLE_ADMIN)->orderByDesc('is_owner')->first();
            if (! $admin) {
                return ['ok' => false, 'error' => 'Không có admin để tạo dữ liệu test.', 'seed' => $seed];
            }
        }

        $companyId = (int) $admin->company_id;
        $marketer = User::withoutTenant()->where('company_id', $companyId)->where('role', User::ROLE_MARKETING)->first()
            ?: User::withoutTenant()->where('company_id', $companyId)->first();
        $sales = User::withoutTenant()->where('company_id', $companyId)->where('role', User::ROLE_SALES)->limit(2)->get();
        $warehouse = Warehouse::withoutTenant()->where('company_id', $companyId)->first();
        $products = Product::withoutTenant()->where('company_id', $companyId)->where('is_active', true)->orderBy('id')->limit(2)->get();

        if (! $marketer || $sales->isEmpty() || ! $warehouse || $products->isEmpty()) {
            $seed = $this->seedDemoSafely();
            $marketer = User::withoutTenant()->where('company_id', $companyId)->where('role', User::ROLE_MARKETING)->first()
                ?: User::withoutTenant()->where('company_id', $companyId)->first();
            $sales = User::withoutTenant()->where('company_id', $companyId)->where('role', User::ROLE_SALES)->limit(2)->get();
            $warehouse = Warehouse::withoutTenant()->where('company_id', $companyId)->first();
            $products = Product::withoutTenant()->where('company_id', $companyId)->where('is_active', true)->orderBy('id')->limit(2)->get();

            if (! $marketer || $sales->isEmpty() || ! $warehouse || $products->isEmpty()) {
                return [
                    'ok' => false,
                    'error' => 'Thiếu marketer/sale/kho/sản phẩm sau khi seed.',
                    'seed' => $seed,
                    'counts' => $this->safeCounts(),
                ];
            }
        }

        $baseProduct = $products[0];
        $upsellProduct = $products[1] ?? $products[0];
        $suffix = now()->format('YmdHis').'-'.Str::lower(Str::random(5));

        $source = \Illuminate\Database\Eloquent\Model::unguarded(fn () => MarketingSource::withoutTenant()->create([
            'company_id' => $companyId,
            'name' => 'QA Landing Connection '.$suffix,
            'product_id' => $baseProduct->id,
            'marketer_user_id' => $marketer->id,
            'created_by_user_id' => $admin->id,
            'ad_channel' => 'landing',
            'utm_source' => 'qa_ladipage',
            'utm_campaign' => 'qa-landing-'.$suffix,
            'budget' => 2_000_000,
            'is_active' => true,
            'is_approved' => true,
            'approved_by_user_id' => $admin->id,
            'approved_at' => now(),
        ]));

        $connection = \Illuminate\Database\Eloquent\Model::unguarded(fn () => LandingConnection::withoutTenant()->create([
            'company_id' => $companyId,
            'marketing_source_id' => $source->id,
            'name' => 'QA Kết nối Landing '.$suffix,
            'marketer_user_id' => $marketer->id,
            'connection_type' => 'landing',
            'ad_channel' => 'ladipage',
            'allocation_method' => 'round_robin',
            'budget_type' => 'total',
            'budget_amount' => 2_000_000,
            'budget_start_date' => now()->toDateString(),
            'budget_end_date' => now()->toDateString(),
            'success_url' => $baseUrl.'/thank-you?ps_flow={flow_token}',
            'manual_import' => false,
            'is_approved' => true,
            'is_active' => true,
            'approved_by_user_id' => $admin->id,
            'approved_at' => now(),
            'created_by_user_id' => $admin->id,
        ]));

        $mainSource = \Illuminate\Database\Eloquent\Model::unguarded(fn () => LandingConnectionSource::withoutTenant()->create([
            'company_id' => $companyId,
            'landing_connection_id' => $connection->id,
            'name' => 'Landing chính QA',
            'source_type' => LandingConnectionSource::TYPE_MAIN,
            'source_url' => $baseUrl.'/qa/main-'.$suffix,
            'redirect_url' => $baseUrl.'/qa/upsell-'.$suffix.'?ps_flow={flow_token}',
            'sort_order' => 1,
            'is_active' => true,
        ]));

        $upsellSource = \Illuminate\Database\Eloquent\Model::unguarded(fn () => LandingConnectionSource::withoutTenant()->create([
            'company_id' => $companyId,
            'landing_connection_id' => $connection->id,
            'name' => 'Upsale QA',
            'source_type' => LandingConnectionSource::TYPE_UPSELL,
            'source_url' => $baseUrl.'/qa/upsell-'.$suffix,
            'redirect_url' => $baseUrl.'/qa/thank-you-'.$suffix,
            'sort_order' => 2,
            'is_active' => true,
        ]));

        \Illuminate\Database\Eloquent\Model::unguarded(fn () => LandingConnectionProduct::withoutTenant()->create([
            'company_id' => $companyId,
            'landing_connection_id' => $connection->id,
            'landing_connection_source_id' => $mainSource->id,
            'product_id' => $baseProduct->id,
            'item_type' => 'product',
            'quantity' => 1,
            'is_default' => true,
            'sort_order' => 1,
        ]));

        \Illuminate\Database\Eloquent\Model::unguarded(fn () => LandingConnectionProduct::withoutTenant()->create([
            'company_id' => $companyId,
            'landing_connection_id' => $connection->id,
            'landing_connection_source_id' => $upsellSource->id,
            'product_id' => $upsellProduct->id,
            'item_type' => 'upsell',
            'quantity' => 1,
            'is_default' => true,
            'sort_order' => 2,
        ]));

        foreach ($sales as $index => $sale) {
            \Illuminate\Database\Eloquent\Model::unguarded(fn () => LandingConnectionSale::withoutTenant()->create([
                'company_id' => $companyId,
                'landing_connection_id' => $connection->id,
                'user_id' => $sale->id,
                'priority' => $index + 1,
                'weight' => 1,
                'is_active' => true,
            ]));
        }

        $mainUrl = $baseUrl.'/api/v1/landing-connections/'.$connection->public_token.'/sources/'.$mainSource->public_token.'/submit';
        $upsellUrl = $baseUrl.'/api/v1/landing-connections/'.$connection->public_token.'/sources/'.$upsellSource->public_token.'/submit';

        $mainResponse = Http::timeout((int) config('staging_test.timeout', 20))
            ->acceptJson()
            ->withOptions(['verify' => false])
            ->post($mainUrl, [
                'submission_id' => 'qa-main-'.$suffix,
                'name' => 'Khách QA Landing '.$suffix,
                'phone' => $phone,
                'address' => '123 QA Street, Hà Nội',
                'utm_source' => 'qa_ladipage',
            ]);

        $flowToken = $mainResponse->json('flow_token');
        $upsellResponse = null;
        if ($mainResponse->successful() && $flowToken) {
            $upsellResponse = Http::timeout((int) config('staging_test.timeout', 20))
                ->acceptJson()
                ->withOptions(['verify' => false])
                ->post($upsellUrl, [
                    'submission_id' => 'qa-upsell-'.$suffix,
                    'phone' => $phone,
                    'ps_flow' => $flowToken,
                    'upsell_accept' => 'yes',
                ]);
        }

        $order = Order::withoutTenant()
            ->where('company_id', $companyId)
            ->where('landing_connection_id', $connection->id)
            ->where('customer_phone', $phone)
            ->with('items')
            ->latest('id')
            ->first();

        $close = null;
        $shipping = null;
        if ($order) {
            try {
                $saleUser = User::withoutTenant()->whereKey($order->sale_user_id)->first() ?: $sales->first();
                app(OrderClosingService::class)->close($order->fresh(), $saleUser, [
                    'shipping_provider' => 'manual',
                    'warehouse_id' => $warehouse->id,
                    'amount_to_collect' => (int) $order->total,
                    'shipping_address' => '123 QA Street, Hà Nội',
                    'confirm_insufficient_stock' => true,
                ]);
                $tracking = 'QA'.strtoupper(Str::random(10));
                $order->refresh()->forceFill([
                    'tracking_number' => $tracking,
                    'shipping_provider' => 'manual',
                ])->save();
                app(ShippingWebhookService::class)->process('manual', [
                    'tracking_number' => $tracking,
                    'order_code' => $order->order_code,
                    'status_text' => 'delivered',
                    'cod' => $order->amount_to_collect,
                ]);
                $close = ['ok' => true, 'tracking_number' => $tracking];
                $shipping = ['delivery_status' => $order->fresh()->delivery_status, 'reconciliation_status' => $order->fresh()->reconciliation_status];
            } catch (\Throwable $e) {
                $close = ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        $order?->refresh()->load('items');
        $mainOk = $mainResponse->successful();
        $upsellOk = $upsellResponse?->successful() ?? false;
        $hasBase = $order?->items?->contains(fn ($item) => $item->product_id === $baseProduct->id) ?? false;
        $hasUpsell = $order?->items?->contains(fn ($item) => $item->product_id === $upsellProduct->id) ?? false;

        return [
            'ok' => $mainOk && $upsellOk && (bool) $order && $hasBase && $hasUpsell,
            'generated_at' => now()->toISOString(),
            'connection' => [
                'id' => $connection->id,
                'name' => $connection->name,
                'main_submit_url' => $mainUrl,
                'upsell_submit_url' => $upsellUrl,
                'budget_amount' => $connection->budget_amount,
            ],
            'http' => [
                'main' => ['status' => $mainResponse->status(), 'body' => $mainResponse->json() ?: $mainResponse->body()],
                'upsell' => $upsellResponse ? ['status' => $upsellResponse->status(), 'body' => $upsellResponse->json() ?: $upsellResponse->body()] : null,
            ],
            'order' => $order ? [
                'id' => $order->id,
                'code' => $order->order_code,
                'sale_user_id' => $order->sale_user_id,
                'total' => $order->total,
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'item_type' => $item->item_type,
                    'total' => $item->lineTotal(),
                ])->values(),
            ] : null,
            'assertions' => [
                'main_accepted' => $mainOk,
                'upsell_accepted' => $upsellOk,
                'order_created' => (bool) $order,
                'base_item_present' => $hasBase,
                'upsell_item_present' => $hasUpsell,
            ],
            'close' => $close,
            'shipping' => $shipping,
        ];
    }

    /** @return array<string, mixed> */
    public function audit(): array
    {
        return [
            'ok' => true,
            'generated_at' => now()->toISOString(),
            'business_flow' => $this->runArtisan('audit:business-flow', ['--json' => true]),
            'facts_verify' => $this->runArtisan('reports:verify-facts', ['--days' => 14]),
        ];
    }

    /** @param array<string, mixed> $parameters
     *  @return array<string, mixed>
     */
    public function runArtisan(string $command, array $parameters = []): array
    {
        $started = microtime(true);

        try {
            $exitCode = Artisan::call($command, $parameters);

            return [
                'command' => $command,
                'parameters' => $parameters,
                'exit_code' => $exitCode,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'output' => trim(Artisan::output()),
            ];
        } catch (\Throwable $e) {
            return [
                'command' => $command,
                'parameters' => $parameters,
                'exit_code' => 1,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'output' => trim(Artisan::output()),
                'error' => $e->getMessage(),
            ];
        }
    }


    /** @return array<string, mixed> */
    private function seedDemoSafely(): array
    {
        $reportingEnabled = config('reporting.enabled');
        $archiveEnabled = config('reporting.archive.enabled');

        config([
            'reporting.enabled' => false,
            'reporting.archive.enabled' => false,
        ]);

        try {
            return $this->runArtisan('db:seed', ['--force' => true]);
        } finally {
            config([
                'reporting.enabled' => $reportingEnabled,
                'reporting.archive.enabled' => $archiveEnabled,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function safeCounts(): array
    {
        $models = [
            'companies' => [Company::class, 'companies'],
            'users' => [User::class, 'users'],
            'products' => [Product::class, 'products'],
            'landing_connections' => [LandingConnection::class, 'landing_connections'],
            'lead_ingestions' => [LeadIngestion::class, 'lead_ingestions'],
            'orders' => [Order::class, 'orders'],
            'shipments' => [Shipment::class, 'shipments'],
        ];

        $counts = [];
        foreach ($models as $key => [$model, $table]) {
            try {
                $counts[$key] = Schema::hasTable($table) ? $this->modelQueryWithoutTenant($model)->count() : null;
            } catch (\Throwable $e) {
                $counts[$key] = 'error: '.$e->getMessage();
            }
        }

        return $counts;
    }


    /** @return array<string, mixed> */
    public function logs(int $lines = 160): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, static fn (string $a, string $b) => filemtime($b) <=> filemtime($a));
        $file = $files[0] ?? null;

        if (! $file || ! is_file($file)) {
            return ['ok' => true, 'file' => null, 'lines' => []];
        }

        $content = @file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $tail = array_slice($content, -max(20, min(1000, $lines)));

        return [
            'ok' => true,
            'file' => basename($file),
            'updated_at' => date('c', filemtime($file)),
            'lines' => array_values($tail),
        ];
    }

    /** @return list<string> */
    private function allPageUrls(): array
    {
        return $this->normalizeUrls(array_merge(
            (array) config('staging_test.page_urls', []),
            $this->navigationUrls(),
            $this->staticWebGetUrls(),
        ));
    }

    /** @return list<string> */
    private function navigationUrls(): array
    {
        $urls = [];
        $walk = static function (array $items) use (&$walk, &$urls): void {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $url = $item['url'] ?? null;
                if (is_string($url) && str_starts_with($url, '/')) {
                    $urls[] = $url;
                }

                if (isset($item['children']) && is_array($item['children'])) {
                    $walk($item['children']);
                }
            }
        };

        $navigation = config('pushsale_navigation', []);
        if (is_array($navigation)) {
            $walk($navigation);
        }

        return $this->normalizeUrls($urls);
    }

    /** @return list<string> */
    private function staticWebGetUrls(): array
    {
        $urls = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = '/'.ltrim($route->uri(), '/');
            if ($uri === '/_ignition/health-check'
                || str_starts_with($uri, '/api/')
                || str_starts_with($uri, '/__erm-test')
                || str_contains($uri, '{')
                || str_contains($uri, '}')
            ) {
                continue;
            }

            $urls[] = $uri === '/' ? '/' : rtrim($uri, '/');
        }

        return $this->normalizeUrls($urls);
    }

    /** @param list<string> $urls
     *  @return list<string>
     */
    private function normalizeUrls(array $urls): array
    {
        $normalized = [];
        foreach ($urls as $url) {
            $url = '/'.ltrim((string) $url, '/');
            if ($url === '/#' || str_contains($url, '{') || str_contains($url, '}')) {
                continue;
            }

            $normalized[] = $url === '/' ? '/' : rtrim($url, '/');
        }

        return array_values(array_unique($normalized));
    }

    /** @param class-string $model */
    private function modelQueryWithoutTenant(string $model): \Illuminate\Database\Eloquent\Builder
    {
        $query = $model::query();

        return method_exists($model, 'scopeWithoutTenant') ? $model::withoutTenant() : $query;
    }

    private function compactErrorHint(string $body): ?string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?: '');
        if ($plain === '') {
            return null;
        }

        return mb_substr($plain, 0, 240);
    }

    private function extractTitle(string $body): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            return trim(html_entity_decode(strip_tags($matches[1])));
        }

        return null;
    }
}
