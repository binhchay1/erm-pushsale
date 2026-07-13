<?php

namespace App\Models\Pushsale;

use App\Models\MarketingSource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerConnection extends BusinessRecord
{
    protected $fillable = [
        'name', 'partner_type', 'endpoint_url', 'access_token', 'marketing_source_id',
        'marketer_user_id', 'product_id', 'ad_channel', 'sale_priority', 'manual_import',
        'is_approved', 'is_active', 'metadata', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'manual_import' => 'boolean',
            'is_approved' => 'boolean',
            'is_active' => 'boolean',
            'access_token' => 'encrypted',
            'metadata' => 'array',
        ];
    }

    public function source(): BelongsTo { return $this->belongsTo(MarketingSource::class, 'marketing_source_id'); }
    public function marketer(): BelongsTo { return $this->belongsTo(User::class, 'marketer_user_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
