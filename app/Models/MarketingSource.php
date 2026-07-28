<?php

namespace App\Models;

use App\Enums\CampaignLeadAllocation;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class MarketingSource extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::creating(function (MarketingSource $source): void {
            if ($source->webhook_token) {
                return;
            }

            do {
                $token = Str::random(40);
            } while (static::query()->withoutGlobalScopes()->where('webhook_token', $token)->exists());

            $source->webhook_token = $token;
        });
    }

    protected $fillable = [
        'company_id', 'parent_id', 'name', 'product_id', 'marketer_user_id', 'created_by_user_id', 'ad_channel',
        'utm_source', 'utm_campaign', 'webhook_token', 'budget', 'interactions', 'contacts',
        'is_active', 'is_approved', 'lead_allocation', 'js_tracking_enabled', 'approved_by_user_id', 'approved_at',
        'rejected_by_user_id', 'rejected_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
            'js_tracking_enabled' => 'boolean',
            'lead_allocation' => CampaignLeadAllocation::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class, 'parent_id');
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MarketingSource::class, 'parent_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function landingConnection(): HasOne
    {
        return $this->hasOne(LandingConnection::class);
    }

    /**
     * Nguồn được phép chọn khi nhập data thủ công:
     * - có landing connection và đã bật Nhập TC, hoặc
     * - nguồn legacy không gắn landing connection.
     */
    public function scopeEligibleForManualEntry($query)
    {
        return $query->where(function ($builder): void {
            $builder->whereHas('landingConnection', static function ($landing): void {
                $landing->where('manual_import', true);
            })->orWhereDoesntHave('landingConnection');
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(MarketingSourceDailyMetric::class);
    }
}
