<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Shop extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function defaultForUsers(): HasMany
    {
        return $this->hasMany(User::class, 'default_shop_id');
    }

    public static function makeCode(string $name, int $companyId): string
    {
        $base = Str::slug($name) ?: 'shop';
        $base = Str::limit($base, 60, '');
        $code = $base;
        $i = 1;

        while (static::query()->withoutGlobalScopes()->where('company_id', $companyId)->where('code', $code)->exists()) {
            $code = $base.'-'.(++$i);
        }

        return $code;
    }

    /** @return array{id:int,name:string,code:string,is_default:bool,is_active:bool} */
    public function toFrontendArray(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'code' => (string) $this->code,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
