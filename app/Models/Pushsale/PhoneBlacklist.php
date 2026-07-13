<?php

namespace App\Models\Pushsale;

use App\Models\Order;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneBlacklist extends BusinessRecord
{
    protected $table = 'phone_blacklists';

    protected $fillable = [
        'phone',
        'reason',
        'order_id',
        'creation_type',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
