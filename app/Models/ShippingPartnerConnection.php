<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ShippingPartnerConnection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'provider',
        'integration_mode',
        'is_enabled',
        'credentials',
        'settings',
        'webhook_secret',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'settings' => 'array',
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
            [
                'integration_mode' => (string) config("shipping_partners.providers.{$provider}.integration_mode", 'direct'),
                'is_enabled' => false,
                'credentials' => [],
                'settings' => [],
            ],
        );
    }
}
