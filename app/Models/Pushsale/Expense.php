<?php

namespace App\Models\Pushsale;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends BusinessRecord
{
    protected $table = 'expenses';

    protected $fillable = [
        'name',
        'year',
        'month',
        'expense_group_id',
        'expense_category_id',
        'expense_unit_id',
        'unit_price',
        'quantity',
        'total',
        'invoice_number',
        'note',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'unit_price' => 'integer',
            'quantity' => 'decimal:2',
            'total' => 'integer',
        ];
    }

    public function group(): BelongsTo { return $this->belongsTo(ExpenseGroup::class, 'expense_group_id'); }
    public function category(): BelongsTo { return $this->belongsTo(ExpenseCategory::class, 'expense_category_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(ExpenseUnit::class, 'expense_unit_id'); }
}
