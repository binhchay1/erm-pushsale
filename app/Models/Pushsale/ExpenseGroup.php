<?php

namespace App\Models\Pushsale;

class ExpenseGroup extends BusinessRecord
{
    protected $table = 'expense_groups';

    protected $fillable = [
        'name',
        'created_by_user_id',
        'updated_by_user_id',
    ];
}
