<?php

namespace App\Models;

use App\Enums\TeamType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['name', 'type', 'leader_user_id'];

    protected function casts(): array
    {
        return ['type' => TeamType::class];
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_user_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
