<?php

namespace App\Http\Resources\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_code' => $this->order_code,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'customer_note' => $this->customer_note,
            'operation_stage' => $this->operation_stage,
            'delivery_status' => $this->delivery_status,
            'total' => $this->total,
            'data_arrived_at' => $this->data_arrived_at?->toIso8601String(),
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'sale_user' => $this->whenLoaded('saleUser', fn () => new UserResource($this->saleUser)),
            'marketing_source' => $this->whenLoaded('marketingSource', fn () => [
                'id' => $this->marketingSource->id,
                'name' => $this->marketingSource->name,
            ]),
        ];
    }
}
