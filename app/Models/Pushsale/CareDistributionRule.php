<?php

namespace App\Models\Pushsale;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareDistributionRule extends BusinessRecord
{
    protected $table = 'care_distribution_rules';

    protected $fillable = [
        'care_user_id',
        'quota',
        'receive_data',
        'sale_team_ids',
        'warehouse_team_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_team_ids' => 'array',
            'receive_data' => 'boolean',
            'quota' => 'integer',
        ];
    }

    public function careUser(): BelongsTo { return $this->belongsTo(User::class, 'care_user_id'); }
    public function warehouseTeam(): BelongsTo { return $this->belongsTo(Team::class, 'warehouse_team_id'); }
}
