<?php

namespace App\Http\Controllers\Admin\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\WarehouseInventoryMovement;
use App\Repositories\ProductRepository;
use App\Repositories\WarehouseMovementRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lịch sử nhập xuất kho — chỉ admin xem được (route nằm trong nhóm admin).
 */
class MovementHistoryController extends Controller
{
    public function __invoke(
        Request $request,
        WarehouseMovementRepository $movements,
        WarehouseRepository $warehouses,
        ProductRepository $products,
    ): Response {
        $filters = [
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
            'product_id' => $request->integer('product_id') ?: null,
            'type' => $request->input('type') ?: null,
            'date_from' => $request->input('date_from') ?: null,
            'date_to' => $request->input('date_to') ?: null,
        ];

        $rows = $movements->paginatedHistory($filters)->through(fn (WarehouseInventoryMovement $m) => [
            'id' => (string) $m->id,
            'createdAt' => $m->created_at?->format('d/m/Y H:i'),
            'type' => $m->type,
            'typeLabel' => WarehouseInventoryMovement::typeLabel($m->type),
            'warehouseName' => $m->warehouse?->name,
            'productName' => $m->product?->name,
            'sku' => $m->product?->sku,
            'quantity' => $m->quantity,
            'stockAfter' => $m->stock_after,
            'userName' => $m->user?->name ?? '—',
            'approverName' => $m->approver?->name,
            'referenceType' => $m->reference_type,
            'referenceId' => $m->reference_id,
            'note' => $m->note,
        ]);

        return Inertia::render('Admin/Warehouse/MovementHistory', [
            'rows' => $rows,
            'filters' => $filters,
            'warehouses' => $warehouses->options(),
            'products' => $products->options(),
            'types' => collect([
                WarehouseInventoryMovement::TYPE_INTAKE,
                WarehouseInventoryMovement::TYPE_EXPORT,
                WarehouseInventoryMovement::TYPE_DEDUCTION,
                WarehouseInventoryMovement::TYPE_RETURN,
            ])->map(fn (string $t) => [
                'value' => $t,
                'label' => WarehouseInventoryMovement::typeLabel($t),
            ])->values()->all(),
        ]);
    }
}
