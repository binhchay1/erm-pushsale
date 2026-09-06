<?php

namespace App\Models;

use App\Casts\LegacyLeadPacketType;
use App\Enums\LeadIngestionStatus;
use App\Models\Concerns\BelongsToShop;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadIngestion extends Model
{
    use BelongsToShop, BelongsToTenant;

    protected $fillable = [
        'shop_id',
        'platform',
        'external_id',
        'status',
        'packet_type',
        'counts_as_lead',
        'customer_name',
        'customer_phone',
        'product_interest',
        'utm_source',
        'utm_campaign',
        'marketing_source_id', 'landing_connection_id', 'landing_connection_source_id',
        'payload',
        'order_id',
        'parent_ingestion_id',
        'related_order_id',
        'requires_review',
        'phone_lock_conflict',
        'phone_lock_owner_user_id',
        'reviewed_at',
        'reviewed_by_user_id',
        'review_resolution',
        'review_note',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'requires_review' => 'boolean',
            'phone_lock_conflict' => 'boolean',
            'counts_as_lead' => 'boolean',
            'status' => LeadIngestionStatus::class,
            'packet_type' => LegacyLeadPacketType::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }


    public function phoneLockOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'phone_lock_owner_user_id');
    }

    public function parentIngestion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_ingestion_id');
    }

    public function childPackets(): HasMany
    {
        return $this->hasMany(self::class, 'parent_ingestion_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function landingConnection(): BelongsTo
    {
        return $this->belongsTo(LandingConnection::class)->withTrashed();
    }

    public function landingConnectionSource(): BelongsTo
    {
        return $this->belongsTo(LandingConnectionSource::class)->withTrashed();
    }

    public function marketingSource(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class);
    }

    public function effectiveOrder(): ?Order
    {
        return $this->order ?? $this->relatedOrder;
    }

    public function isSupplementalPacket(): bool
    {
        return $this->packet_type?->isSupplemental() ?? ! $this->counts_as_lead;
    }

    /** @param Builder<LeadIngestion> $query */
    public function scopeCountableLead(Builder $query): Builder
    {
        return $query->where('counts_as_lead', true);
    }

    /** @param Builder<LeadIngestion> $query */
    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('requires_review', true)->whereNull('reviewed_at');
    }
}
