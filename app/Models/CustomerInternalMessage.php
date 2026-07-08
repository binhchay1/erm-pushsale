<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInternalMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'order_id',
        'author_user_id',
        'author_name',
        'author_role',
        'customer_phone',
        'message',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
