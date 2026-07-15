<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingConnectionProduct extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'landing_connection_id', 'landing_connection_source_id', 'product_id',
        'item_type', 'external_field', 'external_value', 'quantity', 'unit_price_override',
        'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_override' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(LandingConnection::class, 'landing_connection_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LandingConnectionSource::class, 'landing_connection_source_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
