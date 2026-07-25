<?php

namespace App\Services\Testing;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\LandingConnection;
use App\Models\LandingConnectionProduct;
use App\Models\LandingConnectionSale;
use App\Models\LandingConnectionSource;
use App\Models\MarketingSource;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Models\Pushsale\WarehouseVoucher;
use App\Services\Orders\OrderClosingService;
use App\Services\Shipping\ShippingWebhookService;
use Database\Seeders\FullBusinessDemoSeeder;
use App\Models\LeadIngestion;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
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

        $ok = collect($checks)
            ->filter(static fn ($check): bool => is_array($check) && array_key_exists('ok', $check))
            ->every(static fn ($check): bool => (bool) ($check['ok'] ?? false));

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


    /** Chuẩn bị một bộ dữ liệu demo đủ để mở nhanh các trang tác nghiệp và báo cáo Pushsale. */
    public function demoUi(bool $reset = true): array
    {
        $commands = [];
        $commands[] = $this->runArtisan('migrate', ['--force' => true]);
        $commands[] = $this->seedDemoSafely();
        $commands[] = $this->runArtisan('reports:aggregate-daily', ['date' => 'today']);
        $commands[] = $this->runArtisan('reports:verify-facts', ['--days' => 14]);

        $baseUrl = rtrim((string) config('staging_test.base_url'), '/');
        $ok = collect($commands)->every(static fn ($row) => (int) ($row['exit_code'] ?? 1) === 0);

        $accounts = User::withoutTenant()
            ->select(['name', 'email', 'role'])
            ->orderBy('role')
            ->orderBy('email')
            ->limit(30)
            ->get()
            ->map(fn (User $user) => [
                'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values();

        $sampleUpsale = Order::withoutTenant()
            ->whereHas('items', fn ($query) => $query->where('item_type', 'upsell')->orWhere('origin', 'like', '%upsale%')->orWhere('origin', 'like', '%upsell%'))
            ->with(['marketingSource:id,name,ad_channel', 'items:id,order_id,product_name,item_type,origin,quantity,unit_price'])
            ->latest('closed_at')
            ->limit(5)
            ->get()
            ->map(fn (Order $order) => [
                'order_code' => $order->order_code,
                'source' => $order->marketingSource?->name,
                'customer' => $order->customer_name,
                'total' => (int) $order->total,
                'items' => $order->items->map(fn (OrderItem $item) => [
                    'name' => $item->product_name,
                    'type' => $item->item_type,
                    'origin' => $item->origin,
                    'quantity' => (int) $item->quantity,
                    'line_total' => $item->lineTotal(),
                ])->values(),
            ])->values();

        $url = static fn (string $path): string => $baseUrl.'/'.ltrim($path, '/');

        return [
            'ok' => $ok,
            'generated_at' => now()->toISOString(),
            'commands' => $commands,
            'counts' => $this->safeCounts(),
            'accounts' => $accounts,
            'operation_pages' => [
                'admin_dashboard' => $url('/admin/dashboard'),
                'marketing_dashboard' => $url('/admin/marketing/dashboard'),
                'customer_profile' => $url('/admin/marketing/customers'),
                'sale_workspace' => $url('/admin/sales/workspace'),
                'warehouse_workspace' => $url('/admin/warehouse/operations'),
                'accounting_workspace' => $url('/admin/accounting'),
            ],
            'report_pages' => [
                '2.7.5_bao_cao_cong_viec' => $url('/ld/thong-ke/bao-cao-cong-viec-mkt?menu=2.7.5'),
                '2.8.1_thong_ke_truong_nhom' => $url('/ld/marketing/thong-ke-truong-nhom'),
                '2.8.2_bao_cao_cong_viec' => $url('/ld/thong-ke/bao-cao-cong-viec-mkt?menu=2.8.2'),
                '2.8.3_bao_cao_up_sale' => $url('/ld/thong-ke/bao-cao-up-sale?menu=2.8.3'),
                '8.1.1_bieu_do_theo_gio' => $url('/ld/thong-ke'),
                '8.1.2_bao_cao_doanh_so_marketing' => $url('/bao-cao/bao-cao-doanh-so-chi-tiet-marketing'),
                '8.1.3_bao_cao_up_sale' => $url('/ld/thong-ke/bao-cao-up-sale?menu=8.1.3'),
            ],
            'sample_upsale_orders' => $sampleUpsale,
            'note' => 'Dữ liệu demo đi từ lead -> sale tác nghiệp -> chốt đơn -> kho trừ/nhập -> báo cáo; một phần đơn có item_type=upsell để kiểm tra báo cáo up sale.',
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
    public function scanPages(?array $urls = null, bool $allStatic = false, int $failedLimit = 20): array
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
                    || str_contains($body, 'Stack trace')
                    || str_contains($body, 'Trang này đang thiếu dữ liệu')
                    || str_contains($body, 'Không tải được dữ liệu bộ lọc');

                $ok = $response->status() >= 200 && $response->status() < 400 && ! $hasPhpError;
                $errorType = $this->classifySmokeFailure($response->status(), $hasPhpError, null);

                $results[] = [
                    'url' => $url,
                    'status' => $response->status(),
                    'ok' => $ok,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                    'title' => $title,
                    'inertia_root' => $hasInertiaRoot,
                    'bytes' => strlen($body),
                    'error_type' => $ok ? null : $errorType,
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
                    'error_type' => 'exception',
                    'error_hint' => get_class($e).': '.$e->getMessage(),
                ];
            }
        }

        $failed = array_values(array_filter($results, static fn ($row) => ! (bool) $row['ok']));
        $summary = $this->summarizeSmokeResults($results, $failedLimit);

        return [
            'ok' => count($failed) === 0,
            'base_url' => $baseUrl,
            'all_static' => $allStatic,
            'generated_at' => now()->toISOString(),
            'total' => count($results),
            'passed' => count($results) - count($failed),
            'failed' => count($failed),
            'summary' => $summary,
            'summary_text' => $this->formatSmokeSummaryText('pages:scan', $summary),
            'failed_results' => array_slice($failed, 0, $failedLimit),
            'results' => $results,
        ];
    }


    /** @return array<string, mixed> */
    public function scanRoutableViewRoutes(bool $queryNoise = true, int $limit = 320, int $failedLimit = 20): array
    {
        $urls = $this->routableViewUrls($queryNoise, $limit);
        $result = $this->scanInternalPages($urls, $failedLimit);
        $result['route_smoke'] = true;
        $result['query_noise'] = $queryNoise;
        $result['generated_urls'] = $urls;

        return $result;
    }

    /**
     * Route smoke phải chạy bên trong Laravel kernel với user thật theo role.
     * Nếu dùng HTTP ngoài server mà chưa bật auto-login thì 300+ route protected
     * sẽ trả 401 và che mất lỗi 500 thật.
     *
     * @param list<string> $urls
     * @return array<string, mixed>
     */
    private function scanInternalPages(array $urls, int $failedLimit = 20): array
    {
        $results = [];

        foreach ($urls as $url) {
            $started = microtime(true);
            $url = '/'.ltrim((string) $url, '/');

            try {
                $user = $this->userForSmokeUrl($url);
                $response = $this->dispatchInternalGet($url, $user);
                $status = $response->getStatusCode();
                $body = '';
                try {
                    $content = method_exists($response, 'getContent') ? $response->getContent() : '';
                    $body = is_string($content) ? $content : '';
                } catch (\Throwable) {
                    $body = '';
                }
                $title = $this->extractTitle($body);
                $hasInertiaRoot = str_contains($body, 'data-page=') || str_contains($body, 'id="app"');
                $hasPhpError = str_contains($body, 'Whoops')
                    || str_contains($body, 'Symfony\Component\ErrorHandler')
                    || str_contains($body, 'Illuminate\Database')
                    || str_contains($body, 'Stack trace')
                    || str_contains($body, 'Trang này đang thiếu dữ liệu')
                    || str_contains($body, 'Không tải được dữ liệu bộ lọc');

                $ok = $status >= 200 && $status < 400 && ! $hasPhpError;
                $errorType = $this->classifySmokeFailure($status, $hasPhpError, null);

                $results[] = [
                    'url' => $url,
                    'status' => $status,
                    'ok' => $ok,
                    'ms' => (int) round((microtime(true) - $started) * 1000),
                    'title' => $title,
                    'inertia_root' => $hasInertiaRoot,
                    'bytes' => strlen($body),
                    'user' => $user ? ['id' => $user->id, 'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role] : null,
                    'error_type' => $ok ? null : $errorType,
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
                    'user' => null,
                    'error_type' => 'exception',
                    'error_hint' => get_class($e).': '.$e->getMessage(),
                ];
            }
        }

        $failed = array_values(array_filter($results, static fn ($row) => ! (bool) $row['ok']));
        $summary = $this->summarizeSmokeResults($results, $failedLimit);

        return [
            'ok' => count($failed) === 0,
            'base_url' => config('app.url'),
            'internal_kernel' => true,
            'generated_at' => now()->toISOString(),
            'total' => count($results),
            'passed' => count($results) - count($failed),
            'failed' => count($failed),
            'summary' => $summary,
            'summary_text' => $this->formatSmokeSummaryText('routes:view-smoke', $summary),
            'failed_results' => array_slice($failed, 0, $failedLimit),
            'results' => $results,
        ];
    }

    private function dispatchInternalGet(string $url, ?User $user): \Symfony\Component\HttpFoundation\Response
    {
        $kernel = app(HttpKernel::class);
        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $https = str_starts_with($appUrl, 'https://');

        $request = HttpRequest::create($url, 'GET', [], [], [], [
            'HTTP_HOST' => $host,
            'HTTPS' => $https ? 'on' : 'off',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $request->setLaravelSession(app('session')->driver());

        Auth::logout();
        if ($user) {
            Auth::login($user);
            $request->setUserResolver(static fn () => $user);
        }

        // Route-smoke chạy trong CLI/staging với APP_DEBUG=false nên Laravel thường
        // render trang lỗi chung “Liên hệ …” và che mất exception thật. Bật debug
        // tạm trong đúng request nội bộ này để failed_top in ra SQL/Controller error
        // đủ ngắn cho việc copy log, rồi khôi phục config ngay sau đó.
        $originalDebug = config('app.debug');
        config(['app.debug' => true]);

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            return $response;
        } finally {
            config(['app.debug' => $originalDebug]);
            Auth::logout();
        }
    }

    private function userForSmokeUrl(string $url): ?User
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?: $url);
        $role = User::ROLE_ADMIN;

        if (str_starts_with($path, '/sales') || str_contains($path, '/ld/sale/')) {
            $role = User::ROLE_SALES;
        } elseif (str_starts_with($path, '/marketing') || str_contains($path, '/ld/marketing') || str_contains($path, '/bao-cao/bao-cao-doanh-so-chi-tiet-marketing')) {
            $role = User::ROLE_MARKETING;
        } elseif (str_starts_with($path, '/warehouse') || str_contains($path, '/warehouse/')) {
            $role = User::ROLE_WAREHOUSE;
        } elseif (str_starts_with($path, '/accounting') || str_contains($path, '/accounting')) {
            $role = User::ROLE_ACCOUNTING;
        }

        return $this->sampleUserByRole($role)
            ?: $this->sampleUserByRole(User::ROLE_ADMIN)
            ?: User::withoutTenant()->orderBy('id')->first();
    }

    private function sampleUserByRole(string $role): ?User
    {
        try {
            $query = User::withoutTenant()->where('role', $role)->orderBy('id');
            if (Schema::hasColumn('users', 'is_active')) {
                $query->where('is_active', true);
            }

            return $query->first();
        } catch (\Throwable) {
            return null;
        }
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
            'success_url' => $baseUrl.'/qa/hoan-tat-'.$suffix.'?ps_flow={flow_token}',
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
            'redirect_url' => $baseUrl.'/qa/hoan-tat-'.$suffix,
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
            return $this->runArtisan('db:seed', ['--class' => FullBusinessDemoSeeder::class, '--force' => true]);
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
            'warehouses' => [Warehouse::class, 'warehouses'],
            'inventories' => [WarehouseInventory::class, 'warehouse_inventories'],
            'inventory_movements' => [WarehouseInventoryMovement::class, 'warehouse_inventory_movements'],
            'warehouse_vouchers' => [WarehouseVoucher::class, 'warehouse_vouchers'],
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


    /** @return list<string> */
    private function routableViewUrls(bool $queryNoise = true, int $limit = 320): array
    {
        $urls = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = trim($route->uri(), '/');
            $name = (string) ($route->getName() ?? '');
            if ($this->shouldSkipRouteSmoke($uri, $name)) {
                continue;
            }

            $materialized = $this->materializeRouteUri($uri);
            if ($materialized === null) {
                continue;
            }

            $url = '/'.ltrim($materialized, '/');
            $url = $url === '/' ? '/' : rtrim($url, '/');
            if ($queryNoise && $this->shouldAppendRouteSmokeQuery($url)) {
                $separator = str_contains($url, '?') ? '&' : '?';
                $url .= $separator.http_build_query([
                    '_qa_route_smoke' => 1,
                    'page' => 1,
                    'per_page' => 20,
                    'search' => 'QA route smoke',
                    'sort' => 'created_desc',
                ]);
            }

            $urls[] = $url;
            if (count($urls) >= max(1, $limit)) {
                break;
            }
        }

        return $this->normalizeUrls($urls);
    }

    private function shouldAppendRouteSmokeQuery(string $url): bool
    {
        return str_starts_with($url, '/admin/')
            || str_starts_with($url, '/bao-cao/')
            || str_starts_with($url, '/ld/')
            || str_starts_with($url, '/docs');
    }

    private function shouldSkipRouteSmoke(string $uri, string $name): bool
    {
        $path = '/'.ltrim($uri, '/');
        $lower = strtolower($path.' '.$name);

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
            'product' => $this->sampleModelId(Product::class) ?? 1,
            'warehouse' => $this->sampleModelId(Warehouse::class) ?? 1,
            'order' => $this->sampleModelId(Order::class) ?? 1,
            'lead_ingestion' => $this->sampleModelId(LeadIngestion::class) ?? 1,
            'shipment' => $this->sampleModelId(Shipment::class) ?? 1,
            'user', 'admin' => $this->sampleModelId(User::class) ?? 1,
            'company', 'company_id' => $this->sampleModelId(Company::class) ?? 1,
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
            return $this->sampleProductIdByType('combo');
        }
        if (str_contains($lower, 'warehouse/vouchers')) {
            return $this->sampleModelId(WarehouseVoucher::class);
        }

        return null;
    }

    /** @param class-string $model */
    private function sampleModelId(string $model): ?int
    {
        try {
            return (int) ($this->modelQueryWithoutTenant($model)->orderBy('id')->value('id') ?: 0) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function sampleProductIdByType(string $type): ?int
    {
        try {
            return (int) (Product::withoutTenant()->where('type', $type)->orderBy('id')->value('id') ?: 0) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function sampleTableId(string $table): ?int
    {
        try {
            if (! Schema::hasTable($table)) {
                return null;
            }

            return (int) (DB::table($table)->orderBy('id')->value('id') ?: 0) ?: null;
        } catch (\Throwable) {
            return null;
        }
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

    /**
     * @param list<array<string, mixed>> $results
     * @return array<string, mixed>
     */
    private function summarizeSmokeResults(array $results, int $failedLimit = 20): array
    {
        $total = count($results);
        $failedRows = array_values(array_filter($results, static fn (array $row): bool => ! (bool) ($row['ok'] ?? false)));
        $statusCounts = [];
        $errorCounts = [];

        foreach ($results as $row) {
            $status = (string) ((int) ($row['status'] ?? 0));
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if (! (bool) ($row['ok'] ?? false)) {
                $type = (string) ($row['error_type'] ?? $this->classifySmokeFailure((int) ($row['status'] ?? 0), false, (string) ($row['error_hint'] ?? '')));
                $errorCounts[$type] = ($errorCounts[$type] ?? 0) + 1;
            }
        }

        ksort($statusCounts);
        arsort($errorCounts);

        $failedTop = array_map(function (array $row): array {
            $compact = [
                'url' => (string) ($row['url'] ?? ''),
                'status' => (int) ($row['status'] ?? 0),
                'ms' => (int) ($row['ms'] ?? 0),
                'type' => (string) ($row['error_type'] ?? 'unknown'),
                'hint' => $this->shortSmokeHint((string) ($row['error_hint'] ?? '')),
            ];

            if (isset($row['user']) && is_array($row['user'])) {
                $compact['user'] = trim(($row['user']['role'] ?? 'user').'#'.($row['user']['id'] ?? ''));
            }

            return $compact;
        }, array_slice($failedRows, 0, max(1, min(100, $failedLimit))));

        return [
            'total' => $total,
            'passed' => $total - count($failedRows),
            'failed' => count($failedRows),
            'status_counts' => $statusCounts,
            'error_counts' => $errorCounts,
            'failed_top' => $failedTop,
            'copy_hint' => 'Copy block này lên ChatGPT là đủ: summary + failed_top, không cần paste toàn bộ generated_urls/results.',
        ];
    }

    /** @param array<string, mixed> $summary */
    private function formatSmokeSummaryText(string $name, array $summary): string
    {
        $lines = [];
        $lines[] = strtoupper($name).' SUMMARY';
        $lines[] = sprintf(
            'total=%d passed=%d failed=%d',
            (int) ($summary['total'] ?? 0),
            (int) ($summary['passed'] ?? 0),
            (int) ($summary['failed'] ?? 0),
        );
        $lines[] = 'status_counts='.json_encode($summary['status_counts'] ?? [], JSON_UNESCAPED_UNICODE);
        $lines[] = 'error_counts='.json_encode($summary['error_counts'] ?? [], JSON_UNESCAPED_UNICODE);

        $failedTop = $summary['failed_top'] ?? [];
        if (is_array($failedTop) && $failedTop !== []) {
            $lines[] = 'failed_top=';
            foreach ($failedTop as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $lines[] = sprintf(
                    '%02d. [%s/%s] %s (%sms) user=%s hint=%s',
                    $index + 1,
                    (string) ($row['status'] ?? 0),
                    (string) ($row['type'] ?? 'unknown'),
                    (string) ($row['url'] ?? ''),
                    (string) ($row['ms'] ?? 0),
                    (string) ($row['user'] ?? '-'),
                    (string) ($row['hint'] ?? ''),
                );
            }
        }

        $lines[] = 'copy_hint='.($summary['copy_hint'] ?? '');

        return implode(PHP_EOL, $lines);
    }

    private function classifySmokeFailure(int $status, bool $hasPhpError, ?string $hint): ?string
    {
        if ($hasPhpError) {
            return 'php_error_signature';
        }

        if ($status === 0) {
            return 'exception';
        }

        if ($status === 401) {
            return 'unauthenticated_401';
        }

        if ($status === 403) {
            return 'forbidden_403';
        }

        if ($status === 404) {
            return 'not_found_404';
        }

        if ($status >= 500) {
            return 'http_5xx';
        }

        if ($status >= 400) {
            return 'http_4xx';
        }

        if ($hint && $hint !== '') {
            return 'content_error';
        }

        return null;
    }

    private function shortSmokeHint(string $hint): string
    {
        $hint = trim(preg_replace('/\s+/', ' ', $hint) ?: '');
        if ($hint === '') {
            return '';
        }

        return mb_substr($hint, 0, 220);
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
