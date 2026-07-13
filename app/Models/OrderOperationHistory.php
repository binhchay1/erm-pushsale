<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderOperationHistory extends Model
{
    use BelongsToTenant;

    public const ACTION_INITIAL_SNAPSHOT = 'initial_snapshot';

    public const ACTION_CALL = 'call';

    public const ACTION_STATUS_UPDATED = 'status_updated';

    public const ACTION_ORDER_UPDATED = 'order_updated';

    public const ACTION_ORDER_CLOSED = 'order_closed';

    public const ACTION_NOTE_UPDATED = 'note_updated';

    public const ACTION_DESIRED_DELIVERY_UPDATED = 'desired_delivery_updated';

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'order_id',
        'actor_user_id',
        'actor_name',
        'actor_role',
        'action',
        'operation_stage_before',
        'operation_stage_after',
        'operation_result',
        'next_operation_at',
        'note',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'next_operation_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
