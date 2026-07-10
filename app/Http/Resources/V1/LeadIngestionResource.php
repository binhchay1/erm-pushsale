<?php

namespace App\Http\Resources\V1;

use App\Enums\LeadPacketType;
use App\Models\LeadIngestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeadIngestion */
class LeadIngestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $packetType = $this->packet_type ?? LeadPacketType::Lead;

        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'external_id' => $this->external_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'packet_type' => $packetType->value,
            'packet_type_label' => $packetType->label(),
            'counts_as_lead' => (bool) $this->counts_as_lead,
            'is_supplemental' => $this->isSupplementalPacket(),
            'requires_review' => (bool) $this->requires_review,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'product_interest' => $this->product_interest,
            'utm_source' => $this->utm_source,
            'utm_campaign' => $this->utm_campaign,
            'order_id' => $this->order_id,
            'related_order_id' => $this->related_order_id,
            'parent_ingestion_id' => $this->parent_ingestion_id,
            'error_message' => $this->error_message,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'order' => $this->whenLoaded('order', fn () => $this->order ? new OrderResource($this->order) : null),
            'related_order' => $this->whenLoaded('relatedOrder', fn () => $this->relatedOrder ? new OrderResource($this->relatedOrder) : null),
            'parent_ingestion' => $this->whenLoaded('parentIngestion', fn () => $this->parentIngestion ? [
                'id' => $this->parentIngestion->id,
                'external_id' => $this->parentIngestion->external_id,
                'order_id' => $this->parentIngestion->order_id,
            ] : null),
        ];
    }
}
