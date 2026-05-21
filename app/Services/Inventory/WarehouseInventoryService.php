<?php

namespace App\Services\Inventory;

use App\Models\WarehouseInventory;
use Illuminate\Http\Request;

class WarehouseInventoryService
{
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
            $query->whereHas('product', fn ($q) => $q->where('name', 'like', $term));
        }

        $rows = $query->latest()->paginate(20)->withQueryString();

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
            ],
        ];
    }
}
