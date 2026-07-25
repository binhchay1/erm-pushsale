<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LandingConnection extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'company_id', 'marketing_source_id', 'name', 'marketer_user_id', 'connection_type',
        'ad_channel', 'allocation_method', 'budget_type', 'budget_amount', 'budget_start_date', 'budget_end_date',
        'public_token', 'success_url', 'manual_import',
        'is_approved', 'is_active', 'metadata', 'approved_by_user_id', 'approved_at',
        'created_by_user_id', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'manual_import' => 'boolean',
            'budget_amount' => 'integer',
            'budget_start_date' => 'date',
            'budget_end_date' => 'date',
            'is_approved' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $connection): void {
            if ($connection->public_token) {
                return;
            }

            do {
                $token = Str::lower(Str::random(40));
            } while (static::query()->withoutGlobalScopes()->where('public_token', $token)->exists());

            $connection->public_token = $token;
        });
    }

    public function marketingSource(): BelongsTo
    {
        return $this->belongsTo(MarketingSource::class);
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(LandingConnectionSource::class)->orderBy('sort_order')->orderBy('id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(LandingConnectionProduct::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(LandingConnectionSale::class)->orderBy('priority')->orderBy('id');
    }


    public function plannedBudgetTotal(): int
    {
        $amount = max(0, (int) $this->budget_amount);

        if ($this->budget_type !== 'daily') {
            return $amount;
        }

        if (! $this->budget_start_date || ! $this->budget_end_date) {
            return $amount;
        }

        return $amount * ($this->budget_start_date->diffInDays($this->budget_end_date) + 1);
    }

    public function apiBaseUrl(): string
    {
        return url('/api/v1/landing-connections/'.$this->public_token);
    }
}
