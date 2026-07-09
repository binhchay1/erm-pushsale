<?php

namespace App\Integrations;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use App\Integrations\Facebook\FacebookLeadDriver;
use App\Integrations\Generic\GenericWebhookDriver;
use App\Integrations\Landing\LandingFormDriver;
use App\Integrations\Pancake\PancakeLeadDriver;
use InvalidArgumentException;

class IntegrationDriverFactory
{
    public static function make(string|IntegrationPlatform $platform): LeadPayloadNormalizer
    {
        $key = $platform instanceof IntegrationPlatform ? $platform->value : $platform;
        $class = config("integrations.platforms.{$key}.driver");

        if (! $class || ! class_exists($class)) {
            throw new InvalidArgumentException("Chưa cấu hình driver tích hợp cho nền tảng: {$key}");
        }

        return match ($key) {
            IntegrationPlatform::Facebook->value => new FacebookLeadDriver,
            IntegrationPlatform::Landing->value => new LandingFormDriver,
            IntegrationPlatform::Pancake->value => new PancakeLeadDriver,
            default => new GenericWebhookDriver($key),
        };
    }
}
