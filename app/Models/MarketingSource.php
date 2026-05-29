<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingSource extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'product_id', 'marketer_user_id', 'ad_channel',
        'utm_source', 'utm_campaign', 'budget', 'interactions', 'contacts', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
