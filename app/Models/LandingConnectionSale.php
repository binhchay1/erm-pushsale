<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingConnectionSale extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'landing_connection_id', 'user_id', 'priority', 'weight', 'is_active',
    ];

    protected function casts(): array
    {
        return ['priority' => 'integer', 'weight' => 'integer', 'is_active' => 'boolean'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(LandingConnection::class, 'landing_connection_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
