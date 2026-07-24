<?php

namespace App\Models\Pushsale;

use App\Models\User;
use App\Models\Scopes\TenantScope;
use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RevenueBonusRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'position_key',
        'year',
        'month',
        'revenue_from',
        'revenue_to',
        'bonus_percent',
        'bonus_amount',
        'locked',
        'sort_order',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'revenue_from' => 'integer',
        'revenue_to' => 'integer',
        'bonus_percent' => 'decimal:2',
        'bonus_amount' => 'integer',
        'locked' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (self $model): void {
            if (! $model->company_id) {
                $model->company_id = app(TenantManager::class)->companyId();
            }
        });
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function normalizePosition(string $value): string
    {
        return $value === 'sale' ? 'sales' : (in_array($value, ['marketing', 'sales'], true) ? $value : 'marketing');
    }

    public static function positionLabel(string $position): string
    {
        return self::normalizePosition($position) === 'sales' ? 'Sale' : 'Marketing';
    }
}
