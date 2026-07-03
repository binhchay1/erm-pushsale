<?php

namespace App\Models;

use App\Enums\OrgLevel;
use App\Enums\PermissionArea;
use App\Enums\PermissionLevel;
use App\Enums\UserRole;
use App\Models\Concerns\BelongsToTenant;
use App\Support\PermissionCatalog;
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

#[Fillable(['company_id', 'name', 'email', 'password', 'role', 'is_owner', 'is_platform_admin', 'avatar_path', 'phone', 'job_title', 'team_id', 'manager_user_id', 'created_by_user_id', 'is_team_leader', 'org_level', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

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
            'is_owner' => 'boolean',
            'is_platform_admin' => 'boolean',
            'org_level' => OrgLevel::class,
            'permissions' => 'array',
        ];
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return asset('storage/'.$this->avatar_path);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) >= 2) {
            return mb_strtoupper(
                mb_substr($parts[0], 0, 1).mb_substr($parts[count($parts) - 1], 0, 1)
            );
        }

        return mb_strtoupper(mb_substr($this->name, 0, 2));
    }

    public function orgLevelLabel(): ?string
    {
        if ($this->org_level instanceof OrgLevel) {
            return $this->org_level->label();
        }

        if ($this->is_team_leader) {
            return OrgLevel::Head->label();
        }

        return null;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSales(): bool
    {
        return $this->role === UserRole::Sales;
    }

    public function isOwner(): bool
    {
        return (bool) $this->is_owner;
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /**
     * Super admin = chủ project: xem full dữ liệu công ty nội bộ + quản trị nền tảng.
     * Chỉ KHÔNG thấy dữ liệu của các doanh nghiệp (tenant) do nó tạo — TenantScope tự chặn.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /** Quản trị nền tảng (/platform/*): super admin hoặc admin công ty nội bộ. */
    public function canManagePlatform(): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        if (! $this->isAdmin()) {
            return false;
        }

        return $this->belongsToInternalCompany();
    }

    /**
     * Bản đồ quyền hiệu lực: mặc định theo vai trò, ghi đè bởi cột permissions.
     *
     * @return array<string, string>
     */
    public function permissionsMap(): array
    {
        // Admin công ty = toàn quyền mọi khu vực.
        if ($this->isAdmin()) {
            return PermissionCatalog::allFull();
        }

        $defaults = $this->role instanceof UserRole
            ? PermissionCatalog::defaultsForRole($this->role)
            : [];

        $overrides = is_array($this->permissions) ? $this->permissions : [];

        return array_merge($defaults, array_filter(
            $overrides,
            fn ($level) => in_array($level, ['none', 'view', 'full'], true),
        ));
    }

    public function permissionLevel(PermissionArea|string $area): PermissionLevel
    {
        $key = $area instanceof PermissionArea ? $area->value : $area;

        return PermissionLevel::fromNullable($this->permissionsMap()[$key] ?? null);
    }

    /** Có đủ quyền tối thiểu ở khu vực không (admin luôn true). */
    public function allows(PermissionArea|string $area, PermissionLevel|string $level = PermissionLevel::View): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $required = $level instanceof PermissionLevel ? $level : PermissionLevel::fromNullable($level);

        return $this->permissionLevel($area)->allows($required);
    }

    public function belongsToInternalCompany(): bool
    {
        if (! $this->company_id) {
            return false;
        }

        $company = $this->relationLoaded('company')
            ? $this->company
            : $this->company()->first(['id', 'slug']);

        return $company?->isInternal() ?? false;
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by_user_id');
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
                'locale' => 'vi',
                'theme' => UserPreference::THEME_DEFAULT,
                'appearance' => UserPreference::APPEARANCE_SYSTEM,
                'notifications' => UserPreference::defaultNotifications(),
            ]
        );
    }
}
