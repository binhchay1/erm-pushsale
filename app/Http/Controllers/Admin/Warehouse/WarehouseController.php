<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WarehouseRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(): Response
    {
        $warehouses = Warehouse::query()
            ->withCount('inventories')
            ->with('manager:id,name')
            ->latest('id')
            ->get()
            ->map(fn (Warehouse $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'phone' => $w->phone,
                'address' => $w->address,
                'manager_name' => $w->manager?->name,
                'vtp_code' => $w->vtp_code,
                'products_count' => $w->inventories_count,
            ])
            ->values();

        return Inertia::render('Admin/Warehouse/Index', [
            'warehouses' => $warehouses,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Warehouse/Form', [
            'managers' => $this->managerOptions(),
            'warehouse' => null,
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        Warehouse::query()->create($request->validated());

        return redirect()->route('admin.warehouses.index')->with('success', 'Đã tạo kho mới.');
    }

    public function show(Warehouse $warehouse, Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $rows = WarehouseInventory::query()
            ->where('warehouse_id', $warehouse->id)
            ->with('product:id,name,sku')
            ->when($search !== '', fn ($q) => $q->whereHas('product', function ($qq) use ($search) {
                $term = '%'.$search.'%';
                $qq->where('name', 'like', $term)->orWhere('sku', 'like', $term);
            }))
            ->orderByDesc('id')
            ->get()
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
                'manager_user_id' => $warehouse->manager_user_id,
                'vtp_code' => $warehouse->vtp_code,
            ],
        ]);
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($request->validated());

        return redirect()->route('admin.warehouses.index')->with('success', 'Đã cập nhật kho.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $warehouse->delete();

        return back()->with('success', 'Đã xóa kho.');
    }

    /** @return list<array{id:int,name:string}> */
    protected function managerOptions(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::Warehouse])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }
}
