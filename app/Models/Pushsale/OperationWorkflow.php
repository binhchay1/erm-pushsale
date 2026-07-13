<?php

namespace App\Models\Pushsale;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationWorkflow extends BusinessRecord
{
    protected $table = 'operation_workflows';

    protected $fillable = [
        'from_operation_category_id',
        'condition_type',
        'operation_result',
        'to_operation_category_id',
        'delay_minutes',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'delay_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function fromCategory(): BelongsTo { return $this->belongsTo(OperationCategory::class, 'from_operation_category_id'); }
    public function toCategory(): BelongsTo { return $this->belongsTo(OperationCategory::class, 'to_operation_category_id'); }
}
