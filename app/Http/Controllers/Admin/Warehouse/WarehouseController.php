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
        ];

        $query = Warehouse::query()->with('manager:id,name')->withCount('inventories');
        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('address', 'like', "%{$term}%"));
        }
        if ($filters['manager_user_id'] !== '') $query->where('manager_user_id', $filters['manager_user_id']);
        if ($filters['province'] !== '') $query->where('pick_province', $filters['province']);

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
            'products_count' => (int) $warehouse->inventories_count,
            'updated_at' => $warehouse->updated_at?->format('d/m/Y H:i'),
        ]);

        return Inertia::render('Admin/Warehouse/Index', [
            'warehouses' => $warehouses,
            'filters' => $filters,
            'managers' => $this->managerOptions(),
            'provinces' => Warehouse::query()->whereNotNull('pick_province')->where('pick_province', '!=', '')->distinct()->orderBy('pick_province')->pluck('pick_province')->values(),
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
            ],
            'activeMenuCode' => '5.2.1',
        ]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return redirect()->route('admin.warehouses.index')->with('success', __('messages.warehouse_updated'));
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
}
