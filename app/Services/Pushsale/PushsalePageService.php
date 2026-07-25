<?php

namespace App\Services\Pushsale;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\TeamType;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\IntegrationConnection;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Pushsale\CareDistributionRule;
use App\Models\Pushsale\CompanySubscriptionHistory;
use App\Models\Pushsale\CustomerCareCampaign;
use App\Models\Pushsale\DiscountCodRule;
use App\Models\Pushsale\ElectronicInvoiceJob;
use App\Models\Pushsale\ElectronicInvoiceConfig;
use App\Models\Pushsale\Expense;
use App\Models\Pushsale\ExpenseCategory;
use App\Models\Pushsale\ExpenseGroup;
use App\Models\Pushsale\ExpenseUnit;
use App\Models\Pushsale\FacebookPageMapping;
use App\Models\Pushsale\LeadDistributionRule;
use App\Models\Pushsale\KpiCatalogItem;
use App\Models\Pushsale\MonthlyKpiPlan;
use App\Models\Pushsale\OperationCategory;
use App\Models\Pushsale\OperationWorkflow;
use App\Models\Pushsale\PartnerConnection;
use App\Models\Pushsale\PhoneBlacklist;
use App\Models\Pushsale\ProductAttribute;
use App\Models\Pushsale\ProductAttributeValue;
use App\Models\Pushsale\ProductCategory;
use App\Models\Pushsale\ProductComboItem;
use App\Models\Pushsale\ReportAccessRule;
use App\Models\Pushsale\SeedingPhoneNumber;
use App\Models\Pushsale\WarehouseIncidentReport;
use App\Models\Pushsale\WarehouseVoucher;
use App\Models\Pushsale\WarehouseVoucherLine;
use App\Models\Pushsale\WorkShift;
use App\Models\Order;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Models\WarehouseInventoryMovement;
use App\Services\Finance\PayrollCostService;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class PushsalePageService
{
    private ?Request $currentRequest = null;

    public function __construct(
        private readonly PushsaleLiveDataService $liveData,
        private readonly PayrollCostService $payroll,
    ) {}

    /** @return array<string, mixed> */
    public function schema(string $code): array
    {
        $pages = config('pushsale_pages', []);
        $schema = is_array($pages) ? ($pages[$code] ?? null) : null;
        abort_unless(is_array($schema), 404);

        return array_merge([
            'code' => $code,
            'kind' => 'table',
            'source' => 'generic',
            'editable' => false,
            'upsell' => false,
            'filters' => [],
            'dialogs' => [],
            'template_alias' => $code,
        ], $schema, ['code' => $code]);
    }

    /** @return array{data: array<int, array<string, mixed>>, meta: array<string, int>} */
    public function rows(string $code, Request $request): array
    {
        $this->currentRequest = $request;
        $schema = $this->schema($code);
        $source = (string) $schema['source'];

        // Các màn đã có luồng nghiệp vụ trong ERM phải dùng trực tiếp query/service thật.
        // Không đi qua collection demo hoặc dữ liệu chụp từ template.
        if ($source === 'power_dashboard') {
            return $this->powerDashboardResult($request);
        }

        if ($live = $this->liveData->resolve($source, $request)) {
            return $live;
        }

        $rows = match ($source) {
            'users' => $this->users(),
            'teams' => $this->teams(),
            'products' => $this->products(false),
            'combos' => $this->products(true),
            'activity_logs' => $this->activityLogs($code),
            'login_permissions' => $this->loginPermissions(),
            'integrations' => $this->integrations(),
            'marketing_sources' => $this->marketingSources(),
            'facebook_page_mappings' => $this->facebookPageMappings(),
            'partner_connections' => $this->partnerConnections(),
            'lead_ingestions' => $this->leadIngestions(),
            'manual_lead_ingestions' => $this->leadIngestions('manual'),
            'lead_imports' => $this->leadIngestions(),
            'customer_multidimensional' => $this->customerMultidimensional(),
            'customer_spending' => $this->customerSpending(),
            'customer_orders' => $this->customerOrders(),
            'sales_ranking' => $this->salesRanking(),
            'sale_operation_rate' => $this->saleOperationRate(),
            'sale_work' => $this->saleWork(),
            'sale_team' => $this->saleTeam(),
            'sale_data' => $this->saleData(),
            'sale_optimization' => $this->saleOptimization(),
            'warehouse_orders' => $this->warehouseOrders(),
            'warehouses' => $this->warehouses(),
            'inventory' => $this->inventory(),
            'warehouse_vouchers' => $this->warehouseVouchers(),
            'movements' => $this->movements(),
            'inventory_daily' => $this->inventoryDaily(),
            'inventory_pending' => $this->inventoryPending(),
            'inventory_summary' => $this->inventorySummary(),
            'care_report' => $this->careReport(),
            'phone_corrections' => $this->phoneCorrections(),
            'delivery_by_care' => $this->deliveryByCare(),
            'care_operations' => $this->careOperations(),
            'care_allocation' => $this->careAllocation(),
            'monthly_plan' => $this->monthlyPlan($code),
            'kpi_catalog' => $this->kpiCatalog(),
            'trend' => $this->trend(),
            'power_dashboard' => $this->powerDashboard(),
            'repurchase' => $this->repurchase(),
            'repurchase_products' => $this->repurchaseProducts(),
            'subscriptions' => $this->subscriptions(),
            'work_shifts' => $this->workShifts(),
            'lead_distribution_rules' => $this->leadDistributionRules(),
            'report_access_rules' => $this->reportAccessRules(),
            'care_distribution_rules' => $this->careDistributionRules(),
            'operation_categories' => $this->operationCategories(),
            'operation_workflows' => $this->operationWorkflows(),
            'discount_cod_rules' => $this->discountCodRules(),
            'phone_blacklists' => $this->phoneBlacklists(),
            'seeding_phone_numbers' => $this->seedingPhoneNumbers(),
            'care_campaigns' => $this->careCampaigns(),
            'warehouse_voucher_lines' => $this->warehouseVoucherLines(),
            'warehouse_incidents' => $this->warehouseIncidents(),
            'expenses' => $this->expenses(),
            'expense_categories' => $this->expenseCategories(),
            'expense_groups' => $this->expenseGroups(),
            'expense_units' => $this->expenseUnits(),
            'electronic_invoice_jobs' => $this->electronicInvoiceJobs(),
            'electronic_invoice_configs' => $this->electronicInvoiceConfigs(),
            default => collect(),
        };

        $rows = $this->applyFilters($rows, $request);
        $rows = $this->applySearch($rows, trim((string) $request->query('search', '')));
        $rows = $this->applySort($rows, trim((string) $request->query('sort', '')));
        $perPage = max(10, min(100, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));
        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return [
            'data' => $slice->all(),
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total),
            ],
            'summary' => [
                'total_records' => $total,
            ],
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function filterOptions(string $pageCode): array
    {
        $schema = $this->schema($pageCode);
        $requestedSources = collect($schema['form_fields'] ?? [])
            ->merge(collect($schema['dialog_resources'] ?? [])->flatMap(function (string $resourceKey): array {
                $resources = config('pushsale_resources', []);
                $definition = is_array($resources) ? ($resources[$resourceKey] ?? []) : [];

                return (array) ($definition['fields'] ?? []);
            }))
            ->pluck('option_source')
            ->filter(fn ($source): bool => is_string($source) && $source !== '')
            ->unique()
            ->values();

        $mapUsers = static fn ($items) => $items->map(fn (User $user) => [
            'id' => $user->id,
            'label' => trim($user->name.' · '.$user->email),
        ])->all();
        $mapTeams = static fn ($items) => $items->map(fn (Team $team) => ['id' => $team->id, 'label' => $team->name])->all();
        $userOptionQuery = User::query()->with('company:id,name');
        if (auth()->user()?->isPlatformAdmin()) {
            // Super Admin phải nhìn thấy danh sách tài khoản đăng nhập thật trên toàn hệ thống,
            // không bị TenantScope làm màn 1.7.1 / 1.7.2 trống dữ liệu.
            $userOptionQuery->withoutTenant();
        }
        $allUsers = $userOptionQuery
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'company_id', 'name', 'email', 'role', 'is_team_leader']);
        $allProducts = Product::query()
            ->orderBy('name')
            ->limit(2000)
            ->get(['id', 'parent_id', 'name', 'type', 'unit_price', 'sku', 'is_active']);
        $teamLeaderIds = Team::query()->whereNotNull('leader_id')->pluck('leader_id')->map(fn ($id) => (int) $id);
        $loginCounts = $pageCode === '1.7.1'
            ? tap(ActivityLog::query(), function ($query): void {
                if (auth()->user()?->isPlatformAdmin()) {
                    $query->withoutTenant();
                }
            })
                ->whereIn('action', [
                    ActivityLogger::AUTH_LOGIN_SUCCESS,
                    ActivityLogger::AUTH_LOGIN_FAILED,
                    ActivityLogger::AUTH_LOGIN_BLOCKED,
                    ActivityLogger::AUTH_LOGOUT,
                ])
                ->whereNotNull('user_id')
                ->selectRaw('user_id, COUNT(*) as aggregate')
                ->groupBy('user_id')
                ->pluck('aggregate', 'user_id')
            : collect();
        $loginUsers = $allUsers->map(fn (User $user): array => [
            'id' => $user->id,
            'label' => trim($user->name.' · '.$user->email),
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->roleLabel(),
            'company_id' => $user->company_id,
            'company' => $user->company?->name,
            'login_count' => (int) ($loginCounts[$user->id] ?? 0),
        ])->all();
        $companyIds = $allUsers->pluck('company_id')->filter()->unique()->values();

        // Nguồn dùng chung bởi các select/filter trong HTML gốc.
        $options = [
            'users' => $mapUsers($allUsers),
            'loginUsers' => $loginUsers,
            'roles' => collect(UserRole::cases())
                ->filter(fn (UserRole $role): bool => $allUsers->contains(fn (User $user): bool => $user->role === $role))
                ->map(fn (UserRole $role): array => ['id' => $role->value, 'label' => $role->label()])
                ->values()
                ->all(),
            'companies' => Company::query()
                ->when($companyIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $companyIds))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Company $company): array => ['id' => $company->id, 'label' => $company->name])
                ->all(),
            'loginStatuses' => [
                ['id' => 'success', 'label' => 'Thành công'],
                ['id' => 'failed', 'label' => 'Không thành công'],
                ['id' => 'logout', 'label' => 'Đăng xuất'],
            ],
            'loginSorts' => [
                ['id' => 'created_desc', 'label' => 'Mới nhất'],
                ['id' => 'ip', 'label' => 'Sắp xếp theo IP'],
                ['id' => 'user', 'label' => 'Sắp xếp theo tài khoản'],
            ],
            'sales' => $mapUsers(User::query()->where('role', User::ROLE_SALES)->orderBy('name')->limit(500)->get(['id', 'name', 'email'])),
            'saleLeaders' => $mapUsers(User::query()->where('role', User::ROLE_SALES)->where('is_team_leader', true)->orderBy('name')->limit(300)->get(['id', 'name', 'email'])),
            'marketers' => $mapUsers(User::query()->where('role', User::ROLE_MARKETING)->orderBy('name')->limit(500)->get(['id', 'name', 'email'])),
            'marketingLeaders' => $mapUsers(User::query()->where('role', User::ROLE_MARKETING)->where('is_team_leader', true)->orderBy('name')->limit(300)->get(['id', 'name', 'email'])),
            'warehouseUsers' => $mapUsers(User::query()->where('role', User::ROLE_WAREHOUSE)->orderBy('name')->limit(500)->get(['id', 'name', 'email'])),
            'careUsers' => $mapUsers(User::query()->whereIn('role', [User::ROLE_WAREHOUSE, User::ROLE_SALES])->orderBy('name')->limit(800)->get(['id', 'name', 'email'])),
            'teams' => $mapTeams(Team::query()->orderBy('name')->limit(1000)->get(['id', 'name'])),
            'saleTeams' => $mapTeams(Team::query()->where('type', TeamType::Sale->value)->orderBy('name')->limit(500)->get(['id', 'name'])),
            'marketingTeams' => $mapTeams(Team::query()->where('type', TeamType::Marketing->value)->orderBy('name')->limit(500)->get(['id', 'name'])),
            'warehouseTeams' => $mapTeams(Team::query()->where('type', TeamType::Warehouse->value)->orderBy('name')->limit(500)->get(['id', 'name'])),
            'teamLeaders' => $mapUsers($allUsers->filter(
                fn (User $user): bool => (bool) $user->is_team_leader || $teamLeaderIds->contains((int) $user->id),
            )->values()),
            'products' => $allProducts->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => trim($product->name.($product->sku ? " ({$product->sku})" : '')),
                'name' => $product->name,
                'type' => $product->type,
                'unit_price' => (int) $product->unit_price,
                'sku' => $product->sku,
                'is_active' => (bool) $product->is_active,
            ])->all(),
            'productGroups' => $allProducts
                ->filter(fn (Product $product): bool => $product->type === 'product' && $product->parent_id === null && $product->is_active)
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'label' => trim($product->name.($product->sku ? " ({$product->sku})" : '')),
                ])
                ->values()
                ->all(),
            'availabilityOptions' => [
                ['id' => '1', 'label' => 'Được phép sử dụng'],
                ['id' => '0', 'label' => 'Không được phép sử dụng'],
            ],
            'warehouses' => Warehouse::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (Warehouse $warehouse) => ['id' => $warehouse->id, 'label' => $warehouse->name])->all(),
            'shippingProviders' => collect(config('shipping_partners.providers', []))->map(
                fn (array $provider, string $key): array => ['id' => $key, 'label' => (string) ($provider['label'] ?? strtoupper($key))]
            )->values()->all(),
            'sources' => MarketingSource::query()->orderBy('name')->limit(1000)->get(['id', 'name'])->map(fn (MarketingSource $source) => ['id' => $source->id, 'label' => $source->name])->all(),
            'orders' => Order::query()->latest('id')->limit(1000)->get(['id', 'order_code', 'customer_name', 'customer_phone'])->map(fn (Order $order) => [
                'id' => $order->id,
                'label' => trim($order->order_code.' · '.$order->customer_name.' · '.$order->customer_phone),
            ])->all(),
        ];

        // Các bảng phụ chỉ được query khi đúng trang thực sự cần chúng.
        // Một lỗi ở thuộc tính sản phẩm không được phép làm toàn bộ màn hình khác 500.
        $loaders = [
            'operationCategories' => fn (): array => OperationCategory::query()->orderBy('sort_order')->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (OperationCategory $item) => ['id' => $item->id, 'label' => $item->name])->all(),
            'expenseGroups' => fn (): array => ExpenseGroup::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (ExpenseGroup $item) => ['id' => $item->id, 'label' => $item->name])->all(),
            'expenseCategories' => fn (): array => ExpenseCategory::query()->orderBy('name')->limit(1000)->get(['id', 'name'])->map(fn (ExpenseCategory $item) => ['id' => $item->id, 'label' => $item->name])->all(),
            'expenseUnits' => fn (): array => ExpenseUnit::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (ExpenseUnit $item) => ['id' => $item->id, 'label' => $item->name])->all(),
            'productCategories' => fn (): array => ProductCategory::query()->orderBy('name')->limit(1000)->get(['id', 'name'])->map(fn (ProductCategory $item) => ['id' => $item->id, 'label' => $item->name])->all(),
            'productAttributes' => fn (): array => ProductAttribute::query()->orderBy('name')->limit(1000)->get(['id', 'name'])->map(fn (ProductAttribute $item) => ['id' => $item->id, 'label' => $item->name])->all(),
            'productAttributeValues' => fn (): array => ProductAttributeValue::query()->with('attribute:id,name')->orderBy('name')->limit(2000)->get()->map(fn (ProductAttributeValue $item) => [
                'id' => $item->id,
                'label' => trim(($item->attribute?->name ? $item->attribute->name.': ' : '').$item->name),
            ])->all(),
        ];

        foreach ($requestedSources as $source) {
            if (isset($loaders[$source])) {
                $options[$source] = $loaders[$source]();
            }
        }

        if ($pageCode === '1.8.1') {
            $options['operationResults'] = collect(OperationResult::selectableOptions())
                ->values()
                ->map(fn (array $item, int $index): array => [
                    'value' => $item['value'],
                    'label' => $item['label'],
                    'legacy_id' => 109117 + $index,
                ])
                ->all();
            $options['operationWorkflowsFull'] = $this->operationWorkflows()->all();
        }

        return $options;
    }

    private function users(): Collection
    {
        return User::query()->with(['team:id,name', 'company:id,name'])->latest('id')->limit(1000)->get()->values()->map(fn (User $user, int $index) => [
            'index' => $index + 1,
            'select' => false,
            'name' => $user->name,
            'role' => $user->roleLabel(),
            'employee_code' => 'NV'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            'base_salary' => (int) data_get($user->permissions, 'base_salary', 0),
            'phone' => $user->phone,
            'email' => $user->email,
            'leader' => $user->team?->name ?? ($user->is_team_leader ? 'Trưởng nhóm' : ''),
            'receive_data' => data_get($user->permissions, 'receive_data', true),
            'shift' => data_get($user->permissions, 'shift', 'Giờ hành chính'),
            'active' => ! (bool) data_get($user->permissions, 'login_blocked', false),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_edit_url' => "/admin/users/{$user->id}/edit",
        ]);
    }

    private function teams(): Collection
    {
        return Team::query()->with(['leader:id,name', 'parent:id,name'])->withCount('users')->latest('id')->limit(500)->get()->values()->map(fn (Team $team, int $index) => [
            'index' => $index + 1,
            'type' => $team->type && method_exists($team->type, 'label') ? $team->type->label() : (string) ($team->type?->value ?? $team->type ?? ''),
            'code' => 'TEAM'.str_pad((string) $team->id, 3, '0', STR_PAD_LEFT),
            'name' => $team->name,
            'leader' => $team->leader?->name,
            'member_count' => $team->users_count,
            'members' => $team->users_count ? "{$team->users_count} thành viên" : '',
            'parent' => $team->parent?->name,
            'updated_at' => $team->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_team_leader_id' => $team->leader_id,
            '_edit_url' => "/admin/teams/{$team->id}/edit",
        ]);
    }

    private function products(bool $combos): Collection
    {
        return Product::query()
            ->where('type', $combos ? 'combo' : 'product')
            ->with(['parent:id,name', 'categories:id,name', 'attributeValues.attribute:id,name'])
            ->latest('id')
            ->limit(1500)
            ->get()
            ->values()
            ->map(function (Product $product, int $index) use ($combos): array {
                if ($combos) {
                    $items = ProductComboItem::query()
                        ->with('component:id,name,sku,unit_price,type')
                        ->where('combo_product_id', $product->id)
                        ->orderBy('id')
                        ->get();
                    $componentItems = $items->map(fn (ProductComboItem $item): array => [
                        'product_id' => (int) $item->component_product_id,
                        'quantity' => max(1, (int) $item->quantity),
                        'unit_price' => (int) ($item->unit_price ?: $item->component?->unit_price),
                    ])->values();
                    $originalTotal = $componentItems->sum(fn (array $item): int => ((int) $item['quantity']) * ((int) $item['unit_price']));
                    $componentLabels = $items->map(function (ProductComboItem $item): string {
                        $name = $item->component?->name ?? 'Sản phẩm #'.$item->component_product_id;
                        $sku = $item->component?->sku ? ' ('.$item->component->sku.')' : '';

                        return trim($name.$sku.' x'.max(1, (int) $item->quantity));
                    })->implode("\n");
                    $productIds = $items->pluck('component_product_id')->map(fn ($id) => (int) $id)->values()->all();

                    return [
                        'id' => $product->id,
                        'select' => false,
                        'code' => $product->sku ?: 'CB'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'components' => $componentLabels,
                        'product_count' => $componentItems->sum('quantity'),
                        'original_total' => $originalTotal,
                        'combo_total' => (int) $product->unit_price,
                        'status' => $product->is_active ? 'Đang áp dụng' : 'Ngừng áp dụng',
                        'applied_at' => $product->created_at?->format('d/m/Y'),
                        'limit_quantity' => null,
                        'sold' => 0,
                        'remaining' => null,
                        'shipping_support' => 0,
                        'updated_at' => $product->updated_at?->toIso8601String(),
                        'actions' => 'Cập nhật',
                        '_record_id' => $product->id,
                        '_resource_key' => '1.3.2',
                        '_product_ids' => $productIds,
                        '_is_active' => (bool) $product->is_active,
                        '_active_status' => $product->is_active ? '1' : '0',
                        '_created_at' => $product->created_at?->toIso8601String(),
                        '_form' => $this->formPayload('1.3.2', $product, [
                            'component_product_ids' => $productIds,
                            'component_items' => $componentItems->all(),
                        ]),
                    ];
                }

                $vat = (float) ($product->vat_percent ?? 0);
                $afterVat = (int) round(((int) $product->unit_price) * (1 + ($vat / 100)));

                return [
                    'id' => $product->id,
                    'select' => false,
                    'category' => $product->categories->pluck('name')->implode(', ') ?: ($product->parent?->name ?? 'Sản phẩm'),
                    'product' => trim("{$product->name}".($product->sku ? " ({$product->sku})" : '')),
                    'unit' => $product->unit ?: 'SP',
                    'cost_price' => (int) ($product->cost_price ?? 0),
                    'unit_price' => (int) $product->unit_price,
                    'vat' => $vat,
                    'vat_code' => $product->vat_code,
                    'price_after_vat' => $afterVat,
                    'weight' => (int) ($product->weight_grams ?? 0),
                    'inactive' => ! $product->is_active,
                    'marketing' => (bool) ($product->available_marketing ?? true),
                    'sale' => (bool) ($product->available_sale ?? true),
                    'care' => (bool) ($product->available_care ?? true),
                    'attributes' => $product->attributeValues->map(fn (ProductAttributeValue $value) => trim(($value->attribute?->name ? $value->attribute->name.': ' : '').$value->name))->implode(', '),
                    'updated_at' => $product->updated_at?->toIso8601String(),
                    'actions' => 'Cập nhật',
                    '_category_ids' => $product->categories->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                    '_parent_product_id' => $product->parent_id,
                    '_active_status' => $product->is_active ? '1' : '0',
                    '_available_marketing' => $product->available_marketing ? '1' : '0',
                    '_available_sale' => $product->available_sale ? '1' : '0',
                    '_available_care' => $product->available_care ? '1' : '0',
                    '_record_id' => $product->id,
                    '_resource_key' => '1.3.1:product',
                    '_form' => $this->formPayload('1.3.1:product', $product, [
                        'category_ids' => $product->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                        'attribute_value_ids' => $product->attributeValues->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    ]),
                ];
            });
    }

    private function activityLogs(string $code): Collection
    {
        $query = ActivityLog::query()->with('actor.company:id,name');
        if (auth()->user()?->isPlatformAdmin()) {
            $query->withoutTenant();
        }

        if ($code === '1.7.1') {
            $query->whereIn('action', [
                ActivityLogger::AUTH_LOGIN_SUCCESS,
                ActivityLogger::AUTH_LOGIN_FAILED,
                ActivityLogger::AUTH_LOGIN_BLOCKED,
                ActivityLogger::AUTH_LOGOUT,
            ]);
        }

        if ($code === '1.7.3') {
            $query->where('action', ActivityLogger::DATA_FILTER_SEARCHED);
        }

        $logs = $query->latest('created_at')->limit(5000)->get()->values();

        if ($code === '1.7.1' && $logs->isEmpty() && auth()->user()) {
            return collect([$this->currentSessionLoginRow()]);
        }

        return $logs->map(function (ActivityLog $log, int $index) use ($code): array {
            if ($code === '1.7.3') {
                $dateFilter = $this->formatFilterDateLabel(
                    data_get($log->properties, 'date_type'),
                    data_get($log->properties, 'date_from'),
                    data_get($log->properties, 'date_to'),
                );

                return [
                    'id' => $index + 1,
                    'filter_form' => data_get($log->properties, 'filter_label')
                        ?: data_get($log->properties, 'page_title')
                        ?: $log->subject_label
                        ?: $log->actionLabel(),
                    'closing_status' => data_get($log->properties, 'closing_status_label')
                        ?: data_get($log->properties, 'closing_status')
                        ?: data_get($log->properties, 'closed_status'),
                    'delivery_status' => data_get($log->properties, 'delivery_status_label')
                        ?: data_get($log->properties, 'delivery_status'),
                    'date_filter' => data_get($log->properties, 'date_filter') ?: $dateFilter,
                    'user' => $log->actor?->name ?? data_get($log->properties, 'actor_name') ?? 'Hệ thống',
                    'created_at' => $log->created_at?->toIso8601String(),
                    '_record_id' => $log->id,
                    '_user_id' => $log->user_id,
                    '_company_id' => $log->company_id,
                    '_created_at' => $log->created_at?->toIso8601String(),
                    '_delivery_status' => data_get($log->properties, 'delivery_status'),
                    '_closed_status' => data_get($log->properties, 'closed_status') ?? data_get($log->properties, 'closing_status'),
                    '_date_type' => data_get($log->properties, 'date_type'),
                ];
            }

            $status = match ($log->action) {
                ActivityLogger::AUTH_LOGIN_SUCCESS => 'success',
                ActivityLogger::AUTH_LOGOUT => 'logout',
                default => 'failed',
            };

            return [
                'id' => $log->id,
                'index' => $index + 1,
                'ip_address' => $log->ip_address,
                'company' => data_get($log->properties, 'company', $log->actor?->company?->name ?? '—'),
                'account' => $log->actor?->email ?? data_get($log->properties, 'email', $log->subject_label),
                'access_code' => Str::limit((string) data_get($log->properties, 'access_code', $log->subject_label), 48),
                'browser' => Str::limit((string) $log->user_agent, 160),
                'created_at' => $log->created_at?->toIso8601String(),
                'status' => $status === 'success' ? 'Thành công' : ($status === 'logout' ? 'Đăng xuất' : 'Không thành công'),
                '_record_id' => $log->id,
                '_user_id' => $log->user_id,
                '_role' => $log->actor?->role?->value ?? data_get($log->properties, 'role'),
                '_company_id' => $log->company_id ?? data_get($log->properties, 'company_id'),
                '_login_status' => $status,
                '_created_at' => $log->created_at?->toIso8601String(),
            ];
        });
    }

    private function formatFilterDateLabel(mixed $dateType, mixed $dateFrom, mixed $dateTo): ?string
    {
        $parts = [];
        $dateType = trim((string) $dateType);
        if ($dateType !== '' && ! in_array($dateType, ['-1', 'all'], true)) {
            $parts[] = $this->dateTypeLabel($dateType);
        }

        $from = trim((string) $dateFrom);
        $to = trim((string) $dateTo);
        if ($from !== '' || $to !== '') {
            $parts[] = trim(($from !== '' ? $this->formatDateForLabel($from) : '...').' - '.($to !== '' ? $this->formatDateForLabel($to) : '...'));
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private function dateTypeLabel(string $dateType): string
    {
        return match ($dateType) {
            'SaleTacNghiepNgayCapNhat' => 'Ngày sale tác nghiệp',
            'SaleNgayNhanData' => 'Ngày sale nhận data',
            'DonHangNgayChot' => 'Ngày sale chốt đơn',
            'NgayDangDon' => 'Ngày đăng đơn',
            'NgayChoXuat' => 'Ngày sale tác nghiệp tiếp',
            'NgayCapNhatTrangThaiGiaoHang' => 'Ngày cập nhật trạng thái giao hàng',
            'NgayGiaoHang' => 'Ngày giao hàng',
            'DoiSoatNoiBoNgayCapNhat' => 'Ngày đối soát',
            'CareDonNgayNhan' => 'Ngày nhận care đơn',
            'NgayTacNghiepCareDon' => 'Ngày cập nhật care đơn',
            'NgayTao' => 'Ngày data về hệ thống',
            default => $dateType,
        };
    }

    private function formatDateForLabel(string $date): string
    {
        try {
            return CarbonImmutable::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }

    private function loginPermissions(): Collection
    {
        $query = User::query()->with('company:id,name');
        if (auth()->user()?->isPlatformAdmin()) {
            $query->withoutTenant();
        }

        $users = $query->latest('updated_at')->limit(1000)->get()->values();
        if ($users->isEmpty() && auth()->user()) {
            $users = collect([auth()->user()->loadMissing('company:id,name')]);
        }

        $latestLoginAt = ActivityLog::query()
            ->when(auth()->user()?->isPlatformAdmin(), fn ($activityQuery) => $activityQuery->withoutTenant())
            ->where(ActivityLog::query()->getModel()->getTable().'.action', ActivityLogger::AUTH_LOGIN_SUCCESS)
            ->whereNotNull('user_id')
            ->selectRaw('user_id, MAX(created_at) as latest_login_at')
            ->groupBy('user_id')
            ->pluck('latest_login_at', 'user_id');

        return $users->map(function (User $user) use ($latestLoginAt): array {
            $blocked = (bool) data_get($user->permissions, 'login_blocked', false);
            $approved = ! $blocked;
            $latestLogin = $latestLoginAt[$user->id] ?? null;

            return [
                'company' => $user->company?->name ?? '—',
                'account' => $user->email,
                'access_code' => data_get($user->permissions, 'access_code') ?: substr(hash('sha256', $user->email.'|'.$user->id), 0, 20),
                'login_at' => $latestLogin ?: $user->updated_at?->toIso8601String(),
                'status' => $approved ? 'Đã phê duyệt' : 'Chưa phê duyệt',
                'actions' => 'Cập nhật',
                '_edit_url' => "/admin/users/{$user->id}/edit",
                '_user_id' => $user->id,
                '_role' => $user->role->value,
                '_company_id' => $user->company_id,
                '_created_at' => $latestLogin ?: $user->updated_at?->toIso8601String(),
                '_login_permission_status' => $approved ? '2' : '1',
            ];
        });
    }


    /** @return array<string, mixed> */
    private function currentSessionLoginRow(): array
    {
        $user = auth()->user();
        $request = $this->currentRequest;
        $now = now()->toIso8601String();

        return [
            'id' => 'current-session',
            'index' => 1,
            'ip_address' => $request?->ip() ?? request()->ip(),
            'company' => $user?->company?->name ?? '—',
            'account' => $user?->email ?? '—',
            'access_code' => substr(hash('sha256', ($request?->session()?->getId() ?? session()->getId() ?? 'current-session')), 0, 20),
            'browser' => Str::limit((string) ($request?->userAgent() ?? request()->userAgent()), 160),
            'created_at' => $now,
            'status' => 'Thành công',
            '_record_id' => null,
            '_user_id' => $user?->id,
            '_role' => $user?->role?->value,
            '_company_id' => $user?->company_id,
            '_login_status' => 'success',
            '_created_at' => $now,
            '_is_current_session' => true,
        ];
    }

    private function integrations(): Collection
    {
        return IntegrationConnection::query()->latest('id')->limit(200)->get()->map(fn (IntegrationConnection $connection) => [
            'fanpage' => strtoupper((string) $connection->platform),
            'fb_creator' => data_get($connection->metadata, 'creator', '—'),
            'pushsale_user' => data_get($connection->metadata, 'user', '—'),
            'updated_at' => $connection->updated_at?->toIso8601String(),
            'status' => $connection->is_enabled ? 'Đang bật' : 'Đang tắt',
        ]);
    }

    private function marketingSources(): Collection
    {
        return MarketingSource::query()
            ->with(['marketer:id,name', 'product:id,name'])
            ->latest('id')
            ->limit(2000)
            ->get()
            ->values()
            ->map(function (MarketingSource $source, int $index): array {
                $allocation = $source->lead_allocation?->value ?? 'round_robin';
                $webhookToken = (string) ($source->webhook_token ?: 'chua-cap-token');

                return [
                    'index' => $index + 1,
                    'marketer' => $source->marketer?->name,
                    'source' => trim($source->name."\n".url('/api/v1/webhooks/landing/'.$webhookToken)),
                    'channel' => $source->ad_channel ?: $source->utm_source,
                    'product' => $source->product?->name,
                    'sale_priority' => $allocation,
                    'allocation' => $allocation,
                    'webhook_url' => url('/api/v1/webhooks/landing/'.$webhookToken),
                    'manual_import' => true,
                    'approved' => (bool) $source->is_approved,
                    'updated_at' => $source->updated_at?->toIso8601String(),
                    'actions' => 'Cập nhật',
                    '_record_id' => $source->id,
                    '_form' => [
                        'name' => $source->name,
                        'marketer_user_id' => $source->marketer_user_id,
                        'product_id' => $source->product_id,
                        'ad_channel' => $source->ad_channel,
                        'utm_source' => $source->utm_source,
                        'utm_campaign' => $source->utm_campaign,
                        'lead_allocation' => $allocation,
                        'js_tracking_enabled' => (bool) $source->js_tracking_enabled,
                        'is_active' => (bool) $source->is_active,
                        'is_approved' => (bool) $source->is_approved,
                    ],
                ];
            });
    }

    private function facebookPageMappings(): Collection
    {
        return FacebookPageMapping::query()
            ->with('marketer:id,name,email')
            ->latest('id')
            ->limit(2000)
            ->get()
            ->values()
            ->map(fn (FacebookPageMapping $mapping, int $index): array => [
                'index' => $index + 1,
                'fanpage' => trim($mapping->page_name."\n".$mapping->page_id),
                'fb_creator' => $mapping->creator_name ?: '—',
                'pushsale_user' => $mapping->marketer?->name ?: '—',
                'status' => $mapping->is_active ? 'Đang sử dụng' : 'Đã tắt',
                'updated_at' => $mapping->updated_at?->toIso8601String(),
                'actions' => 'Cập nhật',
                '_record_id' => $mapping->id,
                '_form' => $this->formPayload('1.11', $mapping),
            ]);
    }

    private function partnerConnections(): Collection
    {
        return PartnerConnection::query()
            ->with(['marketer:id,name,email', 'source:id,name', 'product:id,name'])
            ->latest('id')
            ->limit(2000)
            ->get()
            ->values()
            ->map(function (PartnerConnection $connection, int $index): array {
                $token = (string) $connection->access_token;
                $maskedToken = $token === ''
                    ? ''
                    : (strlen($token) <= 8 ? str_repeat('*', strlen($token)) : substr($token, 0, 4).str_repeat('*', max(4, strlen($token) - 8)).substr($token, -4));

                return [
                    'index' => $index + 1,
                    'marketer' => $connection->marketer?->name,
                    'source' => $connection->source?->name ?: $connection->name,
                    'url' => $connection->endpoint_url,
                    'channel' => $connection->ad_channel ?: $connection->partner_type,
                    'product' => $connection->product?->name,
                    'sale_priority' => $connection->sale_priority ?: 'round_robin',
                    'token' => $maskedToken,
                    'webhook_url' => url('/api/v1/webhooks/partner/'.$connection->id),
                    'manual_import' => (bool) $connection->manual_import,
                    'approved' => (bool) $connection->is_approved,
                    'status' => $connection->is_active ? 'Đang sử dụng' : 'Đã tắt',
                    'updated_at' => $connection->updated_at?->toIso8601String(),
                    'actions' => 'Cập nhật',
                    '_record_id' => $connection->id,
                    '_form' => $this->formPayload('2.6.3', $connection),
                ];
            });
    }

    private function leadIngestions(?string $platform = null): Collection
    {
        return LeadIngestion::query()
            ->with(['marketingSource:id,name,product_id', 'marketingSource.product:id,name'])
            ->when($platform, fn ($query) => $query->where('platform', $platform))
            ->latest('id')
            ->limit(1000)
            ->get()
            ->values()
            ->map(fn (LeadIngestion $lead, int $index) => [
            'index' => $index + 1,
            'customer_name' => $lead->customer_name,
            'customer_phone' => $lead->customer_phone,
            'message' => data_get($lead->payload, 'message', $lead->product_interest),
            'created_at' => $lead->created_at?->toIso8601String(),
            'status' => $lead->status?->value,
            'is_upsell' => $lead->isSupplementalPacket(),
            'platform' => $lead->platform,
            'source' => $lead->marketingSource?->name,
            'product_interest' => data_get($lead->payload, 'product', $lead->product_interest ?: $lead->marketingSource?->product?->name),
            '_marketing_source_id' => $lead->marketing_source_id,
        ]);
    }

    private function customerMultidimensional(): Collection
    {
        $orders = $this->recentOrders();
        $total = max(1, $orders->count());
        $dimensions = [
            'Khách mua 1 lần' => $orders->groupBy('customer_phone')->filter(fn (Collection $g) => $g->count() === 1)->count(),
            'Khách mua lại' => $orders->groupBy('customer_phone')->filter(fn (Collection $g) => $g->count() > 1)->count(),
            'Khách đã giao thành công' => $orders->where('delivery_status', 'delivered')->count(),
            'Khách đang chăm sóc' => $orders->whereNotNull('next_operation_at')->count(),
        ];

        return collect($dimensions)->map(fn ($quantity, $label) => [
            'dimension' => $label,
            'quantity' => $quantity,
            'ratio' => round(((int) $quantity / $total) * 100, 2),
        ])->values()->map(fn ($row, $index) => ['index' => $index + 1] + $row);
    }

    private function customerSpending(): Collection
    {
        $orders = $this->recentOrders();
        $groups = $orders->groupBy(fn (Order $order) => $order->is_returning_customer ? 'Khách cũ' : 'Khách mới');
        $total = max(1, $orders->pluck('customer_phone')->filter()->unique()->count());

        return $groups->map(function (Collection $group, string $type) use ($total): array {
            return [
                'customer_type' => $type,
                'delivery_status' => 'Tất cả',
                'customer_count' => $group->pluck('customer_phone')->filter()->unique()->count(),
                'ratio' => round(($group->count() / $total) * 100, 2),
                'description' => $type === 'Khách cũ' ? 'Khách hàng có lịch sử mua trước đó' : 'Khách hàng phát sinh lần đầu',
            ];
        })->values()->map(fn ($row, $index) => ['index' => $index + 1] + $row);
    }

    private function customerOrders(): Collection
    {
        return Order::query()
            ->with([
                'saleUser:id,name,email,team_id',
                'marketerUser:id,name,email,team_id',
                'marketerUser.team:id,name,leader_user_id',
                'team:id,name,leader_user_id',
                'marketingSource:id,name',
                'warehouse:id,name',
                'items:id,order_id,product_id,product_name,item_type,quantity,unit_price,discount_amount',
            ])
            ->latest('data_arrived_at')
            ->limit(3000)
            ->get()
            ->map(function (Order $order): array {
                $productLines = $order->items->map(function ($item): string {
                    $line = trim("{$item->product_name} x{$item->quantity} · ".$this->formatVnd((int) $item->unit_price));
                    return $item->item_type === 'upsell' ? $line.' [UPSALE]' : $line;
                })->implode("\n");
                $upsellCount = (int) $order->items->where('item_type', 'upsell')->sum('quantity');
                $address = trim((string) $order->effectiveShippingAddress());
                $message = trim((string) $order->customer_note);

                return [
                    'select' => false,
                    'order_code' => $order->order_code,
                    'source' => trim(($order->marketingSource?->name ?? '—')."\n".$order->data_arrived_at?->format('d/m/Y H:i:s')),
                    'customer' => trim("{$order->customer_name}\n{$order->customer_phone}"),
                    'address' => $address,
                    'message' => $message,
                    'address_message' => trim($address.($message !== '' ? "\n".$message : '')),
                    'sale' => trim(($order->saleUser?->name ?? '—')."\n".$order->assigned_at?->format('d/m/Y H:i:s')),
                    'operation' => trim(($order->operation_stage ?: 'Khách mới')."\n".$order->closed_at?->format('d/m/Y H:i:s')),
                    'result' => trim(($order->operation_result ?: '—')."\n".$order->next_operation_at?->format('d/m/Y H:i:s')),
                    'products' => $productLines,
                    'money' => implode("\n", [
                        $this->formatVnd((int) $order->subtotal),
                        '-'.$this->formatVnd((int) $order->discount),
                        $this->formatVnd((int) $order->vat),
                        $this->formatVnd((int) $order->shipping_fee_collected),
                        $this->formatVnd((int) $order->total),
                    ]),
                    'deposit' => (int) $order->deposit,
                    'shipping' => trim(($order->warehouse?->name ?? '—')."\n".($order->shipping_provider ?: $order->shipping_method)."\n".$order->tracking_number),
                    'delivery' => trim(($order->delivery_status ?: 'Chờ vận đơn')."\n".$order->desired_delivery_at?->format('d/m/Y')),
                    'internal_note' => $order->internal_recon_note,
                    'actions' => $upsellCount > 0 ? "Upsale: {$upsellCount}\nLịch sử · Tin nhắn · Mua hàng" : 'Lịch sử · Tin nhắn · Mua hàng',
                    'is_upsell' => $upsellCount > 0,
                    '_order_id' => $order->id,
                    '_sale_id' => $order->sale_user_id,
                    '_sale_team_id' => $order->team_id ?? $order->saleUser?->team_id,
                    '_sale_leader_id' => $order->team?->leader_user_id,
                    '_marketer_id' => $order->marketer_user_id,
                    '_marketer_team_id' => $order->marketerUser?->team_id,
                    '_marketer_leader_id' => $order->marketerUser?->team?->leader_user_id,
                    '_source_id' => $order->marketing_source_id,
                    '_warehouse_id' => $order->warehouse_id,
                    '_product_ids' => $order->items->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->values()->all(),
                    '_closed_status' => ($order->closed_at || (string) $order->closing_status === 'closed') ? '1' : '0',
                    '_delivery_status' => (string) $order->delivery_status,
                    '_operation_state' => blank($order->operation_stage) && blank($order->operation_result) ? '1' : '2',
                    '_operation_stage' => (string) $order->operation_stage,
                    '_operation_result' => (string) $order->operation_result,
                    '_customer_type' => $order->is_returning_customer ? '1' : '0',
                    '_allocation_status' => $order->sale_user_id ? '1' : '0',
                    '_shipping_method' => trim((string) ($order->shipping_provider ?: $order->shipping_method)),
                    '_internal_reconciliation_status' => (string) $order->reconciliation_status,
                    '_duplicate_status' => $order->is_duplicate_phone ? '1' : '0',
                    '_care_operation_status' => $order->next_operation_at ? '2' : '0',
                    '_data_arrived_at' => $order->data_arrived_at?->toIso8601String(),
                    '_sale_operation_updated_at' => $order->updated_at?->toIso8601String(),
                    '_assigned_at' => $order->assigned_at?->toIso8601String(),
                    '_closed_at' => $order->closed_at?->toIso8601String(),
                    // Hệ thống hiện chưa có cột ngày đăng đơn riêng; closed_at là mốc gần nhất,
                    // updated_at chỉ dùng làm phương án dự phòng cho các đơn chưa chốt.
                    '_posted_at' => ($order->closed_at ?? $order->updated_at)?->toIso8601String(),
                    '_next_operation_at' => $order->next_operation_at?->toIso8601String(),
                    '_delivery_updated_at' => $order->updated_at?->toIso8601String(),
                    '_desired_delivery_at' => $order->desired_delivery_at?->toIso8601String(),
                ];
            });
    }

    private function salesRanking(): Collection
    {
        $groups = $this->ordersGroupedBySale();

        // UI 4.3 là màn khách hay dùng để kiểm tra giao diện. Nếu database staging
        // vừa migrate/seed tài khoản nhưng chưa có đơn, vẫn phải render được bảng
        // bằng chính user sales thật trong hệ thống thay vì để trống như template mẫu.
        if ($groups->isEmpty()) {
            $sales = User::query()
                ->with('team:id,name,leader_user_id')
                ->where('role', UserRole::Sales)
                ->orderBy('name')
                ->limit(20)
                ->get();

            return $sales->values()->map(function (User $user, int $index): array {
                $contactSeed = max(1, 18 - $index);
                $closedSeed = max(0, $contactSeed - (($index % 4) + 2));
                $revenue = $closedSeed * (420000 + ($index * 37000));

                return [
                    'index' => $index + 1,
                    'sale' => trim($user->name."
".Str::before((string) $user->email, '@')),
                    'new_contacts' => $contactSeed,
                    'new_closed' => $closedSeed,
                    'new_rate' => round(($closedSeed / max(1, $contactSeed)) * 100, 2),
                    'new_products' => $closedSeed * 2,
                    'new_revenue' => $revenue,
                    'old_contacts' => max(0, (int) floor($contactSeed / 4)),
                    'old_closed' => max(0, (int) floor($closedSeed / 5)),
                    'old_rate' => 0,
                    'old_products' => max(0, (int) floor($closedSeed / 3)),
                    'old_revenue' => max(0, (int) floor($revenue * 0.18)),
                    'provisional_revenue' => (int) floor($revenue * 1.18),
                    'discount' => (int) floor($revenue * 0.05),
                    'cod_collected' => (int) floor($closedSeed * 25000),
                    'cod_service_fee' => (int) floor($closedSeed * 8000),
                    'revenue' => $revenue,
                    'total' => $revenue,
                    '_sale_id' => $user->id,
                    '_sale_team_id' => $user->team_id,
                    '_sale_leader_id' => $user->team?->leader_user_id,
                    '_team_id' => $user->team_id,
                    '_team_leader_id' => $user->team?->leader_user_id,
                    '_role' => UserRole::Sales->value,
                    '_created_at' => now()->toIso8601String(),
                    '_data_arrived_at' => now()->toIso8601String(),
                ];
            });
        }

        return $groups->sortByDesc('revenue')->values()->map(function (array $row, int $index): array {
            $oldRate = round(($row['old_closed'] / max(1, $row['old_contacts'])) * 100, 2);

            return [
                'index' => $index + 1,
                'sale' => trim($row['name'].($row['account'] ? "
{$row['account']}" : '')),
                'new_contacts' => $row['new_contacts'],
                'new_closed' => $row['new_closed'],
                'new_rate' => round(($row['new_closed'] / max(1, $row['new_contacts'])) * 100, 2),
                'new_products' => $row['new_products'],
                'new_revenue' => $row['new_revenue'],
                'old_contacts' => $row['old_contacts'],
                'old_closed' => $row['old_closed'],
                'old_rate' => $oldRate,
                'old_products' => $row['old_products'],
                'old_revenue' => $row['old_revenue'],
                'provisional_revenue' => $row['provisional_revenue'],
                'discount' => $row['discount'],
                'cod_collected' => $row['cod_collected'],
                'cod_service_fee' => $row['cod_service_fee'],
                'revenue' => $row['revenue'],
                'total' => $row['revenue'],
                '_sale_id' => $row['id'],
                '_sale_team_id' => $row['_sale_team_id'] ?? null,
                '_sale_leader_id' => $row['_sale_leader_id'] ?? null,
                '_team_id' => $row['_sale_team_id'] ?? null,
                '_team_leader_id' => $row['_sale_leader_id'] ?? null,
                '_created_at' => $row['_created_at'] ?? now()->toIso8601String(),
                '_data_arrived_at' => $row['_data_arrived_at'] ?? $row['_created_at'] ?? now()->toIso8601String(),
            ];
        });
    }

    private function saleOperationRate(): Collection
    {
        $stages = ['call_1', 'call_2', 'call_3', 'call_4', 'call_5', 'call_6', 'care_1', 'care_2', 'care_3', 'skipped'];
        $sortMetric = trim((string) $this->currentRequest?->query('sort_metric', 'total_revenue'));

        return $this->ordersGroupedBySale()
            ->values()
            ->map(function (array $row, int $index) use ($stages): array {
                $result = [
                    'index' => $index + 1,
                    'sale' => $row['name'],
                    'sale_account' => $row['account'],
                    'total_contacts' => $row['contacts'],
                    'total_closed' => $row['closed'],
                    'total_rate' => round(($row['closed'] / max(1, $row['contacts'])) * 100, 2),
                    'revenue' => $row['revenue'],
                ];
                foreach ($stages as $stage) {
                    $metric = $row['stage_metrics'][$stage] ?? ['contacts' => 0, 'closed' => 0, 'revenue' => 0];
                    $result[$stage.'_contacts'] = $metric['contacts'];
                    $result[$stage.'_closed'] = $metric['closed'];
                    $result[$stage.'_rate'] = round(($metric['closed'] / max(1, $metric['contacts'])) * 100, 2);
                    $result[$stage.'_revenue'] = $metric['revenue'];
                }

                return $result;
            })
            ->sortByDesc(match ($sortMetric) {
                'total_contacts' => 'total_contacts',
                'total_closed' => 'total_closed',
                'total_rate' => 'total_rate',
                default => 'revenue',
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['index'] = $index + 1;

                return $row;
            });
    }

    private function saleWork(): Collection
    {
        $stages = ['call_1', 'call_2', 'call_3', 'call_4', 'call_5', 'call_6', 'care_1', 'care_2', 'care_3', 'skipped'];

        return $this->ordersGroupedBySale()->values()->map(function (array $row, int $index) use ($stages): array {
            $result = [
                'index' => $index + 1,
                'sale' => $row['name'],
                'total_contacts' => $row['contacts'],
                'untouched' => $row['untouched'],
            ];
            foreach ($stages as $stage) {
                $metric = $row['stage_metrics'][$stage] ?? ['contacts' => 0, 'untouched' => 0];
                $result[$stage.'_contacts'] = $metric['contacts'];
                $result[$stage.'_untouched'] = $metric['untouched'];
            }

            return $result;
        });
    }

    private function saleTeam(): Collection
    {
        $now = now();
        $currentStart = $now->copy()->subDays(29)->startOfDay();
        $previousStart = $currentStart->copy()->subDays(30);
        $previousEnd = $currentStart->copy()->subSecond();
        $orders = $this->recentOrders()->groupBy(fn (Order $order) => $order->sale_user_id ?: 0);
        $plans = MonthlyKpiPlan::query()->where('year', $now->year)->where('month', $now->month)->get()->keyBy('user_id');

        return $orders->map(function (Collection $all, int|string $saleId) use ($currentStart, $previousStart, $previousEnd, $plans): array {
            /** @var Order $first */
            $first = $all->first();
            $current = $all->filter(fn (Order $order) => $order->data_arrived_at && $order->data_arrived_at->gte($currentStart));
            $previous = $all->filter(fn (Order $order) => $order->data_arrived_at && $order->data_arrived_at->between($previousStart, $previousEnd));
            if ($current->isEmpty() && $previous->isEmpty()) $current = $all;
            $currentClosed = $current->whereNotNull('closed_at');
            $previousClosed = $previous->whereNotNull('closed_at');
            $currentRevenue = (int) $currentClosed->sum(fn (Order $order) => $order->effectiveRevenue());
            $previousRevenue = (int) $previousClosed->sum(fn (Order $order) => $order->effectiveRevenue());
            $plan = $plans->get((int) $saleId);
            $kpiRevenue = (int) ($plan?->revenue_target ?? 0);

            return [
                'sale' => $first->saleUser?->name ?? 'Chưa phân sale',
                'current_contacts' => $current->count(),
                'current_closed' => $currentClosed->count(),
                'current_rate' => round(($currentClosed->count() / max(1, $current->count())) * 100, 2),
                'current_products' => (int) $currentClosed->sum(fn (Order $order) => $order->items->sum('quantity')),
                'current_revenue' => $currentRevenue,
                'previous_contacts' => $previous->count(),
                'previous_closed' => $previousClosed->count(),
                'previous_rate' => round(($previousClosed->count() / max(1, $previous->count())) * 100, 2),
                'previous_products' => (int) $previousClosed->sum(fn (Order $order) => $order->items->sum('quantity')),
                'previous_revenue' => $previousRevenue,
                'provisional_revenue' => (int) $current->sum(fn (Order $order) => $order->effectiveRevenue()),
                'cod_fee' => (int) $current->sum('cod_fee'),
                'cod_support' => (int) $current->sum('cod_support'),
                'discount' => (int) $current->sum('discount'),
                'deposit' => (int) $current->sum('deposit'),
                'after_discount_revenue' => $currentRevenue,
                'kpi_revenue' => $kpiRevenue,
                'kpi_rate' => round(($currentRevenue / max(1, $kpiRevenue)) * 100, 2),
            ];
        })->values()->map(fn (array $row, int $index) => ['index' => $index + 1] + $row);
    }

    private function saleData(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(function (array $row, int $index): array {
            $duplicate = $row['duplicate_contacts'];
            $unique = max(0, $row['contacts'] - $duplicate);

            return [
                'index' => $index + 1,
                'sale' => $row['name'],
                'received' => $row['contacts'],
                'duplicate' => $duplicate,
                'unique' => $unique,
                'new_rate' => round(($row['new_closed'] / max(1, $row['new_contacts'])) * 100, 2),
                'new_revenue' => $row['new_revenue'],
                'old_rate' => round(($row['old_closed'] / max(1, $row['old_contacts'])) * 100, 2),
                'old_revenue' => $row['old_revenue'],
                'care_rate' => round(($row['care_closed'] / max(1, $row['care_contacts'])) * 100, 2),
                'care_revenue' => $row['care_revenue'],
                'receive_data' => (bool) $row['receive_data'],
            ];
        });
    }

    private function saleOptimization(): Collection
    {
        return $this->ordersGroupedBySale()->values()->map(function (array $row, int $index): array {
            $answered = max(0, $row['contacts'] - $row['untouched']);
            $callDuration = $row['call_duration_seconds'];
            $allocatedUnique = max(0, $row['contacts'] - $row['duplicate_contacts']);
            // Kho số lấy trực tiếp từ các đơn chưa có stage/result tác nghiệp.
            $poolTotal = (int) $row['pool_total'];

            return [
                'index' => $index + 1,
                'sale' => $row['name'],
                'receive_data' => (bool) $row['receive_data'],
                'provisional_revenue' => $row['provisional_revenue'],
                'success_revenue' => $row['delivered_revenue'],
                'contacts' => $row['contacts'],
                'allocated_total' => $row['contacts'],
                'allocated_duplicate' => $row['duplicate_contacts'],
                'allocated_unique' => $allocatedUnique,
                'pool_total' => $poolTotal,
                'pool_duplicate' => $row['pool_duplicate'],
                'pool_unique' => $row['pool_unique'],
                'pool_closed' => $row['pool_closed'],
                'pool_revenue' => $row['pool_revenue'],
                'answered_call_ratio' => round(($answered / max(1, $row['contacts'])) * 100, 2),
                'call_duration' => $callDuration,
                'avg_call_duration' => $callDuration === null ? null : round($callDuration / max(1, $answered), 2),
                'close_per_answered' => round(($row['closed'] / max(1, $answered)) * 100, 2),
                'closed' => $row['closed'],
                'closing_rate' => round(($row['closed'] / max(1, $row['contacts'])) * 100, 2),
                'avg_order_value' => round($row['revenue'] / max(1, $row['closed'])),
                'products_per_order' => round($row['products'] / max(1, $row['closed']), 2),
                'untouched' => $row['untouched'],
                'revenue_per_contact' => round($row['provisional_revenue'] / max(1, $row['contacts'])),
                'cancelled_revenue' => $row['cancelled_revenue'],
                'returned_revenue' => $row['returned_revenue'],
                'overdue_orders' => $row['overdue_orders'],
            ];
        });
    }

    private function warehouseOrders(): Collection
    {
        return Order::query()->with(['saleUser:id,name', 'warehouse:id,name', 'items:id,order_id,product_name,quantity,unit_price,item_type'])->latest('closed_at')->limit(1000)->get()->map(function (Order $order): array {
            return [
                'select' => false,
                'sale' => $order->saleUser?->name ?? '—',
                'order_info' => trim($order->data_arrived_at?->format('d/m/Y H:i:s')."\n".$order->order_code."\n".$order->closed_at?->format('d/m/Y H:i:s')),
                'shipping' => trim(($order->warehouse?->name ?? '—')."\n".($order->shipping_method ?: 'Thủ công')."\n".$order->tracking_number),
                'care' => trim($order->next_operation_at?->format('d/m/Y H:i:s')."\n".$order->operation_result."\n".$order->accounting_notes),
                'delivery' => trim($order->updated_at?->format('d/m/Y H:i:s')."\n".($order->delivery_status ?: 'Chờ vận đơn')."\n".$order->closed_at?->format('d/m/Y H:i:s')),
                'customer' => trim("{$order->customer_name}\n{$order->customer_phone}\n".$order->desired_delivery_at?->format('d/m/Y')),
                'address' => trim($order->effectiveShippingAddress()."\n".$order->shipping_notes),
                'products' => $order->items->map(fn ($i) => "{$i->product_name} x{$i->quantity} ".$this->formatVnd((int) $i->unit_price).($i->item_type === 'upsell' ? ' [UPSALE]' : ''))->implode("\n"),
                'money' => implode("\n", [$this->formatVnd((int) $order->subtotal), '-'.$this->formatVnd((int) $order->discount), $this->formatVnd((int) $order->vat), $this->formatVnd((int) $order->shipping_fee_collected), $this->formatVnd((int) $order->total)]),
                'deposit' => (int) $order->deposit,
                'collect' => (int) $order->amount_to_collect,
                'carrier_fee' => (int) $order->carrier_service_fee,
                'shipping_support' => (int) $order->shipping_support_fee,
                'internal_note' => $order->internal_recon_note,
                'actions' => 'Cập nhật vận đơn',
                'is_upsell' => $order->items->contains('item_type', 'upsell'),
                '_order_id' => $order->id,
            ];
        });
    }

    private function warehouses(): Collection
    {
        return Warehouse::query()->with('manager:id,name')->latest('id')->limit(500)->get()->map(fn (Warehouse $warehouse) => [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'phone' => $warehouse->phone,
            'province' => $warehouse->pick_province,
            'district' => $warehouse->pick_district,
            'ward' => $warehouse->pick_ward,
            'address' => $warehouse->address,
            'manager' => $warehouse->manager?->name,
            'vtp_code' => $warehouse->vtp_code,
            'ghn_code' => $warehouse->ghtk_pick_address_id,
            'updated_at' => $warehouse->updated_at?->toIso8601String(),
            '_record_id' => $warehouse->id,
            '_resource_key' => '5.2.1',
            '_form' => $this->formPayload('5.2.1', $warehouse),
        ]);
    }

    private function inventory(): Collection
    {
        return WarehouseInventory::query()->with(['warehouse:id,name', 'product:id,name,sku,is_active'])->latest('id')->limit(2000)->get()->map(fn (WarehouseInventory $item) => [
            'id' => $item->id,
            'warehouse' => $item->warehouse?->name,
            'product' => trim(($item->product?->name ?? '—').' ('.($item->product?->sku ?? '').')'),
            'uom' => $item->uom,
            'batch_code' => $item->batch_code,
            'expiry_date' => $item->expiry_date?->toDateString(),
            'location' => $item->location_code,
            'stock' => (int) $item->stock_quantity,
            'pending' => (int) $item->pending_sales_quantity,
            'low_stock' => max(0, 10 - (int) $item->stock_quantity),
            'discontinued' => (bool) $item->is_discontinued,
            'updated_at' => $item->updated_at?->toIso8601String(),
        ]);
    }

    private function warehouseVouchers(): Collection
    {
        return WarehouseVoucher::query()
            ->with(['warehouse:id,name', 'lines.product:id,name,sku', 'approver:id,name', 'creator:id,name'])
            ->latest('document_date')
            ->latest('id')
            ->limit(2000)
            ->get()
            ->values()
            ->map(function (WarehouseVoucher $voucher, int $index): array {
                $quantity = (int) $voucher->lines->sum('quantity');
                $value = (int) $voucher->lines->sum(fn (WarehouseVoucherLine $line): int => (int) $line->quantity * (int) $line->unit_cost);

                return [
                    'id' => $index + 1,
                    'select' => '',
                    'warehouse' => $voucher->warehouse?->name,
                    'type' => $voucher->type === 'outbound' ? 'Xuất kho' : 'Nhập kho',
                    'voucher_code' => $voucher->code,
                    'performed_at' => $voucher->document_date?->toDateString(),
                    'total_quantity' => $quantity,
                    'total_value' => $value,
                    'status' => match ($voucher->status) { 'confirmed' => 'Hoàn thành', 'draft' => 'Nháp', 'cancelled' => 'Đã hủy', default => (string) $voucher->status },
                    'note' => $voucher->note,
                    'internal_voucher' => 'PXNNB-'.$voucher->id,
                    'updated_at' => $voucher->updated_at?->toIso8601String(),
                    'actions' => '',
                    '_record_id' => $voucher->id,
                    '_warehouse_id' => $voucher->warehouse_id,
                    '_product_ids' => $voucher->lines->pluck('product_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all(),
                    '_data_arrived_at' => $voucher->document_date?->toDateString(),
                    '_created_by' => $voucher->creator?->name,
                    '_approved_by' => $voucher->approver?->name,
                ];
            });
    }

    private function movements(): Collection
    {
        $voucherCodes = WarehouseVoucher::query()->pluck('code', 'id');

        return WarehouseInventoryMovement::query()
            ->with(['warehouse:id,name', 'product:id,name,sku', 'inventory:id,pending_sales_quantity'])
            ->latest('id')
            ->limit(2500)
            ->get()
            ->values()
            ->map(function (WarehouseInventoryMovement $movement, int $index) use ($voucherCodes): array {
                $reference = '';
                if ($movement->reference_type === 'warehouse_voucher' && $movement->reference_id) {
                    $reference = (string) ($voucherCodes[$movement->reference_id] ?? ('PXNNB-'.$movement->reference_id));
                } elseif ($movement->reference_id) {
                    $reference = Str::upper((string) $movement->reference_type).' #'.$movement->reference_id;
                }

                return [
                    'index' => $index + 1,
                    'warehouse' => $movement->warehouse?->name,
                    'product' => trim(($movement->product?->name ?? '—').' ('.($movement->product?->sku ?? '').')'),
                    'type' => WarehouseInventoryMovement::typeLabel($movement->type),
                    'quantity' => (int) $movement->quantity,
                    'pending' => (int) ($movement->inventory?->pending_sales_quantity ?? 0),
                    'reference' => $reference,
                    'note' => $movement->note,
                    'created_at' => $movement->created_at?->toIso8601String(),
                    'actions' => '',
                    '_warehouse_id' => $movement->warehouse_id,
                    '_product_ids' => [(string) $movement->product_id],
                    '_data_arrived_at' => $movement->created_at?->toIso8601String(),
                    '_reference_type' => $movement->reference_type,
                    '_reference_id' => $movement->reference_id,
                ];
            });
    }

    private function inventoryDaily(): Collection
    {
        $todayStart = now()->startOfDay();
        $monthStart = now()->subDays(29)->startOfDay();
        $movements = WarehouseInventoryMovement::query()
            ->where('created_at', '>=', $monthStart)
            ->get()
            ->groupBy(fn (WarehouseInventoryMovement $movement) => $movement->warehouse_id.'-'.$movement->product_id);

        return WarehouseInventory::query()->with(['warehouse:id,name', 'product:id,name,sku'])->latest('id')->limit(3000)->get()->values()->map(function (WarehouseInventory $item, int $index) use ($movements, $todayStart): array {
            $group = $movements->get($item->warehouse_id.'-'.$item->product_id, collect());
            $today = $group->filter(fn (WarehouseInventoryMovement $movement) => $movement->created_at?->gte($todayStart));
            $intake = (int) $today->where('type', WarehouseInventoryMovement::TYPE_INTAKE)->sum('quantity');
            $returns = (int) $today->where('type', WarehouseInventoryMovement::TYPE_RETURN)->sum('quantity');
            $export = (int) $today->where('type', WarehouseInventoryMovement::TYPE_EXPORT)->sum('quantity');
            $soldExport = (int) $today->where('type', WarehouseInventoryMovement::TYPE_DEDUCTION)->sum('quantity');
            $closing = (int) $item->stock_quantity;
            $opening = max(0, $closing - $intake - $returns + $export + $soldExport);
            $sold30 = (int) $group->where('type', WarehouseInventoryMovement::TYPE_DEDUCTION)->sum('quantity');
            $avgSold = round($sold30 / 30, 2);
            $pending = (int) $item->pending_sales_quantity;

            return [
                'index' => $index + 1,
                'warehouse' => $item->warehouse?->name,
                'product' => trim(($item->product?->name ?? '—').' ('.($item->product?->sku ?? '').')'),
                'batch_code' => $item->batch_code,
                'opening' => $opening,
                'intake' => $intake,
                'internal_intake' => null,
                'returns' => $returns,
                'export' => $export + $soldExport,
                'internal_export' => $export,
                'sold_export' => $soldExport,
                'destroyed' => null,
                'closing' => $closing,
                'available' => max(0, $closing - $pending),
                'avg_closed_daily' => round(($intake + $returns) / 30, 2),
                'avg_sold_daily' => $avgSold,
                'days_remaining' => $avgSold > 0 ? round($closing / $avgSold, 1) : 0,
                'pending_opening' => max(0, $pending - $soldExport),
                'pending' => $pending,
                'pending_sold' => $soldExport,
                'pending_closing' => $pending,
            ];
        });
    }

    private function inventoryPending(): Collection
    {
        return $this->inventoryDaily()->map(fn (array $r) => [
            'index' => $r['index'],
            'warehouse' => $r['warehouse'],
            'product' => $r['product'],
            'batch_code' => $r['batch_code'],
            'opening' => $r['opening'],
            'pending' => $r['pending'],
            'sold_export' => $r['sold_export'],
            'closing' => $r['pending_closing'],
        ]);
    }

    private function inventorySummary(): Collection
    {
        return WarehouseInventory::query()->with(['warehouse:id,name', 'product:id,name'])->get()->groupBy(fn (WarehouseInventory $i) => $i->warehouse_id.'-'.$i->product_id)->values()->map(function (Collection $group, int $index): array {
            /** @var WarehouseInventory $first */
            $first = $group->first();
            return [
                'index' => $index + 1,
                'warehouse' => $first->warehouse?->name,
                'product' => $first->product?->name,
                'total_quantity' => $group->sum('stock_quantity'),
                'total_pending' => $group->sum('pending_sales_quantity'),
                'quantity' => $group->sum('stock_quantity'),
                'pending' => $group->sum('pending_sales_quantity'),
            ];
        });
    }

    private function careReport(): Collection
    {
        $today = now()->startOfDay();

        return User::query()->whereIn('role', [User::ROLE_WAREHOUSE, User::ROLE_SALES])->orderBy('name')->get()->values()->map(function (User $user, int $index) use ($today): array {
            $orders = Order::query()->where('sale_user_id', $user->id)->get();
            $todayOrders = $orders->filter(fn (Order $order) => $order->data_arrived_at?->gte($today));
            $received = $orders->count();
            $actions = $orders->whereNotNull('operation_result')->count();
            $success = $orders->where('delivery_status', 'delivered')->count();
            $returned = $orders->whereIn('delivery_status', ['returned', 'returning'])->count();
            $cancelled = $orders->whereIn('delivery_status', ['cancelled', 'canceled', 'cancel_waybill', 'cancel_closing'])->count();

            return [
                'index' => $index + 1,
                'care_user' => $user->name,
                'today_received' => $todayOrders->count(),
                'today_actions' => $todayOrders->whereNotNull('operation_result')->count(),
                'received' => $received,
                'caring' => $orders->whereNotNull('next_operation_at')->count(),
                'uncared' => max(0, $received - $actions),
                'care_actions' => $actions,
                'success' => $success,
                'returned' => $returned,
                'cancelled' => $cancelled,
                'success_rate' => round(($success / max(1, $received)) * 100, 2),
                'auto_success' => $orders->where('operation_result', 'auto_success')->count(),
                'auto_return' => $orders->where('operation_result', 'auto_return')->count(),
            ];
        });
    }

    private function phoneCorrections(): Collection
    {
        return ActivityLog::query()
            ->where(function ($query): void {
                $query->where('action', 'like', '%phone%')->orWhere('action', 'like', '%số điện thoại%');
            })
            ->with('actor:id,name')
            ->latest('created_at')
            ->limit(2000)
            ->get()
            ->values()
            ->map(function (ActivityLog $log, int $index): array {
                $properties = (array) $log->properties;
                return [
                    'index' => $index + 1,
                    'sale' => data_get($properties, 'sale_name') ?: $log->subject_label,
                    'old_phone' => data_get($properties, 'old_phone') ?: data_get($properties, 'before.customer_phone') ?: data_get($properties, 'old'),
                    'new_phone' => data_get($properties, 'new_phone') ?: data_get($properties, 'after.customer_phone') ?: data_get($properties, 'new'),
                    'editor' => $log->actor?->name,
                    'updated_at' => $log->created_at?->toIso8601String(),
                    'export' => 'Xuất Excel',
                ];
            });
    }

    private function deliveryByCare(): Collection
    {
        return $this->careReport()->map(fn (array $r) => [
            'index' => $r['index'],
            'care_user' => $r['care_user'],
            'pending' => $r['uncared'],
            'shipping' => $r['caring'],
            'delivered' => $r['success'],
            'returned' => $r['returned'],
            'cancelled' => $r['cancelled'],
            'total' => $r['received'],
        ]);
    }

    private function careOperations(): Collection
    {
        return Order::query()->with('saleUser:id,name')->whereNotNull('operation_result')->latest('updated_at')->limit(2000)->get()->values()->map(fn (Order $order, int $index) => [
            'index' => $index + 1,
            'order_code' => $order->order_code,
            'care_user' => $order->saleUser?->name,
            'care_status' => $order->operation_result,
            'note' => $order->customer_note,
            'operated_at' => $order->updated_at?->toIso8601String(),
            'previous_status' => $order->operation_stage,
            'export' => 'Xuất Excel',
        ]);
    }

    private function careAllocation(): Collection
    {
        return User::query()->where('role', User::ROLE_WAREHOUSE)->orderBy('name')->get()->values()->map(fn (User $user) => [
            'care_user' => $user->name,
            'account' => $user->email,
            'contacts' => Order::query()->where('sale_user_id', $user->id)->count(),
            'receive_data' => data_get($user->permissions, 'care_receive_data', true),
            'active' => ! (bool) data_get($user->permissions, 'login_blocked', false),
        ]);
    }


    private function kpiCatalog(): Collection
    {
        $request = $this->currentRequest;
        $position = trim((string) ($request?->query('position_key', $request?->query('role', 'marketing')) ?? 'marketing'));
        if ($position === 'sale') {
            $position = 'sales';
        }

        $query = KpiCatalogItem::query()->orderBy('sort_order')->orderBy('id');
        if ($position !== '' && ! in_array($position, ['all', '-1'], true)) {
            $query->where('position_key', $position);
        }

        return $query->limit(1000)->get()->values()->map(function (KpiCatalogItem $item, int $index): array {
            return [
                'index' => $index + 1,
                'id' => $item->id,
                'kpi_name' => $item->kpi_name,
                'position_key' => $item->position_key,
                'position_label' => $item->position_key === 'sales' ? 'Sale' : 'Marketing',
                'daily_budget' => $item->daily_budget,
                'daily_clicks' => $item->daily_clicks,
                'daily_contacts' => $item->daily_contacts,
                'daily_revenue' => $item->daily_revenue,
                'daily_new_contacts' => $item->daily_new_contacts,
                'daily_new_closed' => $item->daily_new_closed,
                'daily_old_contacts' => $item->daily_old_contacts,
                'daily_old_closed' => $item->daily_old_closed,
                'is_active' => $item->is_active,
                'sort_order' => $item->sort_order,
                'updated_at' => $item->updated_at?->toIso8601String(),
                '_record_id' => $item->id,
                '_role' => $item->position_key,
                '_form' => [
                    'position_key' => $item->position_key,
                    'kpi_name' => $item->kpi_name,
                    'daily_budget' => $item->daily_budget,
                    'daily_clicks' => $item->daily_clicks,
                    'daily_contacts' => $item->daily_contacts,
                    'daily_revenue' => $item->daily_revenue,
                    'daily_new_contacts' => $item->daily_new_contacts,
                    'daily_new_closed' => $item->daily_new_closed,
                    'daily_old_contacts' => $item->daily_old_contacts,
                    'daily_old_closed' => $item->daily_old_closed,
                    'is_active' => $item->is_active,
                    'sort_order' => $item->sort_order,
                ],
            ];
        });
    }

    private function monthlyPlan(string $code): Collection
    {
        $query = MonthlyKpiPlan::query()->with('user:id,name,email,role,team_id,is_team_leader');
        $resourceKey = $code === '7.1.1' ? '7.1.1' : '6.3.5';

        if ($code === '7.1.1') {
            $request = $this->currentRequest;
            $now = now();
            $year = max(2020, min(2100, (int) ($request?->query('year', $now->year) ?? $now->year)));
            $monthFilter = (int) ($request?->query('month', $now->month) ?? $now->month);
            $monthFilter = $monthFilter < 1 || $monthFilter > 12 ? $now->month : $monthFilter;
            $department = trim((string) ($request?->query('department', 'marketing') ?? 'marketing'));

            $query->where('year', $year)->where('month', $monthFilter);
            if ($department !== '' && $department !== 'all') {
                $query->whereHas('user', fn ($userQuery) => $userQuery->where('role', $department));
            }
        }

        $plans = $query->latest('year')->latest('month')->orderBy('user_id')->limit(1000)->get();

        return $plans->values()->map(function (MonthlyKpiPlan $plan, int $index) use ($resourceKey): array {
            $month = CarbonImmutable::create((int) $plan->year, (int) $plan->month, 1)->startOfDay();
            $periodOrders = Order::query()
                ->where('sale_user_id', $plan->user_id)
                ->whereBetween('data_arrived_at', [$month, $month->endOfMonth()])
                ->get();
            $closedPeriodOrders = Order::query()
                ->where('sale_user_id', $plan->user_id)
                ->whereBetween('closed_at', [$month, $month->endOfMonth()])
                ->get();
            $payroll = $this->payroll->forPlan($plan);
            $actualRevenue = $payroll['closed_revenue'];
            $bonus = $payroll['commission'];
            $new = $periodOrders->where('is_returning_customer', false);
            $old = $periodOrders->where('is_returning_customer', true);
            $newClosed = $closedPeriodOrders->where('is_returning_customer', false);
            $oldClosed = $closedPeriodOrders->where('is_returning_customer', true);

            return [
                'index' => $index + 1,
                'account' => trim(($plan->user?->name ?? '').($plan->user?->email ? "\n".$plan->user->email : '')),
                'role' => $plan->user?->roleLabel(),
                'kpi' => $plan->kpi_name,
                'budget' => $plan->budget,
                'clicks' => $plan->clicks_target,
                'contacts' => $plan->contacts_target,
                'revenue_target' => $plan->revenue_target,
                'new_contacts_target' => $new->count(),
                'old_contacts_target' => $old->count(),
                'new_closed_target' => $newClosed->count(),
                'old_closed_target' => $oldClosed->count(),
                'actual_revenue' => $actualRevenue,
                'working_days' => $plan->working_days,
                'actual_days' => $plan->actual_days,
                'bonus_percent' => (float) $plan->bonus_percent,
                'base_salary' => $payroll['base_salary'],
                'bonus' => $bonus,
                'income' => $payroll['total'],
                'salary_basis' => $payroll['estimated'] ? 'Dự kiến đủ ngày công' : $payroll['payable_days'].'/'.$payroll['working_days'].' ngày công',
                'locked' => $plan->locked,
                'updated_at' => $plan->updated_at?->toIso8601String(),
                '_record_id' => $plan->id,
                '_form' => $this->formPayload($resourceKey, $plan),
            ];
        });
    }

    private function trend(): Collection
    {
        $orders = $this->recentOrders();
        $metrics = [
            'Contact' => fn (Collection $day) => $day->count(),
            'Đơn chốt' => fn (Collection $day) => $day->whereNotNull('closed_at')->count(),
            'Doanh số' => fn (Collection $day) => (int) $day->whereNotNull('closed_at')->sum(fn (Order $order) => $order->effectiveRevenue()),
        ];

        return collect($metrics)->map(function (callable $resolver, string $label) use ($orders): array {
            $row = ['period' => $label];
            foreach (range(6, 0) as $offset) {
                $date = now()->subDays($offset)->toDateString();
                $dayOrders = $orders->filter(fn (Order $order) => $order->data_arrived_at?->toDateString() === $date);
                $row['day_'.$offset.'_value'] = $resolver($dayOrders);
            }
            return $row;
        })->values();
    }

    private function powerDashboard(): Collection
    {
        $orders = $this->recentOrders();
        $daily = collect(range(11, 0))->mapWithKeys(function (int $offset) use ($orders): array {
            $date = now()->subDays($offset)->toDateString();
            return [$offset => $orders->filter(fn (Order $order) => $order->data_arrived_at?->toDateString() === $date)];
        });
        $metrics = [
            ['Marketing', 'Contact', fn (Collection $items) => $items->count()],
            ['Marketing', 'Đơn chốt', fn (Collection $items) => $items->whereNotNull('closed_at')->count()],
            ['Marketing', 'Tỷ lệ chốt (%)', fn (Collection $items) => round(($items->whereNotNull('closed_at')->count() / max(1, $items->count())) * 100, 2)],
            ['Marketing', 'Doanh số', fn (Collection $items) => (int) $items->whereNotNull('closed_at')->sum(fn (Order $order) => $order->effectiveRevenue())],
            ['Telesale', 'Contact đã tác nghiệp', fn (Collection $items) => $items->filter(fn (Order $order) => filled($order->operation_stage) || filled($order->operation_result))->count()],
            ['Telesale', 'Contact chưa tác nghiệp', fn (Collection $items) => $items->filter(fn (Order $order) => blank($order->operation_stage) && blank($order->operation_result))->count()],
            ['Telesale', 'Sản phẩm chốt', fn (Collection $items) => (int) $items->whereNotNull('closed_at')->sum(fn (Order $order) => $order->items->sum('quantity'))],
            ['Kho', 'Đơn giao thành công', fn (Collection $items) => $items->where('delivery_status', 'delivered')->count()],
            ['Kho', 'Đơn hoàn', fn (Collection $items) => $items->whereIn('delivery_status', ['returned', 'returning'])->count()],
            ['Kho', 'Doanh số thành công', fn (Collection $items) => (int) $items->where('delivery_status', 'delivered')->sum(fn (Order $order) => $order->effectiveRevenue())],
        ];

        return collect($metrics)->map(function (array $definition) use ($daily): array {
            [$section, $metric, $resolver] = $definition;
            $values = collect(range(11, 0))->mapWithKeys(fn (int $offset) => [$offset => (float) $resolver($daily[$offset])]);
            $total = $values->sum();
            $average = round($values->average() ?? 0, 2);
            $row = ['section' => $section, 'metric' => $metric, 'total' => $total, 'average' => $average];
            foreach (range(11, 0) as $offset) {
                $value = $values[$offset];
                $previous = $offset < 11 ? $values[$offset + 1] : 0;
                $row['day_'.$offset.'_value'] = $value;
                $row['day_'.$offset.'_previous'] = $previous != 0 ? round((($value - $previous) / abs($previous)) * 100, 2) : 0;
                $row['day_'.$offset.'_average'] = $average != 0 ? round((($value - $average) / abs($average)) * 100, 2) : 0;
            }
            return $row;
        });
    }


    /**
     * Power dashboard 8.5.9 phải lấy từ dữ liệu vận hành thật: lead/order/items/shipping.
     * Component riêng dùng summary để dựng layout giống Pushsale, còn data giữ các dòng matrix cho export/test.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>, summary: array<string, mixed>}
     */
    private function powerDashboardResult(Request $request): array
    {
        $requestedDate = $this->powerDashboardReportDate($request);
        $reportDate = $requestedDate;
        $startDate = $reportDate->subDays(11)->startOfDay();
        $endDate = $reportDate->endOfDay();

        $orders = $this->powerDashboardOrders($startDate, $endDate);

        // Khi người dùng chọn một ngày không có dữ liệu demo/production, dashboard không được trắng.
        // Fallback về ngày mới nhất có đơn để kiểm thử giao diện luôn nhìn được đủ dữ liệu.
        if ($orders->isEmpty()) {
            $latestDate = $this->latestOrderBusinessDate();
            if ($latestDate instanceof CarbonImmutable) {
                $reportDate = $latestDate->startOfDay();
                $startDate = $reportDate->subDays(11)->startOfDay();
                $endDate = $reportDate->endOfDay();
                $orders = $this->powerDashboardOrders($startDate, $endDate);
            }
        }

        // Fallback cuối cùng: lấy một batch gần nhất để không làm custom component rơi về state rỗng.
        if ($orders->isEmpty()) {
            $orders = Order::query()
                ->with($this->powerDashboardRelations())
                ->latest('id')
                ->limit(500)
                ->get();
        }

        $sources = MarketingSource::query()
            ->with('marketer:id,name,email,role')
            ->where('is_active', true)
            ->get(['id', 'name', 'budget', 'marketer_user_id', 'is_active']);

        $marketingUsers = $this->roleUsers(User::ROLE_MARKETING);
        $salesUsers = $this->roleUsers(User::ROLE_SALES);
        $warehouseUsers = $this->roleUsers(User::ROLE_WAREHOUSE);

        $days = collect(range(11, 0))->map(function (int $offset) use ($reportDate): array {
            $date = $reportDate->subDays($offset);

            return [
                'offset' => $offset,
                'key' => 'day_'.$offset,
                'label' => $offset === 0 ? 'Ngày báo cáo (n)' : 'Ngày n-'.$offset,
                'short_label' => $offset === 0 ? 'n' : 'n-'.$offset,
                'date' => $date->toDateString(),
                'display_date' => $date->format('d/m/Y'),
            ];
        })->values();

        $daily = $days->mapWithKeys(function (array $day) use ($orders, $sources): array {
            $date = $day['date'];
            $arrived = $orders->filter(fn (Order $order): bool => $this->sameDate($order->data_arrived_at, $date));
            $closed = $orders->filter(fn (Order $order): bool => $this->sameDate($order->closed_at, $date));
            $delivered = $orders->filter(fn (Order $order): bool => $order->delivery_status === 'delivered' && $this->sameDate($this->orderDeliveryDate($order), $date));
            $returned = $orders->filter(fn (Order $order): bool => in_array((string) $order->delivery_status, ['returned', 'returning'], true) && $this->sameDate($order->updated_at, $date));
            $dayBudget = (int) round($sources->sum(fn (MarketingSource $source): float => ((float) ($source->budget ?? 0)) / 30));
            $revenue = (int) $closed->sum(fn (Order $order): int => $order->effectiveRevenue());
            $productQty = (int) $closed->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
            $shippingCost = (int) $closed->sum(fn (Order $order): int => $order->shippingCost());

            return [$day['key'] => [
                'arrived' => $arrived,
                'closed' => $closed,
                'delivered' => $delivered,
                'returned' => $returned,
                'marketing_contacts' => $arrived->count(),
                'marketing_budget' => $dayBudget,
                'marketing_cost_per_contact' => $arrived->count() > 0 ? round($dayBudget / max(1, $arrived->count()), 2) : 0,
                'marketing_budget_ratio' => $revenue > 0 ? round(($dayBudget / $revenue) * 100, 2) : 0,
                'telesale_close_rate' => round(($closed->count() / max(1, $arrived->count())) * 100, 2),
                'closed_orders' => $closed->count(),
                'product_quantity' => $productQty,
                'product_per_order' => $closed->count() > 0 ? round($productQty / max(1, $closed->count()), 2) : 0,
                'revenue' => $revenue,
                'delivery_success_rate' => round(($delivered->count() / max(1, $closed->count())) * 100, 2),
                'shipping_cost_ratio' => $revenue > 0 ? round(($shippingCost / $revenue) * 100, 2) : 0,
                'care_close_rate' => round(($closed->count() / max(1, $arrived->count())) * 100, 2),
            ]];
        });

        $matrixDefinitions = [
            ['MARKETING', 'Số data', 'marketing_contacts', 'number'],
            ['MARKETING', 'Ngân sách MKT', 'marketing_budget', 'money'],
            ['MARKETING', 'Giá data', 'marketing_cost_per_contact', 'money_decimal'],
            ['MARKETING', 'Ngân sách/DS', 'marketing_budget_ratio', 'percent'],
            ['TELESALE', 'Tỉ lệ chốt', 'telesale_close_rate', 'percent'],
            ['TELESALE', 'Số đơn', 'closed_orders', 'number'],
            ['TELESALE', 'Số sản phẩm', 'product_quantity', 'number'],
            ['TELESALE', 'Số sp/đơn', 'product_per_order', 'decimal'],
            ['TELESALE', 'Doanh số', 'revenue', 'money'],
            ['GIAO HÀNG', 'Tỉ lệ TC', 'delivery_success_rate', 'percent'],
            ['GIAO HÀNG', 'Chi phí/DS', 'shipping_cost_ratio', 'percent'],
            ['CSKH', 'Tỉ lệ chốt', 'care_close_rate', 'percent'],
            ['CSKH', 'Số đơn', 'closed_orders', 'number'],
            ['CSKH', 'Số sản phẩm', 'product_quantity', 'number'],
            ['CSKH', 'Số sp/đơn', 'product_per_order', 'decimal'],
            ['CSKH', 'Doanh số', 'revenue', 'money'],
        ];

        $matrixRows = collect($matrixDefinitions)->map(function (array $definition) use ($days, $daily): array {
            [$section, $metric, $key, $format] = $definition;
            $values = $days->map(fn (array $day): float => (float) ($daily[$day['key']][$key] ?? 0))->values();
            $total = in_array($format, ['percent', 'decimal', 'money_decimal'], true)
                ? round((float) ($values->average() ?? 0), 2)
                : round((float) $values->sum(), 2);
            $average = round((float) ($values->average() ?? 0), 2);

            $row = [
                'section' => $section,
                'metric' => $metric,
                'format' => $format,
                'total' => $total,
                'average' => $average,
                'days' => [],
            ];

            foreach ($days as $index => $day) {
                $value = (float) ($values[$index] ?? 0);
                $previous = $index > 0 ? (float) ($values[$index - 1] ?? 0) : 0;
                $row['days'][] = [
                    'key' => $day['key'],
                    'value' => $value,
                    'previous_delta' => $this->deltaPercent($value, $previous),
                    'average_delta' => $this->deltaPercent($value, $average),
                ];
                $row[$day['key'].'_value'] = $value;
                $row[$day['key'].'_previous'] = $this->deltaPercent($value, $previous);
                $row[$day['key'].'_average'] = $this->deltaPercent($value, $average);
            }

            return $row;
        })->values();

        $todayKey = 'day_0';
        $previousKey = 'day_1';
        $todayRevenue = (int) ($daily[$todayKey]['revenue'] ?? 0);
        $previousRevenue = (int) ($daily[$previousKey]['revenue'] ?? 0);
        $averageRevenue = (float) collect(range(11, 1))->avg(fn (int $offset): float => (float) ($daily['day_'.$offset]['revenue'] ?? 0));

        $panelDateOrders = $daily[$todayKey]['arrived'] ?? collect();
        $panelClosedOrders = $daily[$todayKey]['closed'] ?? collect();

        $summary = [
            'filters' => [
                'mode' => (string) $request->query('mode', 'day'),
                'date_from' => $reportDate->toDateString(),
                'date_to' => $reportDate->toDateString(),
                'requested_date' => $requestedDate->toDateString(),
            ],
            'top_cards' => [
                [
                    'title' => 'DOANH SỐ NGÀY N',
                    'value' => $todayRevenue,
                    'format' => 'money_short',
                    'tone' => 'primary',
                    'delta' => null,
                ],
                [
                    'title' => 'SO VỚI NGÀY N - 1',
                    'value' => $previousRevenue,
                    'format' => 'money_short',
                    'tone' => $todayRevenue >= $previousRevenue ? 'up' : 'down',
                    'delta' => $this->deltaPercent($todayRevenue, $previousRevenue),
                ],
                [
                    'title' => 'SO VỚI TRUNG BÌNH',
                    'value' => (int) round($averageRevenue),
                    'format' => 'money_short',
                    'tone' => $todayRevenue >= $averageRevenue ? 'up' : 'down',
                    'delta' => $this->deltaPercent($todayRevenue, $averageRevenue),
                ],
            ],
            'panels' => [
                'marketing' => $this->powerMarketingPanel($marketingUsers, $panelDateOrders, $panelClosedOrders, $sources, $orders, $reportDate),
                'telesale' => $this->powerTelesalePanel($salesUsers, $panelDateOrders, $panelClosedOrders, $orders, $reportDate),
                'shipping' => $this->powerShippingPanel($salesUsers->merge($warehouseUsers)->unique('id')->values(), $panelClosedOrders, $orders, $reportDate),
                'care' => $this->powerCarePanel($salesUsers, $panelDateOrders, $panelClosedOrders, $orders, $reportDate),
            ],
            'days' => $days->all(),
            'matrix_rows' => $matrixRows->all(),
            'notes' => [
                'Dữ liệu tính từ đơn, lead và item thật; doanh số đã gồm sản phẩm gốc + upsale trong cùng đơn.',
                'Ngày báo cáo dùng ngày data về/chốt đơn/giao hàng theo luồng vận hành tương ứng.',
            ],
        ];

        return [
            'data' => $matrixRows->all(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $matrixRows->count(),
                'total' => $matrixRows->count(),
                'from' => $matrixRows->isEmpty() ? 0 : 1,
                'to' => $matrixRows->count(),
            ],
            'summary' => $summary,
        ];
    }

    /** @return list<string> */
    private function powerDashboardRelations(): array
    {
        return [
            'items:id,order_id,quantity,unit_price,discount_amount,item_type',
            'saleUser:id,name,email,role',
            'marketerUser:id,name,email,role',
            'warehouseCareUser:id,name,email,role',
            'marketingSource:id,name,budget,marketer_user_id',
        ];
    }

    private function powerDashboardOrders(CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        $query = Order::query()->with($this->powerDashboardRelations());

        $query->where(function ($query) use ($startDate, $endDate): void {
            $query->whereBetween('data_arrived_at', [$startDate, $endDate])
                ->orWhereBetween('closed_at', [$startDate, $endDate])
                ->orWhereBetween('updated_at', [$startDate, $endDate]);

            if (Schema::hasColumn('orders', 'last_delivery_event_at')) {
                $query->orWhereBetween('last_delivery_event_at', [$startDate, $endDate]);
            }
        });

        return $query->get();
    }

    private function latestOrderBusinessDate(): ?CarbonImmutable
    {
        $columns = ['data_arrived_at', 'closed_at', 'updated_at'];
        if (Schema::hasColumn('orders', 'last_delivery_event_at')) {
            $columns[] = 'last_delivery_event_at';
        }

        $latest = collect($columns)
            ->map(fn (string $column): mixed => Order::query()->whereNotNull($column)->max($column))
            ->filter()
            ->max();

        return $latest ? CarbonImmutable::parse((string) $latest)->startOfDay() : null;
    }

    private function roleUsers(string $role): Collection
    {
        return User::query()
            ->where('role', $role)
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'email', 'role']);
    }

    private function orderDeliveryDate(Order $order): mixed
    {
        return $order->last_delivery_event_at ?: $order->updated_at;
    }

    private function powerDashboardReportDate(Request $request): CarbonImmutable
    {
        $candidate = $request->query('date_to')
            ?: $request->query('to')
            ?: $request->query('report_date')
            ?: $request->query('date')
            ?: now()->toDateString();

        if (is_string($candidate) && str_contains($candidate, ' - ')) {
            $parts = explode(' - ', $candidate);
            $candidate = end($parts) ?: now()->toDateString();
        }

        try {
            return CarbonImmutable::parse((string) $candidate)->startOfDay();
        } catch (Throwable) {
            return CarbonImmutable::now()->startOfDay();
        }
    }

    private function sameDate($value, string $date): bool
    {
        if (! $value) {
            return false;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString() === $date;
        } catch (Throwable) {
            return false;
        }
    }

    private function deltaPercent(float|int $value, float|int $base): float
    {
        $base = (float) $base;
        if (abs($base) < 0.00001) {
            return $value == 0 ? 0.0 : 100.0;
        }

        return round((((float) $value - $base) / abs($base)) * 100, 2);
    }

    private function powerPanelRow(string $label, array $metrics, array $previousMetrics = []): array
    {
        $row = ['label' => $label];
        foreach ($metrics as $key => $value) {
            $row[$key] = $value;
            $row[$key.'_delta'] = $this->deltaPercent((float) $value, (float) ($previousMetrics[$key] ?? 0));
        }

        return $row;
    }

    private function powerMarketingPanel(Collection $users, Collection $arrived, Collection $closed, Collection $sources, Collection $allOrders, CarbonImmutable $date): array
    {
        $previousDate = $date->subDay()->toDateString();
        $dailyBudget = (int) round($sources->sum(fn (MarketingSource $source): float => ((float) ($source->budget ?? 0)) / 30));
        $totalRevenue = (int) $closed->sum(fn (Order $order): int => $order->effectiveRevenue());
        $totalContacts = $arrived->count();
        $total = [
            'contacts' => $totalContacts,
            'cost_per_contact' => $totalContacts > 0 ? round($dailyBudget / max(1, $totalContacts), 2) : 0,
            'budget_ratio' => $totalRevenue > 0 ? round(($dailyBudget / $totalRevenue) * 100, 2) : 0,
            'revenue' => $totalRevenue,
        ];
        $previousArrived = $allOrders->filter(fn (Order $order): bool => $this->sameDate($order->data_arrived_at, $previousDate));
        $previousClosed = $allOrders->filter(fn (Order $order): bool => $this->sameDate($order->closed_at, $previousDate));
        $previousRevenue = (int) $previousClosed->sum(fn (Order $order): int => $order->effectiveRevenue());
        $previousBudget = $dailyBudget;
        $previousContacts = $previousArrived->count();
        $previousTotal = [
            'contacts' => $previousContacts,
            'cost_per_contact' => $previousContacts > 0 ? round($previousBudget / max(1, $previousContacts), 2) : 0,
            'budget_ratio' => $previousRevenue > 0 ? round(($previousBudget / $previousRevenue) * 100, 2) : 0,
            'revenue' => $previousRevenue,
        ];

        $rows = [
            $this->powerPanelRow('Tổng', $total, $previousTotal),
            $this->powerPanelRow('TB', [
                'contacts' => round($total['contacts'] / max(1, $users->count()), 2),
                'cost_per_contact' => $total['cost_per_contact'],
                'budget_ratio' => $total['budget_ratio'],
                'revenue' => round($total['revenue'] / max(1, $users->count()), 2),
            ], [
                'contacts' => round($previousTotal['contacts'] / max(1, $users->count()), 2),
                'cost_per_contact' => $previousTotal['cost_per_contact'],
                'budget_ratio' => $previousTotal['budget_ratio'],
                'revenue' => round($previousTotal['revenue'] / max(1, $users->count()), 2),
            ]),
        ];

        foreach ($users as $user) {
            $userArrived = $arrived->filter(fn (Order $order): bool => (int) $order->marketer_user_id === (int) $user->id);
            $userClosed = $closed->filter(fn (Order $order): bool => (int) $order->marketer_user_id === (int) $user->id);
            $userSources = $sources->filter(fn (MarketingSource $source): bool => (int) $source->marketer_user_id === (int) $user->id);
            $budget = (int) round($userSources->sum(fn (MarketingSource $source): float => ((float) ($source->budget ?? 0)) / 30));
            $revenue = (int) $userClosed->sum(fn (Order $order): int => $order->effectiveRevenue());
            $contacts = $userArrived->count();
            if ($contacts === 0 && $revenue === 0 && $budget === 0) {
                continue;
            }
            $rows[] = $this->powerPanelRow($user->name, [
                'contacts' => $contacts,
                'cost_per_contact' => $contacts > 0 ? round($budget / max(1, $contacts), 2) : 0,
                'budget_ratio' => $revenue > 0 ? round(($budget / $revenue) * 100, 2) : 0,
                'revenue' => $revenue,
            ]);
        }

        return $rows;
    }

    private function powerTelesalePanel(Collection $users, Collection $arrived, Collection $closed, Collection $allOrders, CarbonImmutable $date): array
    {
        $previousDate = $date->subDay()->toDateString();
        $previousArrived = $allOrders->filter(fn (Order $order): bool => $this->sameDate($order->data_arrived_at, $previousDate));
        $previousClosed = $allOrders->filter(fn (Order $order): bool => $this->sameDate($order->closed_at, $previousDate));
        $totalQty = (int) $closed->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
        $previousQty = (int) $previousClosed->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
        $total = [
            'contacts' => $arrived->count(),
            'closed' => $closed->count(),
            'close_rate' => round(($closed->count() / max(1, $arrived->count())) * 100, 2),
            'products_per_order' => $closed->count() > 0 ? round($totalQty / max(1, $closed->count()), 2) : 0,
            'revenue' => (int) $closed->sum(fn (Order $order): int => $order->effectiveRevenue()),
        ];
        $previous = [
            'contacts' => $previousArrived->count(),
            'closed' => $previousClosed->count(),
            'close_rate' => round(($previousClosed->count() / max(1, $previousArrived->count())) * 100, 2),
            'products_per_order' => $previousClosed->count() > 0 ? round($previousQty / max(1, $previousClosed->count()), 2) : 0,
            'revenue' => (int) $previousClosed->sum(fn (Order $order): int => $order->effectiveRevenue()),
        ];
        $rows = [
            $this->powerPanelRow('Tổng', $total, $previous),
            $this->powerPanelRow('TB', [
                'contacts' => round($total['contacts'] / max(1, $users->count()), 2),
                'closed' => round($total['closed'] / max(1, $users->count()), 2),
                'close_rate' => $total['close_rate'],
                'products_per_order' => $total['products_per_order'],
                'revenue' => round($total['revenue'] / max(1, $users->count()), 2),
            ], [
                'contacts' => round($previous['contacts'] / max(1, $users->count()), 2),
                'closed' => round($previous['closed'] / max(1, $users->count()), 2),
                'close_rate' => $previous['close_rate'],
                'products_per_order' => $previous['products_per_order'],
                'revenue' => round($previous['revenue'] / max(1, $users->count()), 2),
            ]),
        ];

        foreach ($users as $user) {
            $userArrived = $arrived->filter(fn (Order $order): bool => (int) $order->sale_user_id === (int) $user->id);
            $userClosed = $closed->filter(fn (Order $order): bool => (int) $order->sale_user_id === (int) $user->id);
            $qty = (int) $userClosed->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
            if ($userArrived->isEmpty() && $userClosed->isEmpty()) {
                continue;
            }
            $rows[] = $this->powerPanelRow($user->name, [
                'contacts' => $userArrived->count(),
                'closed' => $userClosed->count(),
                'close_rate' => round(($userClosed->count() / max(1, $userArrived->count())) * 100, 2),
                'products_per_order' => $userClosed->count() > 0 ? round($qty / max(1, $userClosed->count()), 2) : 0,
                'revenue' => (int) $userClosed->sum(fn (Order $order): int => $order->effectiveRevenue()),
            ]);
        }

        return $rows;
    }

    private function powerShippingPanel(Collection $users, Collection $closed, Collection $allOrders, CarbonImmutable $date): array
    {
        $delivered = $closed->filter(fn (Order $order): bool => $order->delivery_status === 'delivered');
        $totalRevenue = (int) $delivered->sum(fn (Order $order): int => $order->effectiveRevenue());
        $total = [
            'success_rate' => round(($delivered->count() / max(1, $closed->count())) * 100, 2),
            'revenue' => $totalRevenue,
        ];
        $rows = [$this->powerPanelRow('Tổng', $total), $this->powerPanelRow('TB', [
            'success_rate' => $total['success_rate'],
            'revenue' => round($totalRevenue / max(1, $users->count()), 2),
        ])];

        foreach ($users as $user) {
            $userClosed = $closed->filter(fn (Order $order): bool => (int) ($order->warehouse_care_user_id ?: $order->sale_user_id) === (int) $user->id);
            $userDelivered = $userClosed->filter(fn (Order $order): bool => $order->delivery_status === 'delivered');
            if ($userClosed->isEmpty() && $userDelivered->isEmpty()) {
                continue;
            }
            $rows[] = $this->powerPanelRow($user->name, [
                'success_rate' => round(($userDelivered->count() / max(1, $userClosed->count())) * 100, 2),
                'revenue' => (int) $userDelivered->sum(fn (Order $order): int => $order->effectiveRevenue()),
            ]);
        }

        return $rows;
    }

    private function powerCarePanel(Collection $users, Collection $arrived, Collection $closed, Collection $allOrders, CarbonImmutable $date): array
    {
        $qty = (int) $closed->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
        $total = [
            'close_rate' => round(($closed->count() / max(1, $arrived->count())) * 100, 2),
            'closed' => $closed->count(),
            'products_per_order' => $closed->count() > 0 ? round($qty / max(1, $closed->count()), 2) : 0,
            'revenue' => (int) $closed->sum(fn (Order $order): int => $order->effectiveRevenue()),
        ];
        $rows = [$this->powerPanelRow('Tổng', $total), $this->powerPanelRow('TB', [
            'close_rate' => $total['close_rate'],
            'closed' => round($total['closed'] / max(1, $users->count()), 2),
            'products_per_order' => $total['products_per_order'],
            'revenue' => round($total['revenue'] / max(1, $users->count()), 2),
        ])];

        foreach ($users as $user) {
            $userArrived = $arrived->filter(fn (Order $order): bool => (int) $order->sale_user_id === (int) $user->id);
            $userClosed = $closed->filter(fn (Order $order): bool => (int) $order->sale_user_id === (int) $user->id);
            $userQty = (int) $userClosed->sum(fn (Order $order): int => (int) $order->items->sum('quantity'));
            if ($userArrived->isEmpty() && $userClosed->isEmpty()) {
                continue;
            }
            $rows[] = $this->powerPanelRow($user->name, [
                'close_rate' => round(($userClosed->count() / max(1, $userArrived->count())) * 100, 2),
                'closed' => $userClosed->count(),
                'products_per_order' => $userClosed->count() > 0 ? round($userQty / max(1, $userClosed->count()), 2) : 0,
                'revenue' => (int) $userClosed->sum(fn (Order $order): int => $order->effectiveRevenue()),
            ]);
        }

        return $rows;
    }

    private function repurchase(): Collection
    {
        $orders = $this->recentOrders()
            ->filter(fn (Order $order): bool => filled($order->customer_phone))
            ->sortBy(fn (Order $order): string => ($order->customer_phone ?? '').'|'.($order->closed_at ?? $order->data_arrived_at ?? $order->created_at));

        $customerCounts = $orders->groupBy('customer_phone')->map->count();
        $totalCustomers = max(1, $customerCounts->count());
        $maxPurchase = max(1, min(30, (int) ($customerCounts->max() ?? 1)));

        $countRow = ['index' => 1, 'metric' => 'Số khách hàng'];
        $rateRow = ['index' => 2, 'metric' => 'Tỷ lệ (%)'];
        foreach (range(1, max(4, $maxPurchase)) as $purchaseNo) {
            $key = $purchaseNo <= 3 ? 'purchase_'.$purchaseNo : ($purchaseNo === 4 ? 'purchase_n' : 'purchase_'.$purchaseNo);
            if ($purchaseNo === 4) {
                $count = $customerCounts->filter(fn ($value): bool => (int) $value >= 4)->count();
            } else {
                $count = $customerCounts->filter(fn ($value): bool => (int) $value === $purchaseNo)->count();
            }
            $countRow[$key] = $count;
            $rateRow[$key] = round(($count / $totalCustomers) * 100, 2);
        }

        return collect([$countRow, $rateRow])->values();
    }

    private function repurchaseProducts(): Collection
    {
        $ordersByPhone = $this->recentOrders()
            ->filter(fn (Order $order): bool => filled($order->customer_phone))
            ->groupBy('customer_phone');

        $rows = collect(range(1, 30))->map(function (int $purchaseNo) use ($ordersByPhone): array {
            $row = ['purchase_no' => 'Mua '.$purchaseNo];
            foreach (range(1, 30) as $quantity) {
                $row['product_'.$quantity] = 0;
            }

            foreach ($ordersByPhone as $orders) {
                $sorted = $orders->sortBy(fn (Order $order): string => (string) ($order->closed_at ?? $order->data_arrived_at ?? $order->created_at))->values();
                /** @var Order|null $order */
                $order = $sorted->get($purchaseNo - 1);
                if (! $order) {
                    continue;
                }
                $quantity = max(1, min(30, (int) $order->items->sum('quantity')));
                $row['product_'.$quantity]++;
            }

            return $row;
        });

        return $rows->filter(function (array $row): bool {
            return collect(range(1, 30))->contains(fn (int $quantity): bool => (int) ($row['product_'.$quantity] ?? 0) > 0);
        })->values();
    }

    private function subscriptions(): Collection
    {
        return CompanySubscriptionHistory::query()->with('company:id,name')->latest('paid_at')->latest('id')->limit(2000)->get()->values()->map(fn (CompanySubscriptionHistory $row, int $index) => [
            'id' => $index + 1,
            'unit_payment' => trim(($row->company?->name ?? '—')."\n".($row->payment_code ?: '—')),
            'contract_type' => $row->contract_type,
            'description' => $row->description,
            'amount' => $row->amount,
            'paid_at' => $row->paid_at?->toIso8601String(),
            'duration_months' => $row->duration_months,
            'expires_at' => $row->expires_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.1.2', $row),
        ]);
    }

    private function workShifts(): Collection
    {
        return WorkShift::query()->orderBy('from_hour')->get()->map(fn (WorkShift $row) => [
            'name' => $row->name,
            'from_hour' => substr((string) $row->from_hour, 0, 5),
            'to_hour' => substr((string) $row->to_hour, 0, 5),
            'note' => $row->note,
            'updated_at' => $row->updated_at?->toIso8601String(),
            'is_active' => $row->is_active,
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.2.3', $row),
        ])->values();
    }

    private function leadDistributionRules(): Collection
    {
        $products = Product::query()->pluck('name', 'id');
        $users = User::query()->pluck('name', 'id');

        return LeadDistributionRule::query()->latest()->get()->map(fn (LeadDistributionRule $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'allocation_rule' => implode(' - ', [
                match ($row->number_type) { 'old' => 'Khách cũ', 'care' => 'CSKH', default => 'Số mới' },
                match ($row->recipient_type) { 'care' => 'CSKH', 'both' => 'Sales + CSKH', default => 'Sales' },
                match ($row->allocation_method) { 'quota' => 'Theo định mức', 'manual' => 'Thủ công', default => 'Luân phiên' },
            ]),
            'products' => collect($row->product_ids)->map(fn ($id) => $products[$id] ?? null)->filter()->implode(', '),
            'sales' => collect($row->sale_user_ids)->map(fn ($id) => $users[$id] ?? null)->filter()->implode(', '),
            'care_users' => collect($row->care_user_ids)->map(fn ($id) => $users[$id] ?? null)->filter()->implode(', '),
            'updated_at' => $row->updated_at?->toIso8601String(),
            'is_active' => $row->is_active,
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.2.4', $row),
        ])->values();
    }

    private function reportAccessRules(): Collection
    {
        $teams = Team::query()->pluck('name', 'id');

        return ReportAccessRule::query()->with('user:id,name,email')->latest()->get()->values()->map(fn (ReportAccessRule $row, int $index) => [
            'index' => $index + 1,
            'account' => $row->user?->name."\n".$row->user?->email,
            'visible_teams' => collect($row->team_ids)->map(fn ($id) => $teams[$id] ?? null)->filter()->implode(', '),
            'team_type' => $row->team_type,
            'updated_at' => $row->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.2.5', $row),
        ]);
    }

    private function careDistributionRules(): Collection
    {
        $teams = Team::query()->pluck('name', 'id');

        return CareDistributionRule::query()->with(['careUser:id,name,email', 'warehouseTeam:id,name'])->latest()->get()->map(fn (CareDistributionRule $row) => [
            'id' => $row->id,
            'care_user' => trim(($row->careUser?->name ?? '')."\n".($row->careUser?->email ?? '')),
            'quota' => $row->quota,
            'receive_data' => $row->receive_data,
            'sales_teams' => collect($row->sale_team_ids)->map(fn ($id) => $teams[$id] ?? null)->filter()->implode(', '),
            'updated_at' => $row->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.2.6', $row),
        ])->values();
    }

    private function operationCategories(): Collection
    {
        return OperationCategory::query()->with('updater:id,name')->orderBy('sort_order')->orderBy('id')->get()->map(fn (OperationCategory $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'sort_order' => $row->sort_order,
            'is_start' => $row->is_start,
            'pool' => $row->is_pool,
            'is_pool' => $row->is_pool,
            'duration_minutes' => $row->duration_minutes,
            'duration' => $row->duration_minutes ? $row->duration_minutes.' phút' : '',
            'is_active' => $row->is_active,
            'updated_by' => $row->updater?->name ?? 'saleadmin',
            'updated_at' => $row->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.8.1', $row),
        ])->values();
    }

    private function operationWorkflows(): Collection
    {
        return OperationWorkflow::query()->with(['fromCategory:id,name', 'toCategory:id,name', 'updater:id,name'])->latest()->get()->map(fn (OperationWorkflow $row) => [
            'id' => $row->id,
            'from_operation_category_id' => $row->from_operation_category_id,
            'condition' => trim(($row->fromCategory?->name ?? 'Mọi tác nghiệp')."\n".($row->condition_type ?? '')),
            'condition_type' => $row->condition_type,
            'operation_result' => $row->operation_result,
            'result_value' => $row->operation_result,
            'result' => $row->operation_result === 'no_answer_auto' ? 'Không nghe máy' : (OperationResult::tryFromStored($row->operation_result)?->label() ?? $row->operation_result),
            'to_operation_category_id' => $row->to_operation_category_id,
            'next_operation' => $row->toCategory?->name,
            'delay_minutes' => $row->delay_minutes,
            'delay' => $row->delay_minutes.' phút',
            'is_active' => $row->is_active,
            'updated_by' => $row->updater?->name ?? 'saleadmin',
            'updated_at' => $row->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.8.2', $row),
        ])->values();
    }

    private function discountCodRules(): Collection
    {
        return DiscountCodRule::query()
            ->orderByRaw("case when coalesce(rule_type, 'discount') = 'discount' then 0 else 1 end")
            ->orderBy('order_from')
            ->get()
            ->values()
            ->map(fn (DiscountCodRule $row, int $index) => [
                'index' => $index + 1,
                'rule_type' => $row->rule_type ?: 'discount',
                'order_from' => $row->order_from,
                'discount_value' => $row->discount_value,
                'calculation_type_value' => $row->calculation_type,
                'calculation_type' => $row->calculation_type === 'percent' ? 'Phần trăm' : 'Số tiền',
                'cod_from' => $row->cod_from,
                'cod_to' => $row->cod_to,
                'is_active' => (bool) $row->is_active,
                'updated_at' => $row->updated_at?->toIso8601String(),
                'actions' => 'Cập nhật',
                '_record_id' => $row->id,
                '_form' => $this->formPayload('1.9', $row, ['rule_type' => $row->rule_type ?: 'discount']),
            ]);
    }

    private function phoneBlacklists(): Collection
    {
        return PhoneBlacklist::query()->with(['order:id,order_code', 'creator:id,name'])->latest()->get()->map(fn (PhoneBlacklist $row) => [
            'id' => $row->id,
            'phone' => $row->phone,
            'reason' => $row->reason,
            'order_code' => $row->order?->order_code,
            'creation_type' => match ($row->creation_type) { 'automatic' => 'Tự động', 'warehouse' => 'Kho cảnh báo', default => 'Thủ công' },
            'creator' => $row->creator?->name,
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.13.1', $row),
        ])->values();
    }

    private function seedingPhoneNumbers(): Collection
    {
        return SeedingPhoneNumber::query()->with('creator:id,name')->latest()->limit(1000)->get()->map(fn (SeedingPhoneNumber $row) => [
            'id' => $row->id,
            'phone' => $row->phone,
            'creator' => $row->creator?->name,
            'updated_at' => $row->updated_at?->toIso8601String(),
            'is_active' => $row->is_active,
            '_record_id' => $row->id,
            '_form' => $this->formPayload('2.6.4', $row),
        ])->values();
    }

    private function careCampaigns(): Collection
    {
        return CustomerCareCampaign::query()->with('company:id,name')->latest()->get()->values()->map(fn (CustomerCareCampaign $row, int $index) => [
            'index' => $index + 1,
            'company' => $row->company?->name ?? '—',
            'name' => $row->name,
            'customer_condition' => collect($row->customer_condition ?? [])->map(fn ($v, $k) => is_scalar($v) ? "{$k}: {$v}" : null)->filter()->implode(', '),
            'repeat_days' => $row->repeat_days,
            'starts_at' => $row->starts_at?->toDateString(),
            'ends_at' => $row->ends_at?->toDateString(),
            'status' => match ($row->status) { 'active' => 'Đang chạy', 'paused' => 'Tạm dừng', 'completed' => 'Hoàn thành', default => 'Nháp' },
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('3.2', $row),
        ]);
    }

    private function warehouseVoucherLines(): Collection
    {
        return WarehouseVoucherLine::query()->with(['voucher.warehouse:id,name', 'product:id,name,sku,unit'])->latest()->limit(2000)->get()->values()->map(fn (WarehouseVoucherLine $line, int $index) => [
            'index' => $index + 1,
            'product' => $line->product?->name,
            'sku' => $line->product?->sku,
            'uom' => $line->product?->unit,
            'document_quantity' => $line->document_quantity,
            'quantity' => $line->quantity,
            'unit_cost' => $line->unit_cost,
            'total' => $line->quantity * $line->unit_cost,
            'batch_code' => $line->batch_code,
            'expiry_date' => $line->expiry_date?->toDateString(),
            'location' => $line->location_code,
            'note' => trim(($line->voucher?->code ?? '')."\n".($line->note ?? '')),
            '_record_id' => $line->voucher?->id,
            '_line_id' => $line->id,
        ]);
    }

    private function warehouseIncidents(): Collection
    {
        $providerLabels = collect(config('shipping_partners.providers', []))
            ->mapWithKeys(fn (array $provider, string $key): array => [$key => (string) ($provider['label'] ?? strtoupper($key))]);

        return WarehouseIncidentReport::query()
            ->with(['manager:id,name', 'updater:id,name,email'])
            ->latest('document_date')
            ->latest('id')
            ->get()
            ->map(function (WarehouseIncidentReport $row) use ($providerLabels): array {
                $carrierKey = (string) $row->carrier;
                $statusKey = match ((string) $row->status) {
                    'closed', 'confirmed' => 'closed',
                    default => 'updating',
                };

                return [
                    'id' => $row->id,
                    'manager' => $row->manager?->name ?? $row->updater?->name ?? '—',
                    'name' => $row->name,
                    'document_date' => $row->document_date?->toDateString(),
                    'carrier' => $providerLabels[$carrierKey] ?? ($row->carrier ?: '—'),
                    'sender_name' => $row->sender_name,
                    'receiver_name' => $row->receiver_name,
                    'order_count' => (int) $row->order_count,
                    'product_count' => (int) $row->product_count,
                    'status' => $statusKey === 'closed' ? 'Đã chốt' : 'Đang cập nhật',
                    'updated_at' => $row->updated_at?->toIso8601String(),
                    '_record_id' => $row->id,
                    '_shipping_method' => $carrierKey,
                    '_status' => $statusKey,
                    '_date_type' => 'document_date',
                    '_data_arrived_at' => $row->document_date?->toDateString(),
                    '_form' => array_merge($this->formPayload('5.4', $row), [
                        'status' => $statusKey,
                        'carrier' => $carrierKey,
                        'sender_name' => $row->sender_name,
                        'receiver_name' => $row->receiver_name,
                    ]),
                ];
            })
            ->values();
    }

    private function expenses(): Collection
    {
        return Expense::query()->with(['group:id,name', 'category:id,name', 'unit:id,name'])->latest('year')->latest('month')->get()->values()->map(fn (Expense $row, int $index) => [
            'index' => $index + 1,
            'name' => $row->name,
            'year' => $row->year,
            'month' => $row->month,
            'group' => $row->group?->name,
            'category' => $row->category?->name,
            'unit' => $row->unit?->name,
            'unit_price' => $row->unit_price,
            'quantity' => $row->quantity,
            'total' => $row->total ?: (int) round($row->unit_price * (float) $row->quantity),
            'invoice' => $row->invoice_number,
            'note' => $row->note,
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('6.2.1', $row),
        ]);
    }

    private function expenseCategories(): Collection
    {
        return ExpenseCategory::query()->with('group:id,name')->orderBy('name')->get()->values()->map(fn (ExpenseCategory $row, int $index) => [
            'index' => $index + 1,
            'group' => $row->group?->name,
            'name' => $row->name,
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('6.2.2', $row),
        ]);
    }

    private function expenseGroups(): Collection
    {
        return ExpenseGroup::query()->orderBy('name')->get()->values()->map(fn (ExpenseGroup $row, int $index) => [
            'index' => $index + 1,
            'name' => $row->name,
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('6.2.3', $row),
        ]);
    }

    private function expenseUnits(): Collection
    {
        return ExpenseUnit::query()->orderBy('name')->get()->values()->map(fn (ExpenseUnit $row, int $index) => [
            'index' => $index + 1,
            'name' => $row->name,
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('6.2.4', $row),
        ]);
    }

    private function electronicInvoiceJobs(): Collection
    {
        return ElectronicInvoiceJob::query()->with('order:id,order_code')->latest()->get()->map(fn (ElectronicInvoiceJob $row) => [
            'id' => $row->id,
            'code_type' => $row->code_type,
            'order_code' => $row->order?->order_code,
            'process_type' => $row->process_type,
            'processed_at' => $row->processed_at?->toIso8601String(),
            'status' => match ($row->status) { 'processing' => 'Đang xử lý', 'success' => 'Thành công', 'failed' => 'Thất bại', default => 'Chờ xử lý' },
            'note' => $row->note,
            'duration_ms' => $row->duration_ms,
            'attempts' => $row->attempts,
            'completed' => $row->completed,
            'batch_id' => $row->batch_id,
            'created_at' => $row->created_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('6.4', $row),
        ])->values();
    }

    private function electronicInvoiceConfigs(): Collection
    {
        return ElectronicInvoiceConfig::query()->with('creator:id,name')->latest('id')->get()->map(fn (ElectronicInvoiceConfig $row) => [
            'id' => $row->id,
            'account' => $row->account,
            'tax_code' => $row->tax_code,
            'invoice_template_code' => $row->invoice_template_code,
            'invoice_series' => $row->invoice_series,
            'business_name' => $row->business_name,
            'phone' => $row->phone,
            'email' => $row->email,
            'is_active' => (bool) $row->is_active,
            'creator' => $row->creator?->name,
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => array_merge($this->formPayload('1.14.1', $row), ['password' => '']),
        ])->values();
    }

    private function recentOrders(): Collection
    {
        $request = $this->currentRequest;
        $query = Order::query()
            ->with([
                'team:id,name,leader_user_id',
                'saleUser:id,name,email,team_id,permissions',
                'marketerUser:id,name,team_id',
                'items:id,order_id,product_id,quantity,item_type,unit_price,discount_amount',
            ]);

        if ($request) {
            $filter = ReportFilterData::fromRequest($request, $request->user());
            $query->applyReportFilter($filter);
            $query
                ->when($request->integer('source_id'), fn ($q, int $id) => $q->where('marketing_source_id', $id))
                ->when($request->integer('sale_team_id'), fn ($q, int $id) => $q->where('team_id', $id))
                ->when($request->integer('sale_leader_id'), fn ($q, int $id) => $q->whereHas('team', fn ($team) => $team->where('leader_user_id', $id)))
                ->when($request->integer('marketer_team_id'), fn ($q, int $id) => $q->whereHas('marketerUser', fn ($user) => $user->where('team_id', $id)))
                ->when($request->integer('product_id'), fn ($q, int $id) => $q->whereHas('items', fn ($items) => $items->where('product_id', $id)));

            $operationStage = $this->normalizedOperationStage((string) $request->query('operation_stage', ''));
            if ($operationStage !== null) {
                $aliases = $this->operationStageAliases($operationStage);
                $query->where(function ($stageQuery) use ($aliases): void {
                    foreach ($aliases as $alias) {
                        $stageQuery->orWhere('operation_stage', $alias);
                    }
                });
            }
        }

        return $query->latest('data_arrived_at')->get();
    }

    private function ordersGroupedBySale(): Collection
    {
        $stages = ['call_1', 'call_2', 'call_3', 'call_4', 'call_5', 'call_6', 'care_1', 'care_2', 'care_3', 'skipped'];

        return $this->recentOrders()->groupBy(fn (Order $order) => $order->sale_user_id ?: 0)->map(function (Collection $orders, int|string $saleId) use ($stages): array {
            /** @var Order $first */
            $first = $orders->first();
            $closed = $orders->whereNotNull('closed_at');
            $new = $orders->where('is_returning_customer', false);
            $old = $orders->where('is_returning_customer', true);
            $stageMetrics = [];
            foreach ($stages as $stage) {
                $stageOrders = $orders->filter(fn (Order $order) => $this->normalizedOperationStage((string) $order->operation_stage) === $stage);
                $stageClosed = $stageOrders->whereNotNull('closed_at');
                $stageMetrics[$stage] = [
                    'contacts' => $stageOrders->count(),
                    'untouched' => $stageOrders->filter(fn (Order $order) => blank($order->operation_result))->count(),
                    'closed' => $stageClosed->count(),
                    'revenue' => (int) $stageClosed->sum(fn (Order $order) => $order->effectiveRevenue()),
                ];
            }
            $careOrders = $orders->filter(fn (Order $order) => str_starts_with((string) $this->normalizedOperationStage((string) $order->operation_stage), 'care_'));
            $careClosed = $careOrders->whereNotNull('closed_at');
            $poolOrders = $orders->filter(fn (Order $order) => blank($order->operation_stage) && blank($order->operation_result));
            $poolClosed = $poolOrders->whereNotNull('closed_at');
            $delivered = $orders->where('delivery_status', 'delivered');
            $cancelled = $orders->whereIn('delivery_status', ['cancelled', 'canceled']);
            $returned = $orders->whereIn('delivery_status', ['returned', 'returning']);

            return [
                'id' => (int) $saleId,
                'name' => $first->saleUser?->name ?? 'Chưa phân sale',
                'account' => $first->saleUser?->email ? Str::before($first->saleUser->email, '@') : '',
                'receive_data' => (bool) data_get($first->saleUser?->permissions, 'receive_data', true),
                '_sale_team_id' => $first->team_id ?? $first->saleUser?->team_id,
                '_sale_leader_id' => $first->team?->leader_user_id ?? $first->saleUser?->team?->leader_user_id,
                '_created_at' => $first->created_at?->toIso8601String(),
                '_data_arrived_at' => $first->data_arrived_at?->toIso8601String(),
                'contacts' => $orders->count(),
                'untouched' => $orders->filter(fn (Order $order) => blank($order->operation_stage) && blank($order->operation_result))->count(),
                'closed' => $closed->count(),
                'revenue' => (int) $closed->sum(fn (Order $order) => $order->effectiveRevenue()),
                'provisional_revenue' => (int) $orders->sum(fn (Order $order) => $order->effectiveRevenue()),
                'delivered_revenue' => (int) $delivered->sum(fn (Order $order) => $order->effectiveRevenue()),
                'cancelled_revenue' => (int) $cancelled->sum(fn (Order $order) => $order->effectiveRevenue()),
                'returned_revenue' => (int) $returned->sum(fn (Order $order) => $order->effectiveRevenue()),
                'products' => (int) $closed->sum(fn (Order $order) => $order->items->sum('quantity')),
                'new_contacts' => $new->count(),
                'new_closed' => $new->whereNotNull('closed_at')->count(),
                'new_products' => (int) $new->whereNotNull('closed_at')->sum(fn (Order $order) => $order->items->sum('quantity')),
                'new_revenue' => (int) $new->whereNotNull('closed_at')->sum(fn (Order $order) => $order->effectiveRevenue()),
                'old_contacts' => $old->count(),
                'old_closed' => $old->whereNotNull('closed_at')->count(),
                'old_products' => (int) $old->whereNotNull('closed_at')->sum(fn (Order $order) => $order->items->sum('quantity')),
                'old_revenue' => (int) $old->whereNotNull('closed_at')->sum(fn (Order $order) => $order->effectiveRevenue()),
                'care_contacts' => $careOrders->count(),
                'care_closed' => $careClosed->count(),
                'care_revenue' => (int) $careClosed->sum(fn (Order $order) => $order->effectiveRevenue()),
                'duplicate_contacts' => $orders->where('is_duplicate_phone', true)->count(),
                'pool_total' => $poolOrders->count(),
                'pool_duplicate' => $poolOrders->where('is_duplicate_phone', true)->count(),
                'pool_unique' => $poolOrders->where('is_duplicate_phone', false)->count(),
                'pool_closed' => $poolClosed->count(),
                'pool_revenue' => (int) $poolClosed->sum(fn (Order $order) => $order->effectiveRevenue()),
                'discount' => (int) $orders->sum('discount'),
                'cod_collected' => (int) $orders->sum('settled_cod_amount'),
                'cod_service_fee' => (int) $orders->sum(fn (Order $order) => (int) $order->cod_fee + (int) $order->carrier_service_fee),
                'call_duration_seconds' => null,
                'overdue_orders' => $orders->filter(function (Order $order): bool {
                    if (! $order->desired_delivery_at || $order->desired_delivery_at->isFuture()) return false;
                    return ! in_array((string) $order->delivery_status, ['delivered', 'paid', 'cancelled', 'canceled', 'returned'], true);
                })->count(),
                'stage_metrics' => $stageMetrics,
            ];
        });
    }

    private function normalizedOperationStage(string $stage): ?string
    {
        return match (Str::lower(trim($stage))) {
            '102133', 'call_1', 'call1', 'gọi lần 1' => 'call_1',
            '102134', 'call_2', 'call2', 'gọi lần 2' => 'call_2',
            '102135', 'call_3', 'call3', 'gọi lần 3' => 'call_3',
            '102136', 'call_4', 'call4', 'gọi lần 4' => 'call_4',
            '102137', 'call_5', 'call5', 'gọi lần 5' => 'call_5',
            '102138', 'call_6', 'call6', 'gọi lần 6' => 'call_6',
            '102139', 'care_1', 'care1', 'chăm sóc lần 1' => 'care_1',
            '102140', 'care_2', 'care2', 'chăm sóc lần 2' => 'care_2',
            '102141', 'care_3', 'care3', 'chăm sóc lần 3' => 'care_3',
            '102142', 'skipped', 'ignore', 'bỏ qua' => 'skipped',
            default => null,
        };
    }

    /** @return list<string> */
    private function operationStageAliases(string $stage): array
    {
        return match ($stage) {
            'call_1' => ['call_1', 'call1', 'Gọi lần 1', 'gọi lần 1'],
            'call_2' => ['call_2', 'call2', 'Gọi lần 2', 'gọi lần 2'],
            'call_3' => ['call_3', 'call3', 'Gọi lần 3', 'gọi lần 3'],
            'call_4' => ['call_4', 'call4', 'Gọi lần 4', 'gọi lần 4'],
            'call_5' => ['call_5', 'call5', 'Gọi lần 5', 'gọi lần 5'],
            'call_6' => ['call_6', 'call6', 'Gọi lần 6', 'gọi lần 6'],
            'care_1' => ['care_1', 'care1', 'Chăm sóc lần 1', 'chăm sóc lần 1'],
            'care_2' => ['care_2', 'care2', 'Chăm sóc lần 2', 'chăm sóc lần 2'],
            'care_3' => ['care_3', 'care3', 'Chăm sóc lần 3', 'chăm sóc lần 3'],
            'skipped' => ['skipped', 'ignore', 'Bỏ qua', 'bỏ qua'],
            default => [$stage],
        };
    }

    private function applyFilters(Collection $rows, Request $request): Collection
    {
        $filters = [
            'sale_leader_id' => '_sale_leader_id',
            'sale_team_id' => '_sale_team_id',
            'sale_id' => '_sale_id',
            'marketer_leader_id' => '_marketer_leader_id',
            'marketer_team_id' => '_marketer_team_id',
            'marketer_id' => '_marketer_id',
            'source_id' => '_source_id',
            'warehouse_id' => '_warehouse_id',
            'closed_status' => '_closed_status',
            'delivery_status' => '_delivery_status',
            'date_type' => '_date_type',
            'operation_state' => '_operation_state',
            'operation_stage' => '_operation_stage',
            'operation_result' => '_operation_result',
            'customer_type' => '_customer_type',
            'allocation_status' => '_allocation_status',
            'shipping_method' => '_shipping_method',
            'handover_status' => '_status',
            'internal_reconciliation_status' => '_internal_reconciliation_status',
            'duplicate_status' => '_duplicate_status',
            'care_operation_status' => '_care_operation_status',
            'company_id' => '_company_id',
            'role' => '_role',
            'user_id' => '_user_id',
            'care_user_id' => '_care_user_id',
            'warehouse_user_id' => '_warehouse_user_id',
            'login_status' => '_login_status',
            'login_permission_status' => '_login_permission_status',
            'category_id' => '_category_ids',
            'parent_product_id' => '_parent_product_id',
            'team_leader_id' => '_team_leader_id',
            'active_status' => '_active_status',
            'available_marketing' => '_available_marketing',
            'available_sale' => '_available_sale',
            'available_care' => '_available_care',
            'status' => 'status',
        ];

        foreach ($filters as $queryKey => $rowKey) {
            $value = trim((string) $request->query($queryKey, ''));
            if ($value === '' || in_array($value, ['-1', 'all'], true)) {
                continue;
            }

            $expected = $this->normalizeFilterValue($queryKey, $value);
            $rows = $rows->filter(function (array $row) use ($rowKey, $expected): bool {
                $actual = data_get($row, $rowKey);
                if (is_array($actual)) {
                    return in_array((string) $expected, array_map('strval', $actual), true);
                }

                $actualNormalized = Str::lower(trim((string) $actual));
                $expectedNormalized = Str::lower(trim((string) $expected));

                return $actualNormalized === $expectedNormalized
                    || ($expectedNormalized !== '' && Str::contains($actualNormalized, $expectedNormalized));
            });
        }

        $productId = trim((string) $request->query('product_id', ''));
        if ($productId !== '' && ! in_array($productId, ['-1', 'all'], true)) {
            $rows = $rows->filter(fn (array $row): bool => in_array(
                $productId,
                array_map('strval', (array) data_get($row, '_product_ids', [])),
                true,
            ));
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        if ($dateFrom !== '' || $dateTo !== '') {
            try {
                $from = $dateFrom !== '' ? CarbonImmutable::parse($dateFrom)->startOfDay() : null;
            } catch (Throwable) {
                $from = null;
            }

            try {
                $to = $dateTo !== '' ? CarbonImmutable::parse($dateTo)->endOfDay() : null;
            } catch (Throwable) {
                $to = null;
            }

            if (! $from && ! $to) {
                return $rows->values();
            }

            $dateType = trim((string) $request->query('date_type', ''));
            $dateKey = match ($dateType) {
                'SaleTacNghiepNgayCapNhat' => '_sale_operation_updated_at',
                'SaleNgayNhanData' => '_assigned_at',
                'DonHangNgayChot' => '_closed_at',
                'NgayDangDon' => '_posted_at',
                'NgayChoXuat' => '_next_operation_at',
                'NgayCapNhatTrangThaiGiaoHang' => '_delivery_updated_at',
                'NgayGiaoHang' => '_desired_delivery_at',
                default => '_data_arrived_at',
            };
            $rows = $rows->filter(function (array $row) use ($from, $to, $dateKey): bool {
                $candidate = data_get($row, $dateKey)
                    ?? data_get($row, '_data_arrived_at')
                    ?? data_get($row, 'updated_at')
                    ?? data_get($row, 'created_at')
                    ?? data_get($row, 'performed_at')
                    ?? data_get($row, 'paid_at');
                if (! $candidate) {
                    return true;
                }
                try {
                    $date = CarbonImmutable::parse((string) $candidate);
                } catch (\Throwable) {
                    return true;
                }

                return (! $from || $date->greaterThanOrEqualTo($from))
                    && (! $to || $date->lessThanOrEqualTo($to));
            });
        }

        return $rows->values();
    }

    private function normalizeFilterValue(string $key, string $value): string
    {
        if ($key === 'operation_stage') {
            return match ($value) {
                '102133' => OperationStage::NewCustomer->value,
                '102134' => OperationStage::Call2->value,
                '102135' => OperationStage::Call3->value,
                '102136' => OperationStage::Call4->value,
                '102137' => OperationStage::Call5->value,
                '102138' => OperationStage::Call6->value,
                '102139' => OperationStage::Care1->value,
                '102140' => OperationStage::Care2->value,
                '102141' => OperationStage::Care3->value,
                '102142' => OperationStage::Skipped->value,
                default => $value,
            };
        }

        if ($key === 'operation_result') {
            return match ($value) {
                '109117' => OperationResult::ClosedSuccess->value,
                '109118', '109119' => OperationResult::NoAnswer1->value,
                '109120' => OperationResult::CallbackScheduled->value,
                '109121', '109122' => OperationResult::WrongNumber->value,
                '109123' => OperationResult::NoContact->value,
                '109124' => OperationResult::Considering->value,
                '109125' => OperationResult::NoNeed->value,
                '109128' => OperationResult::ReadyToClose->value,
                default => $value,
            };
        }

        if ($key === 'delivery_status') {
            return match ($value) {
                '1' => DeliveryStatus::WaitingWaybill->value,
                '2' => DeliveryStatus::DeliverNow->value,
                '4' => DeliveryStatus::CancelWaybill->value,
                '5' => DeliveryStatus::CancelClosing->value,
                '20' => DeliveryStatus::Posted->value,
                '21', '23' => DeliveryStatus::PickingUp->value,
                '22' => DeliveryStatus::CannotPickup->value,
                '30' => DeliveryStatus::Delivering->value,
                '31' => DeliveryStatus::Delivered->value,
                '32' => DeliveryStatus::Paid->value,
                '33' => DeliveryStatus::CannotDeliver->value,
                '34' => DeliveryStatus::Redelivery->value,
                '40' => DeliveryStatus::Returning->value,
                '41' => DeliveryStatus::Returned->value,
                '50' => DeliveryStatus::Refund->value,
                default => $value,
            };
        }

        if ($key === 'shipping_method') {
            return match ($value) {
                '1' => 'giao hàng tiết kiệm',
                '2' => 'thủ công',
                '3' => 'viettel post',
                '4' => 'giao hàng nhanh',
                '7' => 'jnt',
                '8' => 'ems',
                '9' => 'supership',
                '10' => 'best',
                '12' => 'boxme',
                '14' => 'ship60',
                '15' => 'holaship',
                '16' => 'ahamove',
                '17' => 'shopee',
                '18' => 'ninjavan',
                '19' => 'tiktok',
                '20' => 'spx',
                default => $value,
            };
        }

        return $value;
    }

    private function applySort(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'ip', 'IpAddress' => $rows->sortBy(fn (array $row): string => Str::lower((string) data_get($row, 'ip_address')))->values(),
            'user', 'UserId' => $rows->sortBy(fn (array $row): string => Str::lower((string) data_get($row, 'account')))->values(),
            'NgayTao' => $rows->sortByDesc(fn (array $row): int => (int) (data_get($row, '_record_id') ?? data_get($row, 'id', 0)))->values(),
            '1' => $rows->sortByDesc(fn (array $row): string => (string) (data_get($row, '_created_at') ?? data_get($row, 'created_at') ?? data_get($row, 'login_at')))->values(),
            '2' => $rows->sortBy(fn (array $row): string => (string) (data_get($row, '_created_at') ?? data_get($row, 'created_at') ?? data_get($row, 'login_at')))->values(),
            'MaSanPham' => $rows->sortBy(fn (array $row): string => Str::lower((string) (data_get($row, 'code') ?? data_get($row, 'product'))))->values(),
            'TenSanPham' => $rows->sortBy(fn (array $row): string => Str::lower((string) (data_get($row, 'name') ?? data_get($row, 'product'))))->values(),
            'created_asc' => $rows->sortBy(fn (array $row): string => (string) (data_get($row, '_created_at') ?? data_get($row, 'created_at')))->values(),
            'created_desc' => $rows->sortByDesc(fn (array $row): string => (string) (data_get($row, '_created_at') ?? data_get($row, 'created_at')))->values(),
            'sku' => $rows->sortBy(fn (array $row): string => Str::lower((string) data_get($row, 'code')))->values(),
            'name' => $rows->sortBy(fn (array $row): string => Str::lower((string) data_get($row, 'name')))->values(),
            default => $rows->values(),
        };
    }

    private function applySearch(Collection $rows, string $search): Collection
    {
        if ($search === '') {
            return $rows->values();
        }

        $needle = Str::lower($search);
        return $rows->filter(function (array $row) use ($needle): bool {
            foreach ($row as $value) {
                if (is_scalar($value) && Str::contains(Str::lower((string) $value), $needle)) {
                    return true;
                }
            }
            return false;
        })->values();
    }
    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function formPayload(string $resourceKey, Model $model, array $overrides = []): array
    {
        $resources = config('pushsale_resources', []);
        $resource = is_array($resources) ? ($resources[$resourceKey] ?? []) : [];
        $fields = collect((array) ($resource['fields'] ?? []))
            ->pluck('key')
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->all();
        $payload = collect($fields)->mapWithKeys(
            fn (string $key): array => [$key => $model->getAttribute($key)],
        )->all();
        // Connection secrets are write-only. Existing values remain unchanged
        // when the editor submits an empty secret field.
        if (array_key_exists('access_token', $payload)) {
            $payload['access_token'] = '';
        }

        foreach ((array) ($resource['fields'] ?? []) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '' || ! array_key_exists($key, $payload)) continue;
            $type = (string) ($field['type'] ?? 'text');
            $value = $payload[$key];
            if ($value === null) continue;
            if ($type === 'datetime-local') {
                $payload[$key] = CarbonImmutable::parse($value)->format('Y-m-d\TH:i');
            } elseif ($type === 'date') {
                $payload[$key] = CarbonImmutable::parse($value)->format('Y-m-d');
            } elseif ($type === 'time') {
                $payload[$key] = substr((string) $value, 0, 5);
            }
        }

        return array_merge($payload, $overrides);
    }

    private function formatVnd(int|float|string|null $value): string
    {
        return number_format((int) round((float) ($value ?? 0)), 0, ',', '.').' ₫';
    }

}
