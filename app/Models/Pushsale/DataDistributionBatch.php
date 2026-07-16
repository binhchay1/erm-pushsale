<?php

namespace App\Models\Pushsale;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataDistributionBatch extends Model
{
    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'filters',
        'flags',
        'total_contacts',
        'allocated_contacts',
        'status',
        'line_stats',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'flags' => 'array',
            'line_stats' => 'array',
            'total_contacts' => 'integer',
            'allocated_contacts' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
