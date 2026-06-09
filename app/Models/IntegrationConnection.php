<?php

namespace App\Models;

use App\Enums\IntegrationPlatform;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class IntegrationConnection extends Model
{
    protected $fillable = [
        'platform',
        'is_enabled',
        'credentials',
        'webhook_secret',
        'verify_token',
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
        return static::query()->firstOrCreate(
            ['platform' => $platform->value],
            ['is_enabled' => false, 'credentials' => []],
        );
    }
}
