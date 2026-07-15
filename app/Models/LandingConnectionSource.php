<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LandingConnectionSource extends Model
{
    use BelongsToTenant, SoftDeletes;

    public const TYPE_MAIN = 'main';
    public const TYPE_UPSELL = 'upsell';
    public const TYPE_THANK_YOU = 'thank_you';

    protected $fillable = [
        'company_id', 'landing_connection_id', 'name', 'source_type', 'source_url',
        'redirect_url', 'public_token', 'sort_order', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $source): void {
            if ($source->public_token) {
                return;
            }

            do {
                $token = Str::lower(Str::random(32));
            } while (static::query()->withoutGlobalScopes()->where('public_token', $token)->exists());

            $source->public_token = $token;
        });
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(LandingConnection::class, 'landing_connection_id');
    }

    public function productMappings(): HasMany
    {
        return $this->hasMany(LandingConnectionProduct::class);
    }

    public function submitUrl(): string
    {
        $connectionToken = $this->connection?->public_token;

        return url('/api/v1/landing-connections/'.$connectionToken.'/sources/'.$this->public_token.'/submit');
    }

    public function isUpsell(): bool
    {
        return $this->source_type === self::TYPE_UPSELL;
    }

    public function isSupplemental(): bool
    {
        return $this->source_type === self::TYPE_UPSELL;
    }

    public function acceptsSubmissions(): bool
    {
        return in_array($this->source_type, [self::TYPE_MAIN, self::TYPE_UPSELL], true);
    }
}
