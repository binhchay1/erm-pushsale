<?php

namespace App\Http\Controllers\Api\V1\Pancake;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pancake\StorePancakeOrderRequest;
use App\Http\Traits\ApiResponds;
use App\Services\Pancake\PancakeOrderImportService;
use Illuminate\Http\JsonResponse;

class PancakeExtensionController extends Controller
{
    use ApiResponds;

    public function storeOrder(
        StorePancakeOrderRequest $request,
        PancakeOrderImportService $importer,
    ): JsonResponse {
        $result = $importer->import($request->validated(), $request->user());
        $order = $result['order'];
        $lead = $result['lead'];

        return $this->success([
            'lead' => [
                'id' => $lead->id,
                'status' => $lead->status->value,
                'customer_name' => $lead->customer_name,
                'customer_phone' => $lead->customer_phone,
            ],
            'order' => $order ? [
                'id' => $order->id,
                'order_code' => $order->order_code,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'sale_user' => $order->saleUser?->only(['id', 'name', 'email']),
                'total' => (int) $order->total,
                'url' => $order->sale_user_id ? '/sales/workspace' : '/admin/leads',
            ] : null,
            'sync_record_id' => $result['sync_record']->id,
        ], $order ? 'Đã tạo đơn từ Pancake.' : 'Đã tạo lead từ Pancake, đang chờ chia số.', 201);
    }
}
