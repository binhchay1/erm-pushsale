<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WarehouseRequest;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use App\Support\VietnamDivisions;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseRepository $warehouses,
        private readonly UserRepository $users,
        private readonly OrderRepository $orderStats,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'manager_user_id' => (string) $request->query('manager_user_id', ''),
            'province' => (string) $request->query('province', ''),
            'district' => (string) $request->query('district', ''),
        ];

        $query = Warehouse::query()->with('manager:id,name')->withCount('inventories');
        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('address', 'like', "%{$term}%"));
        }
        if ($filters['manager_user_id'] !== '') $query->where('manager_user_id', $filters['manager_user_id']);
        if ($filters['province'] !== '') {
            $province = $filters['province'];
            $query->where(fn ($builder) => $builder
                ->where('pick_province', $province)
                ->orWhere('pick_province', 'like', "%{$province}%"));
        }
        if ($filters['district'] !== '') {
            $district = $filters['district'];
            $query->where(fn ($builder) => $builder
                ->where('pick_district', $district)
                ->orWhere('pick_ward', $district)
                ->orWhere('pick_district', 'like', "%{$district}%")
                ->orWhere('pick_ward', 'like', "%{$district}%"));
        }

        $warehouses = $query->orderBy('name')->paginate(20)->withQueryString()->through(fn (Warehouse $warehouse): array => [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'phone' => $warehouse->phone,
            'pick_province' => $warehouse->pick_province,
            'pick_district' => $warehouse->pick_district,
            'pick_ward' => $warehouse->pick_ward,
            'address' => $warehouse->address,
            'manager_user_id' => $warehouse->manager_user_id,
            'manager_name' => $warehouse->manager?->name,
            'vtp_code' => $warehouse->vtp_code,
            'ghtk_pick_address_id' => $warehouse->ghtk_pick_address_id,
            'code' => $warehouse->code,
            'sort_order' => $warehouse->sort_order,
            'use_two_level_address' => (bool) $warehouse->use_two_level_address,
            'sender_registration_name' => $warehouse->sender_registration_name,
            'sender_print_note' => $warehouse->sender_print_note,
            'default_delivery_provinces' => $warehouse->default_delivery_provinces,
            'default_shipping_provider' => $warehouse->default_shipping_provider,
            'default_shipping_service' => $warehouse->default_shipping_service,
            'shipping_account_settings' => $warehouse->shipping_account_settings ?? [],
            'products_count' => (int) $warehouse->inventories_count,
            'updated_at' => $warehouse->updated_at?->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Warehouse/Index', [
            'warehouses' => $warehouses,
            'filters' => $filters,
            'managers' => $this->managerOptions(),
            'provinces' => $this->provinceOptions(),
            'districts' => $this->districtOptions($filters['province']),
            'locations' => $this->locationOptions(),
            'shippingProviders' => $this->shippingProviderOptions(),
            'activeMenuCode' => '5.2.1',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Warehouse/Form', [
            'managers' => $this->managerOptions(),
            'warehouse' => null,
            'activeMenuCode' => '5.2.1',
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        Warehouse::query()->create($request->validated());

        return redirect()->route('admin.warehouses.index')->with('success', __('messages.warehouse_created'));
    }

    public function show(Warehouse $warehouse, Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = $this->warehouses->inventoriesOf($warehouse->id, $search)
            ->map(fn (WarehouseInventory $i) => [
                'id' => $i->id,
                'product_name' => $i->product?->name,
                'sku' => $i->product?->sku,
                'location_code' => $i->location_code,
                'batch_code' => $i->batch_code,
                'stock_quantity' => $i->stock_quantity,
                'pending_sales_quantity' => $i->pending_sales_quantity,
                'is_discontinued' => $i->is_discontinued,
            ])
            ->values();

        return Inertia::render('Admin/Warehouse/Show', [
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'phone' => $warehouse->phone,
                'address' => $warehouse->address,
                'vtp_code' => $warehouse->vtp_code,
                'manager_name' => $warehouse->manager?->name,
            ],
            'filters' => [
                'search' => $search,
            ],
            'rows' => $rows,
            'activeMenuCode' => '5.2.1',
        ]);
    }

    public function edit(Warehouse $warehouse): Response
    {
        return Inertia::render('Admin/Warehouse/Form', [
            'managers' => $this->managerOptions(),
            'warehouse' => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'phone' => $warehouse->phone,
                'address' => $warehouse->address,
                'pick_province' => $warehouse->pick_province,
                'pick_district' => $warehouse->pick_district,
                'pick_ward' => $warehouse->pick_ward,
                'code' => $warehouse->code,
                'ghtk_pick_address_id' => $warehouse->ghtk_pick_address_id,
                'manager_user_id' => $warehouse->manager_user_id,
                'vtp_code' => $warehouse->vtp_code,
                'sort_order' => $warehouse->sort_order,
                'use_two_level_address' => (bool) $warehouse->use_two_level_address,
                'sender_registration_name' => $warehouse->sender_registration_name,
                'sender_print_note' => $warehouse->sender_print_note,
                'default_delivery_provinces' => $warehouse->default_delivery_provinces,
            ],
            'activeMenuCode' => '5.2.1',
        ]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return redirect()->route('admin.warehouses.index')->with('success', __('messages.warehouse_updated'));
    }

    public function updateShippingAccount(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $providers = array_keys((array) config('shipping_partners.providers', []));

        $validated = $request->validate([
            'default_shipping_provider' => ['nullable', 'string', Rule::in($providers)],
            'default_shipping_service' => ['nullable', 'string', 'max:80'],
            'shipping_account_settings' => ['nullable', 'array'],
        ]);

        $settings = [];
        foreach ((array) ($validated['shipping_account_settings'] ?? []) as $provider => $payload) {
            if (! in_array($provider, $providers, true) || ! is_array($payload)) {
                continue;
            }

            $settings[$provider] = Arr::only($payload, [
                'account',
                'api_token',
                'shop_id',
                'customer_code',
                'store_code',
                'pickup_time',
                'pickup_method',
                'order_label_note',
                'fixed_receiver_phone',
            ]);
        }

        $warehouse->update([
            'default_shipping_provider' => $validated['default_shipping_provider'] ?? null,
            'default_shipping_service' => $validated['default_shipping_service'] ?? null,
            'shipping_account_settings' => $settings,
        ]);

        return back()->with('success', 'Đã lưu cấu hình tài khoản giao hàng cho kho.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($this->orderStats->existsForWarehouse($warehouse->id)) {
            return back()->with('error', __('messages.warehouse_has_orders'));
        }

        $this->warehouses->deleteInventoriesOfWarehouse($warehouse->id);
        $warehouse->delete();

        return back()->with('success', __('messages.warehouse_deleted'));
    }

    /** @return list<array{id:int,name:string}> */
    protected function managerOptions(): array
    {
        return $this->users->nameOptionsByRoles([UserRole::Admin, UserRole::Warehouse]);
    }

    /** @return list<string> */
    protected function provinceOptions(): array
    {
        $locations = $this->locationOptions();
        $known = array_map(fn (array $item): string => (string) $item['name'], $locations['old']['provinces']);
        $current = array_map(fn (array $item): string => (string) $item['name'], $locations['new2025']['provinces']);

        $fromData = Warehouse::query()
            ->whereNotNull('pick_province')
            ->where('pick_province', '!=', '')
            ->distinct()
            ->orderBy('pick_province')
            ->pluck('pick_province')
            ->all();

        return array_values(array_unique(array_filter(array_merge([
            'Địa chỉ 2 cấp 2025',
        ], $current, $known, $fromData))));
    }

    /** @return list<string> */
    protected function districtOptions(string $province = ''): array
    {
        $locations = $this->locationOptions();
        $fromAddressBook = $province !== ''
            ? array_map(fn (array $item): string => (string) $item['name'], $locations['old']['districts'][$province] ?? [])
            : [];
        $from2025Wards = $province !== ''
            ? array_map(fn (array $item): string => (string) $item['name'], $locations['new2025']['wards'][$province] ?? [])
            : [];

        $query = Warehouse::query()->whereNotNull('pick_district')->where('pick_district', '!=', '');
        if ($province !== '') {
            $query->where('pick_province', $province);
        }

        $fromData = $query->distinct()->orderBy('pick_district')->pluck('pick_district')->all();

        return array_values(array_unique(array_filter(array_merge($fromAddressBook, $from2025Wards, $fromData))));
    }

    /** @return array<string,mixed> */
    protected function locationOptions(): array
    {
        $oldProvinces = [];
        $oldDistricts = [];
        $oldWards = [];

        foreach (VietnamDivisions::provinces() as $province) {
            $provinceName = $this->cleanDivisionName((string) $province['name']);
            $oldProvinces[] = [
                'code' => (string) $province['code'],
                'name' => $provinceName,
                'value' => $provinceName,
                'label' => $provinceName,
                'mode' => 'old',
            ];

            foreach (VietnamDivisions::districts((string) $province['code']) as $district) {
                $districtName = $this->cleanDistrictName((string) $district['name']);
                $oldDistricts[$provinceName][] = [
                    'code' => (string) $district['code'],
                    'name' => $districtName,
                    'value' => $districtName,
                    'label' => $districtName,
                    'mode' => 'old',
                ];

                $wardKey = $provinceName.'||'.$districtName;
                foreach (VietnamDivisions::wards((string) $district['code']) as $ward) {
                    $wardName = (string) $ward['name'];
                    $oldWards[$wardKey][] = [
                        'code' => (string) $ward['code'],
                        'name' => $wardName,
                        'value' => $wardName,
                        'label' => $wardName,
                        'mode' => 'old',
                    ];
                }
            }
        }

        $newProvinces = [];
        $newWards = [];
        foreach (VietnamDivisions::newProvinces() as $province) {
            $provinceName = $this->cleanDivisionName((string) $province['name']);
            $newProvinces[] = [
                'code' => (string) $province['code'],
                'name' => $provinceName,
                'value' => $provinceName,
                'label' => $provinceName,
                'mode' => 'new2025',
            ];

            foreach (VietnamDivisions::newWards((string) $province['code']) as $ward) {
                $wardName = (string) $ward['name'];
                $newWards[$provinceName][] = [
                    'code' => (string) $ward['code'],
                    'name' => $wardName,
                    'value' => $wardName,
                    'label' => $wardName,
                    'mode' => 'new2025',
                ];
            }
        }

        return [
            'old' => [
                'provinces' => $oldProvinces,
                'districts' => $oldDistricts,
                'wards' => $oldWards,
            ],
            'new2025' => [
                'provinces' => $newProvinces,
                'wards' => $newWards,
            ],
        ];
    }

    protected function cleanDivisionName(string $name): string
    {
        return trim(preg_replace('/^(Tỉnh|Thành phố)\s+/u', '', $name) ?: $name);
    }

    protected function cleanDistrictName(string $name): string
    {
        return trim($name);
    }

    /** @return list<array<string,mixed>> */
    protected function shippingProviderOptions(): array
    {
        return collect(config('shipping_partners.providers', []))
            ->map(fn (array $provider, string $key): array => [
                'key' => $key,
                'label' => (string) ($provider['label'] ?? $key),
                'description' => (string) ($provider['description'] ?? ''),
                'integration_mode' => (string) ($provider['integration_mode'] ?? 'direct'),
                'services' => collect($provider['services'] ?? [])
                    ->map(fn (array $service): array => [
                        'code' => (string) ($service['code'] ?? ''),
                        'label' => (string) ($service['label'] ?? ($service['code'] ?? '')),
                    ])
                    ->filter(fn (array $service): bool => $service['code'] !== '')
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
