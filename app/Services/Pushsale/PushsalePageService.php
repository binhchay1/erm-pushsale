<?php

namespace App\Services\Pushsale;

use App\Data\ReportFilterData;
use App\Enums\DeliveryStatus;
use App\Enums\OperationResult;
use App\Enums\OperationStage;
use App\Enums\TeamType;
use App\Models\ActivityLog;
use App\Models\IntegrationConnection;
use App\Models\LeadIngestion;
use App\Models\MarketingSource;
use App\Models\Pushsale\CareDistributionRule;
use App\Models\Pushsale\CompanySubscriptionHistory;
use App\Models\Pushsale\CustomerCareCampaign;
use App\Models\Pushsale\DiscountCodRule;
use App\Models\Pushsale\ElectronicInvoiceJob;
use App\Models\Pushsale\Expense;
use App\Models\Pushsale\ExpenseCategory;
use App\Models\Pushsale\ExpenseGroup;
use App\Models\Pushsale\ExpenseUnit;
use App\Models\Pushsale\FacebookPageMapping;
use App\Models\Pushsale\LeadDistributionRule;
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
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
            default => collect(),
        };

        $rows = $this->applyFilters($rows, $request);
        $rows = $this->applySearch($rows, trim((string) $request->query('search', '')));
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

        // Nguồn dùng chung bởi các select/filter trong HTML gốc.
        $options = [
            'users' => $mapUsers(User::query()->orderBy('name')->limit(1000)->get(['id', 'name', 'email'])),
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
            'products' => Product::query()->orderBy('name')->limit(2000)->get(['id', 'name', 'type', 'unit_price', 'sku'])->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => trim($product->name.($product->sku ? " ({$product->sku})" : '')),
                'name' => $product->name,
                'type' => $product->type,
                'unit_price' => (int) $product->unit_price,
            ])->all(),
            'warehouses' => Warehouse::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn (Warehouse $warehouse) => ['id' => $warehouse->id, 'label' => $warehouse->name])->all(),
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
                        ->with('component:id,name,sku,unit_price')
                        ->where('combo_product_id', $product->id)
                        ->get();
                    $originalTotal = $items->sum(fn (ProductComboItem $item) => ((int) $item->quantity) * ((int) ($item->unit_price ?: $item->component?->unit_price)));

                    return [
                        'id' => $product->id,
                        'select' => false,
                        'code' => $product->sku ?: 'CB'.str_pad((string) $product->id, 5, '0', STR_PAD_LEFT),
                        'name' => $product->name,
                        'product_count' => $items->sum('quantity'),
                        'original_total' => $originalTotal,
                        'combo_total' => (int) $product->unit_price,
                        'status' => $product->is_active ? 'Đang áp dụng' : 'Ngừng áp dụng',
                        'applied_at' => $product->created_at?->format('d/m/Y'),
                        'limit_quantity' => data_get($product->metadata, 'limit_quantity'),
                        'sold' => (int) data_get($product->metadata, 'sold', 0),
                        'remaining' => data_get($product->metadata, 'remaining'),
                        'shipping_support' => (int) data_get($product->metadata, 'shipping_support', 0),
                        'updated_at' => $product->updated_at?->toIso8601String(),
                        '_record_id' => $product->id,
                        '_resource_key' => '1.3.2',
                        '_form' => $this->formPayload('1.3.2', $product, [
                            'component_product_ids' => $items->pluck('component_product_id')->map(fn ($id) => (int) $id)->values()->all(),
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
        return ActivityLog::query()->with('actor.company:id,name')->latest('created_at')->limit(2000)->get()->values()->map(function (ActivityLog $log, int $index) use ($code): array {
            if ($code === '1.7.3') {
                return [
                    'id' => $index + 1,
                    'filter_form' => data_get($log->properties, 'filters', $log->subject_label ?: $log->actionLabel()),
                    'closing_status' => data_get($log->properties, 'closing_status'),
                    'delivery_status' => data_get($log->properties, 'delivery_status'),
                    'date_filter' => data_get($log->properties, 'date_type'),
                    'user' => $log->actor?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            }

            return [
                'ip_address' => $log->ip_address,
                'company' => data_get($log->properties, 'company', $log->actor?->company?->name ?? '—'),
                'account' => $log->actor?->email ?? $log->actor?->name,
                'access_code' => Str::limit((string) data_get($log->properties, 'access_code', $log->subject_label), 48),
                'browser' => Str::limit((string) $log->user_agent, 80),
                'created_at' => $log->created_at?->toIso8601String(),
                'status' => str_contains($log->action, 'fail') ? 'Không thành công' : 'Thành công',
            ];
        });
    }

    private function loginPermissions(): Collection
    {
        return User::query()->with('company:id,name')->latest('updated_at')->limit(1000)->get()->values()->map(fn (User $user) => [
            'company' => $user->company?->name ?? '—',
            'account' => $user->email,
            'access_code' => data_get($user->permissions, 'access_code'),
            'login_at' => $user->updated_at?->toIso8601String(),
            'status' => data_get($user->permissions, 'login_blocked', false) ? 'Đã khóa' : 'Được phép đăng nhập',
            'actions' => 'Cập nhật',
            '_edit_url' => "/admin/users/{$user->id}/edit",
        ]);
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

    private function leadIngestions(): Collection
    {
        return LeadIngestion::query()->latest('id')->limit(1000)->get()->values()->map(fn (LeadIngestion $lead, int $index) => [
            'index' => $index + 1,
            'customer_name' => $lead->customer_name,
            'customer_phone' => $lead->customer_phone,
            'message' => data_get($lead->payload, 'message', $lead->product_interest),
            'created_at' => $lead->created_at?->toIso8601String(),
            'status' => $lead->status?->value,
            'is_upsell' => $lead->isSupplementalPacket(),
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
        return $this->ordersGroupedBySale()->sortByDesc('revenue')->values()->map(function (array $row, int $index): array {
            return [
                'index' => $index + 1,
                'sale' => $row['name'],
                'new_contacts' => $row['new_contacts'],
                'new_closed' => $row['new_closed'],
                'new_rate' => round(($row['new_closed'] / max(1, $row['new_contacts'])) * 100, 2),
                'new_products' => $row['new_products'],
                'new_revenue' => $row['new_revenue'],
                'old_contacts' => $row['old_contacts'],
                'old_closed' => $row['old_closed'],
                'old_rate' => round(($row['old_closed'] / max(1, $row['old_contacts'])) * 100, 2),
                'old_products' => $row['old_products'],
                'old_revenue' => $row['old_revenue'],
                'provisional_revenue' => $row['provisional_revenue'],
                'discount' => $row['discount'],
                'cod_collected' => $row['cod_collected'],
                'cod_service_fee' => $row['cod_service_fee'],
                'revenue' => $row['revenue'],
                'total' => $row['revenue'],
            ];
        });
    }

    private function saleOperationRate(): Collection
    {
        $stages = ['call_1', 'call_2', 'call_3', 'call_4', 'call_5', 'call_6', 'care_1', 'care_2', 'care_3', 'skipped'];

        return $this->ordersGroupedBySale()->values()->map(function (array $row, int $index) use ($stages): array {
            $result = [
                'index' => $index + 1,
                'sale' => $row['name'],
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
        return WarehouseInventoryMovement::query()->with(['warehouse:id,name'])->latest('id')->limit(2000)->get()->groupBy(fn ($m) => $m->reference_type.'-'.$m->reference_id.'-'.$m->created_at?->format('YmdHi'))->values()->map(function (Collection $group): array {
            /** @var WarehouseInventoryMovement $first */
            $first = $group->first();
            return [
                'id' => $first->id,
                'warehouse' => $first->warehouse?->name,
                'type' => WarehouseInventoryMovement::typeLabel($first->type),
                'voucher_code' => $first->reference_id ? strtoupper((string) $first->reference_type).'-'.$first->reference_id : 'PXN-'.$first->id,
                'performed_at' => $first->created_at?->toIso8601String(),
                'total_quantity' => $group->sum('quantity'),
                'total_value' => null,
                'status' => 'Hoàn thành',
                'note' => $first->note,
                'internal_voucher' => '',
                'updated_at' => $first->updated_at?->toIso8601String(),
            ];
        });
    }

    private function movements(): Collection
    {
        return WarehouseInventoryMovement::query()->with(['warehouse:id,name', 'product:id,name,sku'])->latest('id')->limit(2500)->get()->values()->map(fn (WarehouseInventoryMovement $movement, int $index) => [
            'index' => $index + 1,
            'warehouse' => $movement->warehouse?->name,
            'product' => trim(($movement->product?->name ?? '—').' ('.($movement->product?->sku ?? '').')'),
            'type' => WarehouseInventoryMovement::typeLabel($movement->type),
            'quantity' => (int) $movement->quantity,
            'pending' => null,
            'reference' => $movement->reference_id ? "{$movement->reference_type} #{$movement->reference_id}" : '',
            'note' => $movement->note,
            'created_at' => $movement->created_at?->toIso8601String(),
        ]);
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

    private function monthlyPlan(string $code): Collection
    {
        $plans = MonthlyKpiPlan::query()->with('user:id,name,role')->latest('year')->latest('month')->limit(1000)->get();

        return $plans->values()->map(function (MonthlyKpiPlan $plan, int $index): array {
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
                'account' => $plan->user?->name,
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
                'base_salary' => $payroll['base_salary'],
                'bonus' => $bonus,
                'income' => $payroll['total'],
                'salary_basis' => $payroll['estimated'] ? 'Dự kiến đủ ngày công' : $payroll['payable_days'].'/'.$payroll['working_days'].' ngày công',
                'locked' => $plan->locked,
                'updated_at' => $plan->updated_at?->toIso8601String(),
                '_record_id' => $plan->id,
                '_form' => $this->formPayload('6.3.5', $plan),
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

    private function repurchase(): Collection
    {
        $counts = $this->recentOrders()->groupBy('customer_phone')->map->count();
        $buckets = [
            'purchase_1' => $counts->filter(fn ($v) => $v === 1)->count(),
            'purchase_2' => $counts->filter(fn ($v) => $v === 2)->count(),
            'purchase_3' => $counts->filter(fn ($v) => $v === 3)->count(),
            'purchase_n' => $counts->filter(fn ($v) => $v >= 4)->count(),
        ];
        return collect([
            ['metric' => 'Số khách hàng'] + $buckets,
            ['metric' => 'Tỷ lệ (%)', 'purchase_1' => round(($buckets['purchase_1'] / max(1, $counts->count())) * 100, 2), 'purchase_2' => round(($buckets['purchase_2'] / max(1, $counts->count())) * 100, 2), 'purchase_3' => round(($buckets['purchase_3'] / max(1, $counts->count())) * 100, 2), 'purchase_n' => round(($buckets['purchase_n'] / max(1, $counts->count())) * 100, 2)],
        ])->values()->map(fn ($row, $index) => ['index' => $index + 1] + $row);
    }

    private function repurchaseProducts(): Collection
    {
        $orders = $this->recentOrders()->loadMissing('items:id,order_id,quantity');
        $rows = [];
        foreach (range(1, 4) as $purchaseNo) {
            $row = ['purchase_no' => "Mua lần {$purchaseNo}"];
            foreach (range(1, 30) as $quantity) {
                $row["product_{$quantity}"] = $orders->filter(fn (Order $o) => $o->items->sum('quantity') === $quantity)->count();
            }
            $rows[] = $row;
        }
        return collect($rows);
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
        return OperationCategory::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (OperationCategory $row) => [
            'id' => $row->id,
            'name' => $row->name,
            'sort_order' => $row->sort_order,
            'is_start' => $row->is_start,
            'pool' => $row->is_pool,
            'duration' => $row->duration_minutes ? $row->duration_minutes.' phút' : '',
            'updated_at' => $row->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.8.1', $row),
        ])->values();
    }

    private function operationWorkflows(): Collection
    {
        return OperationWorkflow::query()->with(['fromCategory:id,name', 'toCategory:id,name'])->latest()->get()->map(fn (OperationWorkflow $row) => [
            'id' => $row->id,
            'condition' => trim(($row->fromCategory?->name ?? 'Mọi tác nghiệp')."\n".($row->condition_type ?? '')),
            'result' => $row->operation_result,
            'next_operation' => $row->toCategory?->name,
            'delay' => $row->delay_minutes.' phút',
            'updated_at' => $row->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.8.2', $row),
        ])->values();
    }

    private function discountCodRules(): Collection
    {
        return DiscountCodRule::query()->orderBy('order_from')->get()->values()->map(fn (DiscountCodRule $row, int $index) => [
            'index' => $index + 1,
            'order_from' => $row->order_from,
            'discount_value' => $row->discount_value,
            'calculation_type' => $row->calculation_type === 'percent' ? 'Phần trăm' : 'Số tiền',
            'updated_at' => $row->updated_at?->toIso8601String(),
            'actions' => 'Cập nhật',
            '_record_id' => $row->id,
            '_form' => $this->formPayload('1.9', $row),
        ]);
    }

    private function phoneBlacklists(): Collection
    {
        return PhoneBlacklist::query()->with(['order:id,order_code', 'creator:id,name'])->latest()->get()->map(fn (PhoneBlacklist $row) => [
            'id' => $row->id,
            'phone' => $row->phone,
            'reason' => $row->reason,
            'order_code' => $row->order?->order_code,
            'creation_type' => $row->creation_type === 'automatic' ? 'Tự động' : 'Thủ công',
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
        return WarehouseIncidentReport::query()->with('manager:id,name')->latest('document_date')->get()->map(fn (WarehouseIncidentReport $row) => [
            'id' => $row->id,
            'manager' => $row->manager?->name,
            'name' => $row->name,
            'document_date' => $row->document_date?->toDateString(),
            'carrier' => $row->carrier,
            'order_count' => $row->order_count,
            'product_count' => $row->product_count,
            'status' => match ($row->status) { 'confirmed' => 'Đã xác nhận', 'closed' => 'Đã đóng', default => 'Nháp' },
            'updated_at' => $row->updated_at?->toIso8601String(),
            '_record_id' => $row->id,
            '_form' => $this->formPayload('5.4', $row),
        ])->values();
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

    private function recentOrders(): Collection
    {
        $request = $this->currentRequest;
        $query = Order::query()
            ->with([
                'team:id,name,leader_user_id',
                'saleUser:id,name,team_id,permissions',
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
                'receive_data' => (bool) data_get($first->saleUser?->permissions, 'receive_data', true),
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
            'call_1', 'call1', 'gọi lần 1' => 'call_1',
            'call_2', 'call2', 'gọi lần 2' => 'call_2',
            'call_3', 'call3', 'gọi lần 3' => 'call_3',
            'call_4', 'call4', 'gọi lần 4' => 'call_4',
            'call_5', 'call5', 'gọi lần 5' => 'call_5',
            'call_6', 'call6', 'gọi lần 6' => 'call_6',
            'care_1', 'care1', 'chăm sóc lần 1' => 'care_1',
            'care_2', 'care2', 'chăm sóc lần 2' => 'care_2',
            'care_3', 'care3', 'chăm sóc lần 3' => 'care_3',
            'skipped', 'ignore', 'bỏ qua' => 'skipped',
            default => null,
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
            'operation_state' => '_operation_state',
            'operation_stage' => '_operation_stage',
            'operation_result' => '_operation_result',
            'customer_type' => '_customer_type',
            'allocation_status' => '_allocation_status',
            'shipping_method' => '_shipping_method',
            'internal_reconciliation_status' => '_internal_reconciliation_status',
            'duplicate_status' => '_duplicate_status',
            'care_operation_status' => '_care_operation_status',
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
            $from = $dateFrom !== '' ? CarbonImmutable::parse($dateFrom)->startOfDay() : null;
            $to = $dateTo !== '' ? CarbonImmutable::parse($dateTo)->endOfDay() : null;
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
