<?php

namespace App\Models\Pushsale;

class ExpenseUnit extends BusinessRecord
{
    protected $table = 'expense_units';

    protected $fillable = [
        'name',
        'created_by_user_id',
        'updated_by_user_id',
    ];
}
