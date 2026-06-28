<?php

namespace App\Models;

use App\Enums\IntegrationPlatform;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IntegrationConnection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'platform',
        'is_enabled',
        'credentials',
        'webhook_secret',
        'verify_token',
        'webhook_token',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode(Crypt::decryptString($value), true) : [],
            set: fn (?array $value) => $value ? Crypt::encryptString(json_encode($value)) : null,
        );
    }

    public function platformEnum(): IntegrationPlatform
    {
        return IntegrationPlatform::from($this->platform);
    }

    public static function forPlatform(IntegrationPlatform $platform): self
    {
        $connection = static::query()->firstOrCreate(
            ['platform' => $platform->value],
            ['is_enabled' => false, 'credentials' => []],
        );

        if (! $connection->webhook_token) {
            $connection->forceFill(['webhook_token' => Str::random(32)])->save();
        }

        return $connection;
    }

    /** Đường dẫn webhook riêng cho doanh nghiệp (kèm token để phân biệt giữa các công ty). */
    public function webhookUrl(): string
    {
        return url('/api/v1/webhooks/'.$this->platform.'/'.$this->webhook_token);
    }
}
