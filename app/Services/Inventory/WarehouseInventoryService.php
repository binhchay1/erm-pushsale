<?php

namespace App\Services\Inventory;

use App\Models\WarehouseInventory;
use Illuminate\Http\Request;

class WarehouseInventoryService
{
    public function __construct(
        private readonly InventoryIntakeService $intakeService,
    ) {}

    /** @return array<string, mixed> */
    public function build(Request $request): array
    {
        $query = WarehouseInventory::query()->with(['warehouse', 'product']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->input('search').'%';
            $query->whereHas('product', fn ($q) => $q
                ->where('name', 'like', $term)
                ->orWhere('sku', 'like', $term));
        }

        if ($request->filled('location_code')) {
            $query->where('location_code', 'like', '%'.$request->input('location_code').'%');
        }

        if ($request->filled('batch_code')) {
            $query->where('batch_code', 'like', '%'.$request->input('batch_code').'%');
        }

        if ($request->input('business_status') === 'active') {
            $query->where('is_discontinued', false);
        } elseif ($request->input('business_status') === 'stopped') {
            $query->where('is_discontinued', true);
        }

        $perPage = max(10, min(100, $request->integer('per_page', 20)));
        $rows = $query->latest()->paginate($perPage)->withQueryString();

        return [
            'rows' => $rows->through(fn (WarehouseInventory $inv) => [
                'id' => (string) $inv->id,
                'warehouseName' => $inv->warehouse->name,
                'productName' => $inv->product->name,
                'sku' => $inv->product->sku,
                'uom' => $inv->uom,
                'batchCode' => $inv->batch_code,
                'expiryDate' => $inv->expiry_date?->toDateString(),
                'locationCode' => $inv->location_code,
                'stockQuantity' => $inv->stock_quantity,
                'pendingSalesQuantity' => $inv->pending_sales_quantity,
                'isDiscontinued' => $inv->is_discontinued,
                'businessStatus' => $inv->business_status,
            ]),
            'filters' => [
                'warehouse_id' => $request->input('warehouse_id'),
                'product_id' => $request->input('product_id'),
                'search' => $request->input('search'),
                'location_code' => $request->input('location_code'),
                'batch_code' => $request->input('batch_code'),
                'business_status' => $request->input('business_status'),
                'per_page' => $perPage,
            ],
            'recentIntakes' => $this->intakeService->recentMovements(),
        ];
    }
}
