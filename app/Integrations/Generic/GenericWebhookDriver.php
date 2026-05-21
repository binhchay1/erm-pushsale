<?php

namespace App\Integrations\Generic;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Models\IntegrationConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class GenericWebhookDriver implements LeadPayloadNormalizer
{
    public function __construct(
        protected string $platform,
    ) {}

    public function platform(): string
    {
        return $this->platform;
    }

    public function normalize(array $payload): array
    {
        $phone = Arr::get($payload, 'phone')
            ?? Arr::get($payload, 'customer_phone')
            ?? Arr::get($payload, 'mobile')
            ?? '';

        return [
            'external_id' => (string) (Arr::get($payload, 'id') ?? Arr::get($payload, 'lead_id') ?? uniqid('lead_', true)),
            'customer_name' => Arr::get($payload, 'name') ?? Arr::get($payload, 'customer_name') ?? 'Khách mới',
            'customer_phone' => preg_replace('/\D+/', '', $phone) ?: '',
            'product_interest' => Arr::get($payload, 'product') ?? Arr::get($payload, 'product_interest'),
            'utm_source' => Arr::get($payload, 'utm_source') ?? $this->platform,
            'utm_campaign' => Arr::get($payload, 'utm_campaign'),
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $connection = IntegrationConnection::query()->where('platform', $this->platform)->first();
        $secret = $connection?->webhook_secret ?? config('integrations.webhook.global_secret');

        if (! $secret) {
            return app()->environment('local');
        }

        $signature = $request->header('X-SaleOps-Signature')
            ?? $request->header('X-Webhook-Signature');
        $payload = $request->getContent();

        if ($signature && hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            return true;
        }

        $apiKey = $request->header('X-Api-Key') ?? $request->query('api_key');

        return $apiKey && hash_equals((string) $secret, (string) $apiKey);
    }
}
