<?php

namespace App\Services\Operations;

use App\Models\FailedPartnerOrder;
use Illuminate\Http\Request;

class FailedOrderService
{
    /** @return array<string, mixed> */
    public function build(Request $request): array
    {
        $query = FailedPartnerOrder::query()->with('warehouse');

        if ($request->filled('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('partner_order_id')) {
            $query->where('partner_order_id', 'like', '%'.$request->input('partner_order_id').'%');
        }

        $rows = $query->latest()->paginate(20)->withQueryString();

        return [
            'rows' => $rows->through(fn (FailedPartnerOrder $row) => [
                'stt' => $row->id,
                'partnerOrderId' => $row->partner_order_id,
                'errorDescription' => $row->error_description,
                'platform' => $row->platform,
                'warehouseName' => $row->warehouse?->name,
                'shopName' => $row->shop_name,
                'updatedAt' => $row->updated_at?->toIso8601String(),
            ]),
            'filters' => [
                'platform' => $request->input('platform'),
                'warehouse_id' => $request->input('warehouse_id'),
                'partner_order_id' => $request->input('partner_order_id'),
            ],
            'platforms' => ['TikTok', 'Facebook', 'Shopee', 'Lazada'],
        ];
    }
}
