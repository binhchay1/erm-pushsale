<?php

namespace App\Models\Pushsale;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOperationalProfile extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'employee_code',
        'base_salary',
        'receive_data',
        'work_shift_id',
        'is_locked',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'base_salary' => 'integer',
            'receive_data' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
