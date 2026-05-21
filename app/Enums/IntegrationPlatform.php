<?php

namespace App\Enums;

enum IntegrationPlatform: string
{
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case Zalo = 'zalo';
    case Landing = 'landing';
    case Google = 'google';

    public function label(): string
    {
        return config("integrations.platforms.{$this->value}.label", $this->value);
    }

    public static function tryFromWebhookPath(string $path): ?self
    {
        return self::tryFrom($path);
    }
}
