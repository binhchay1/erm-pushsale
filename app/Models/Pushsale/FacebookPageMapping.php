<?php

namespace App\Models\Pushsale;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookPageMapping extends BusinessRecord
{
    protected $fillable = [
        'page_id', 'page_name', 'creator_name', 'marketer_user_id', 'is_active', 'metadata',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_user_id');
    }
}
