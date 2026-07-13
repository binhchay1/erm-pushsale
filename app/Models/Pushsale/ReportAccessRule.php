<?php

namespace App\Models\Pushsale;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAccessRule extends BusinessRecord
{
    protected $table = 'report_access_rules';

    protected $fillable = [
        'user_id',
        'team_ids',
        'team_type',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'team_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
