<?php

namespace App\Http\Resources\V1;

use App\Models\LeadIngestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeadIngestion */
class LeadIngestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'external_id' => $this->external_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'product_interest' => $this->product_interest,
            'utm_source' => $this->utm_source,
            'utm_campaign' => $this->utm_campaign,
            'order_id' => $this->order_id,
            'error_message' => $this->error_message,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'order' => $this->whenLoaded('order', fn () => new OrderResource($this->order)),
        ];
    }
}
