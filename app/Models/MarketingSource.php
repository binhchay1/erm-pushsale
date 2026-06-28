<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingSource extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'parent_id', 'name', 'product_id', 'marketer_user_id', 'created_by_user_id', 'ad_channel',
        'utm_source', 'utm_campaign', 'webhook_token', 'budget', 'interactions', 'contacts',
        'is_active', 'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
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

    public function children(): HasMany
    {
        return $this->hasMany(MarketingSource::class, 'parent_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
