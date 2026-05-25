<?php

namespace App\Enums;

enum IntegrationPlatform: string
{
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case Zalo = 'zalo';
    case Landing = 'landing';
    case Google = 'google';
    case Shopee = 'shopee';
    case Lazada = 'lazada';

    public function label(): string
    {
        return config("integrations.platforms.{$this->value}.label", $this->value);
    }

    public function category(): string
    {
        return config("integrations.platforms.{$this->value}.category", 'advertising');
    }

    public static function tryFromWebhookPath(string $path): ?self
    {
        return self::tryFrom($path);
    }
}
