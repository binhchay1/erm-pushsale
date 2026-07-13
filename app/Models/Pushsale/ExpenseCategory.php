<?php

namespace App\Models\Pushsale;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseCategory extends BusinessRecord
{
    protected $table = 'expense_categories';

    protected $fillable = [
        'expense_group_id',
        'name',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function group(): BelongsTo { return $this->belongsTo(ExpenseGroup::class, 'expense_group_id'); }
}
