<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ShippingPartnerConnection extends Model
{
    protected $fillable = [
        'provider',
        'is_enabled',
        'credentials',
        'webhook_secret',
        'last_synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value
                ? rescue(fn () => json_decode(Crypt::decryptString($value), true) ?? [], [], false)
                : [],
            set: fn (?array $value) => $value ? Crypt::encryptString(json_encode($value)) : null,
        );
    }

    public static function forProvider(string $provider): self
    {
        return static::query()->firstOrCreate(
            ['provider' => $provider],
            ['is_enabled' => false, 'credentials' => []],
        );
    }
}
