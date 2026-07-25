<?php

namespace App\Http\Controllers\Admin\Marketing;

use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\LandingConnection;
use App\Models\Company;
use App\Models\LandingConnectionSource;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\Marketing\LandingConnectionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as LaravelValidator;
use App\Support\TenantManager;
use Throwable;
use Inertia\Inertia;
use Inertia\Response;

final class LandingConnectionsController extends Controller
{
    public function __construct(private readonly LandingConnectionManager $manager) {}

    public function index(Request $request): Response
    {
        $this->authorizeView($request->user());

        $isWebsiteRoute = $request->is('admin/marketing/website-connections*');
        $routeUrl = $isWebsiteRoute ? '/admin/marketing/website-connections' : '/admin/marketing/landing-connections';
        $recordsUrl = $routeUrl.'/records';
        $activeMenuCode = $isWebsiteRoute ? '2.4.2' : '2.4.1';

        $query = LandingConnection::query()
            ->with(array_merge($this->manager->relations(), ['updatedBy:id,name,email']))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $keyword = trim((string) $request->input('search'));
                $query->where(function ($query) use ($keyword): void {
                    $query->where('name', 'like', "%{$keyword}%")
                        ->orWhereHas('sources', fn ($source) => $source->where('source_url', 'like', "%{$keyword}%"));
                });
            })
            ->when($request->filled('marketer_user_id'), fn ($query) => $query->where('marketer_user_id', $request->integer('marketer_user_id')))
            ->when($request->filled('product_id'), fn ($query) => $query->whereHas('products', fn ($product) => $product->where('product_id', $request->integer('product_id'))))
            ->when($request->filled('connection_type'), fn ($query) => $query->where('connection_type', $request->string('connection_type')->value()))
            ->when($request->filled('ad_channel'), fn ($query) => $query->where('ad_channel', $request->string('ad_channel')->value()))
            ->when($request->filled('approved'), fn ($query) => $query->where('is_approved', $request->boolean('approved')))
            ->when($request->filled('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->latest('id');

        $perPage = max(10, min(100, $request->integer('per_page', 20)));
        $connections = $query->paginate($perPage)->withQueryString()->through(
            fn (LandingConnection $connection): array => $this->serialize($connection),
        );

        return Inertia::render('Pushsale/Pages/Marketing/LandingConnectionsPage', [
            'connections' => $connections,
            'filters' => $request->only(['search', 'marketer_user_id', 'product_id', 'connection_type', 'ad_channel', 'approved', 'active', 'per_page']),
            'routeUrl' => $routeUrl,
            'recordsUrl' => $recordsUrl,
            'marketers' => User::query()->whereIn('role', [UserRole::Marketing, UserRole::Admin])->orderBy('name')->get(['id', 'name', 'email']),
            'sales' => User::query()->where('role', UserRole::Sales)->orderBy('name')->get(['id', 'name', 'email', 'team_id']),
            'saleTeams' => Team::query()->where('type', 'sale')->with('users:id,name,email,team_id')->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->where('is_active', true)->where('available_marketing', true)->orderBy('type')->orderBy('name')->get(['id', 'name', 'sku', 'type', 'unit_price']),
            'canManage' => $this->canManage($request->user()),
            'canApprove' => $this->canApprove($request->user()),
            'activeMenuCode' => $activeMenuCode,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request->user());

        try {
            $payload = $this->validated($request);
            // Luồng mới: tạo nguồn landing trước, duyệt/gắn sản phẩm ở menu duyệt riêng.
            // Vì vậy form tạo không được tự bật duyệt để tránh 500/validation khi chưa có sản phẩm.
            if (empty($payload['products'])) {
                $payload['is_approved'] = false;
            }

            $this->manager->create($payload, $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('landing_connections.store_failed', [
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $message = app()->isProduction()
                ? 'Không lưu được kết nối landing. Chi tiết đã ghi vào log.'
                : get_class($exception).': '.$exception->getMessage();

            return back()
                ->withInput()
                ->withErrors(['landing_connection' => $message])
                ->with('error', 'Không lưu được kết nối landing.');
        }

        return redirect('/admin/marketing/landing-connections')->with('success', 'Đã tạo kết nối landing. Chờ duyệt ở menu duyệt kết nối để gắn sản phẩm/gói và ngân sách.');
    }

    public function update(Request $request, LandingConnection $record): RedirectResponse
    {
        $this->authorizeManage($request->user());

        try {
            $payload = $this->validated($request);
            if (empty($payload['products'])) {
                $payload['is_approved'] = false;
            }

            $this->manager->update($record, $payload, $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('landing_connections.update_failed', [
                'record_id' => $record->id,
                'user_id' => $request->user()?->id,
                'company_id' => $request->user()?->company_id,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            $message = app()->isProduction()
                ? 'Không cập nhật được kết nối landing. Chi tiết đã ghi vào log.'
                : get_class($exception).': '.$exception->getMessage();

            return back()
                ->withInput()
                ->withErrors(['landing_connection' => $message])
                ->with('error', 'Không cập nhật được kết nối landing.');
        }

        return redirect('/admin/marketing/landing-connections')->with('success', 'Đã cập nhật kết nối landing.');
    }

    public function destroy(Request $request, LandingConnection $record): RedirectResponse
    {
        $this->authorizeManage($request->user());
        $this->manager->delete($record);

        return back()->with('success', 'Đã ngừng và xóa kết nối landing.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $this->authorizeManage($request->user());

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $records = LandingConnection::query()->whereIn('id', $validated['ids'])->get();
        foreach ($records as $record) {
            $this->manager->delete($record);
        }

        return back()->with('success', 'Đã ngừng và xóa '.$records->count().' kết nối landing.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $companyId = $this->resolveCompanyId($request->user());

        if (! $request->filled('marketer_user_id')) {
            $fallbackMarketer = $request->user()?->role === UserRole::Marketing
                ? $request->user()
                : User::query()
                    ->where('company_id', $companyId)
                    ->whereIn('role', [UserRole::Marketing, UserRole::Admin])
                    ->orderBy('name')
                    ->first();

            if ($fallbackMarketer) {
                $request->merge(['marketer_user_id' => $fallbackMarketer->id]);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'marketer_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->whereIn('role', [UserRole::Marketing->value, UserRole::Admin->value])),
            ],
            'connection_type' => ['required', Rule::in(['landing', 'website', 'facebook'])],
            'ad_channel' => ['nullable', 'string', 'max:64'],
            'allocation_method' => ['required', Rule::in(['inherit', 'round_robin', 'priority', 'manual'])],
            'budget_type' => ['nullable', Rule::in(['total', 'daily'])],
            'budget_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999999'],
            'budget_start_date' => ['nullable', 'date'],
            'budget_end_date' => ['nullable', 'date', 'after_or_equal:budget_start_date'],
            'success_url' => ['nullable', 'url:http,https', 'max:2048'],
            'manual_import' => ['boolean'],
            'is_approved' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sources' => ['required', 'array', 'min:1', 'max:20'],
            'sources.*.id' => ['nullable', 'integer'],
            'sources.*.client_key' => ['required', 'string', 'max:64'],
            'sources.*.name' => ['required', 'string', 'max:255'],
            'sources.*.source_type' => ['required', Rule::in([LandingConnectionSource::TYPE_MAIN, LandingConnectionSource::TYPE_UPSELL])],
            'sources.*.source_url' => ['required', 'url:http,https', 'max:2048'],
            'sources.*.redirect_url' => ['nullable', 'url:http,https', 'max:2048'],
            'sources.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'sources.*.is_active' => ['boolean'],
            'sources.*.notes' => ['nullable', 'string', 'max:500'],
            'products' => ['nullable', 'array', 'max:100'],
            'products.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->where('available_marketing', true)),
            ],
            'products.*.source_key' => ['nullable', 'string', 'max:64'],
            'products.*.item_type' => ['required', Rule::in(['product', 'combo', 'upsell', 'gift'])],
            'products.*.external_field' => ['nullable', 'string', 'max:255'],
            'products.*.external_value' => ['nullable', 'string', 'max:255'],
            'products.*.quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'products.*.unit_price_override' => ['nullable', 'integer', 'min:0'],
            'products.*.is_default' => ['boolean'],
            'sale_user_ids' => ['nullable', 'array', 'max:200'],
            'sale_user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('role', UserRole::Sales->value)),
            ],
        ]);

        $validator->after(function (LaravelValidator $validator) use ($request): void {
            $sources = collect($request->input('sources', []))->filter(fn ($row): bool => is_array($row))->values();
            $products = collect($request->input('products', []))->filter(fn ($row): bool => is_array($row))->values();
            $sourceKeys = $sources->pluck('client_key')->filter()->map('strval')->values();

            if ((int) $request->input('budget_amount', 0) > 0) {
                if (! $request->filled('budget_start_date') || ! $request->filled('budget_end_date')) {
                    $validator->errors()->add('budget_start_date', 'Ngân sách lớn hơn 0 phải có đầy đủ ngày bắt đầu và ngày kết thúc.');
                }
            }

            if ($sources->where('source_type', LandingConnectionSource::TYPE_MAIN)->count() !== 1) {
                $validator->errors()->add('sources', 'Mỗi kết nối phải có đúng 1 Landing chính.');
            }

            if ($sourceKeys->unique()->count() !== $sourceKeys->count()) {
                $validator->errors()->add('sources', 'Mã nội bộ của các nguồn landing không được trùng nhau.');
            }

            $products = $products
                ->filter(fn ($product): bool => is_array($product) && filled($product['product_id'] ?? null))
                ->values();

            $catalogTypes = Product::query()
                ->whereIn('id', $products->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values())
                ->pluck('type', 'id');

            foreach ($products as $index => $product) {
                $sourceKey = (string) ($product['source_key'] ?? '');
                if ($sourceKey !== '' && ! $sourceKeys->contains($sourceKey)) {
                    $validator->errors()->add("products.{$index}.source_key", 'Nguồn áp dụng của sản phẩm không còn tồn tại.');
                }

                $field = trim((string) ($product['external_field'] ?? ''));
                $value = trim((string) ($product['external_value'] ?? ''));
                if (($field === '') xor ($value === '')) {
                    $validator->errors()->add("products.{$index}.external_field", 'Tên field và giá trị field phải được khai báo cùng nhau.');
                }

                $productId = (int) ($product['product_id'] ?? 0);
                $catalogType = (string) ($catalogTypes[$productId] ?? 'product');
                $itemType = (string) ($product['item_type'] ?? 'product');
                if ($catalogType === 'combo' && $itemType !== 'combo') {
                    $validator->errors()->add("products.{$index}.item_type", 'Gói sản phẩm trong catalog phải được lưu với loại gói sản phẩm.');
                }
                if ($catalogType !== 'combo' && $itemType === 'combo') {
                    $validator->errors()->add("products.{$index}.item_type", 'Sản phẩm đơn không được lưu dưới loại gói sản phẩm.');
                }
            }

            // Luồng mới: Marketing tạo kết nối landing trước, chưa cần gắn sản phẩm.
            // Bước duyệt riêng sẽ gắn sản phẩm/gói sản phẩm + ngân sách trước khi bật nhận data thật.
            if ($request->boolean('is_approved') && $products->isEmpty()) {
                $validator->errors()->add('products', 'Kết nối chỉ được duyệt sau khi đã gắn ít nhất 1 sản phẩm hoặc gói sản phẩm.');
            }

            if ($products->isNotEmpty()) {
                foreach ($sources->whereIn('source_type', ['main', 'upsell']) as $sourceIndex => $source) {
                    $key = (string) ($source['client_key'] ?? '');
                    $applicableProducts = $products->filter(function ($product) use ($key): bool {
                        $sourceKey = (string) ($product['source_key'] ?? '');

                        return $sourceKey === '' || $sourceKey === $key;
                    });

                    if ($applicableProducts->isEmpty()) {
                        $validator->errors()->add("sources.{$sourceIndex}.name", 'Nguồn nhận form phải có ít nhất 1 sản phẩm/gói áp dụng trước khi duyệt.');
                        continue;
                    }

                    $hasSafeFallback = $applicableProducts->contains(function ($product): bool {
                        return trim((string) ($product['external_field'] ?? '')) === ''
                            || (bool) ($product['is_default'] ?? false);
                    });

                    if (! $hasSafeFallback) {
                        $validator->errors()->add("sources.{$sourceIndex}.name", 'Nguồn nhận form phải có ít nhất 1 gói cố định hoặc gói mặc định để tránh mất sản phẩm khi landing gửi sai giá trị.');
                    }
                }
            }
        });

        return $validator->validate();
    }


    private function resolveCompanyId(?User $user): int
    {
        $candidate = (int) ($user?->company_id ?? 0);
        if ($candidate > 0) {
            return $candidate;
        }

        $tenantId = (int) (app(TenantManager::class)->id() ?? 0);
        if ($tenantId > 0) {
            return $tenantId;
        }

        $company = Company::query()
            ->where(function ($query): void {
                if (Schema::hasColumn('companies', 'is_internal')) {
                    $query->where('is_internal', true);
                }
            })
            ->orderBy('id')
            ->first(['id']);

        if (! $company) {
            $company = Company::query()->orderBy('id')->first(['id']);
        }

        if (! $company) {
            throw ValidationException::withMessages([
                'company_id' => 'Chưa có công ty/đơn vị để tạo kết nối landing.',
            ]);
        }

        return (int) $company->id;
    }

    /** @return array<string, mixed> */
    private function serialize(LandingConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'name' => $connection->name,
            'marketer_user_id' => $connection->marketer_user_id,
            'marketer' => $connection->marketer?->name,
            'marketer_email' => $connection->marketer?->email,
            'connection_type' => $connection->connection_type,
            'ad_channel' => $connection->ad_channel,
            'allocation_method' => $connection->allocation_method,
            'budget_type' => $connection->budget_type ?: 'total',
            'budget_amount' => (int) $connection->budget_amount,
            'budget_total' => $connection->plannedBudgetTotal(),
            'budget_start_date' => $connection->budget_start_date?->toDateString(),
            'budget_end_date' => $connection->budget_end_date?->toDateString(),
            'success_url' => $connection->success_url,
            'manual_import' => $connection->manual_import,
            'is_approved' => $connection->is_approved,
            'is_active' => $connection->is_active,
            'notes' => (string) ($connection->metadata['notes'] ?? ''),
            'api_base_url' => $connection->apiBaseUrl(),
            'contacts' => (int) ($connection->marketingSource?->contacts ?? 0),
            'sources' => $connection->sources
                ->whereIn('source_type', [LandingConnectionSource::TYPE_MAIN, LandingConnectionSource::TYPE_UPSELL])
                ->map(fn ($source): array => [
                'id' => $source->id,
                'client_key' => (string) ($source->metadata['client_key'] ?? $source->id),
                'name' => $source->name,
                'source_type' => $source->source_type,
                'source_url' => $source->source_url,
                'redirect_url' => $source->redirect_url,
                'sort_order' => $source->sort_order,
                'is_active' => $source->is_active,
                'notes' => (string) ($source->metadata['notes'] ?? ''),
                'submit_url' => $source->acceptsSubmissions()
                    ? url('/api/v1/landing-connections/'.$connection->public_token.'/sources/'.$source->public_token.'/submit')
                    : null,
            ])->values(),
            'products' => $connection->products->map(fn ($mapping): array => [
                'id' => $mapping->id,
                'product_id' => $mapping->product_id,
                'product_name' => $mapping->product?->name,
                'product_sku' => $mapping->product?->sku,
                'product_type' => $mapping->product?->type,
                'source_key' => $mapping->source ? (string) ($mapping->source->metadata['client_key'] ?? $mapping->source->id) : '',
                'item_type' => $mapping->item_type,
                'external_field' => $mapping->external_field,
                'external_value' => $mapping->external_value,
                'quantity' => $mapping->quantity,
                'unit_price_override' => $mapping->unit_price_override,
                'is_default' => $mapping->is_default,
            ])->values(),
            'sale_user_ids' => $connection->sales->pluck('user_id')->values(),
            'sale_names' => $connection->sales->pluck('user.name')->filter()->values(),
            'updated_at' => $connection->updated_at?->format('d/m/Y H:i'),
            'updated_by' => $connection->updatedBy?->name ?? $connection->updatedBy?->email,
        ];
    }

    private function authorizeView(User $user): void
    {
        abort_unless(
            $user->allows(PermissionArea::Marketing, PermissionLevel::View)
                || $user->allows(PermissionArea::Integrations, PermissionLevel::View),
            403,
        );
    }

    private function authorizeManage(User $user): void
    {
        abort_unless($this->canManage($user), 403);
    }

    private function canManage(User $user): bool
    {
        return $user->allows(PermissionArea::Marketing, PermissionLevel::Full)
            || $user->allows(PermissionArea::Integrations, PermissionLevel::Full);
    }

    private function canApprove(User $user): bool
    {
        return $user->isAdmin()
            || $user->allows(PermissionArea::Marketing, PermissionLevel::Full)
            || $user->allows(PermissionArea::Integrations, PermissionLevel::Full);
    }
}

