<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PancakeUserMapping extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'integration_connection_id',
        'shop_id',
        'page_id',
        'pancake_user_key',
        'pancake_user_id',
        'pancake_user_email',
        'pancake_user_name',
        'internal_user_id',
        'is_active',
        'last_seen_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    public function internalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'internal_user_id');
    }
}
