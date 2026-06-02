<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'team_id', 'manager_user_id', 'is_team_leader'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SALES = 'sales';

    public const ROLE_MARKETING = 'marketing';

    public const ROLE_WAREHOUSE = 'warehouse';

    public const ROLE_ALLOCATOR = 'allocator';

    public const ROLE_ACCOUNTING = 'accounting';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_team_leader' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSales(): bool
    {
        return $this->role === UserRole::Sales;
    }

    public function roleLabel(): string
    {
        return $this->role->label();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(self::class, 'manager_user_id');
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class)->latest('id');
    }

    public function ensurePreferences(): UserPreference
    {
        return $this->preferences()->firstOrCreate(
            ['user_id' => $this->id],
            [
                'theme' => UserPreference::THEME_DEFAULT,
                'appearance' => UserPreference::APPEARANCE_SYSTEM,
                'notifications' => UserPreference::defaultNotifications(),
            ]
        );
    }
}
