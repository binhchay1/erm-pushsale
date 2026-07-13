<?php

namespace App\Models\Pushsale;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyKpiPlan extends BusinessRecord
{
    protected $table = 'monthly_kpi_plans';

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'kpi_name',
        'budget',
        'clicks_target',
        'contacts_target',
        'revenue_target',
        'new_contacts_target',
        'new_closed_target',
        'old_contacts_target',
        'old_closed_target',
        'bonus_percent',
        'base_salary',
        'working_days',
        'actual_days',
        'locked',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'budget' => 'integer',
            'clicks_target' => 'integer',
            'contacts_target' => 'integer',
            'revenue_target' => 'integer',
            'new_contacts_target' => 'integer',
            'new_closed_target' => 'integer',
            'old_contacts_target' => 'integer',
            'old_closed_target' => 'integer',
            'bonus_percent' => 'decimal:2',
            'base_salary' => 'integer',
            'working_days' => 'integer',
            'actual_days' => 'integer',
            'locked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
