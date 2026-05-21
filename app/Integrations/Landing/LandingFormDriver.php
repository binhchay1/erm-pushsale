<?php

namespace App\Integrations\Landing;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class LandingFormDriver implements LeadPayloadNormalizer
{
    public function platform(): string
    {
        return IntegrationPlatform::Landing->value;
    }

    public function normalize(array $payload): array
    {
        $phone = Arr::get($payload, 'phone') ?? Arr::get($payload, 'customer_phone', '');

        return [
            'external_id' => (string) (Arr::get($payload, 'submission_id') ?? uniqid('lp_', true)),
            'customer_name' => Arr::get($payload, 'name', 'Khách Landing'),
            'customer_phone' => preg_replace('/\D+/', '', (string) $phone),
            'product_interest' => Arr::get($payload, 'product'),
            'utm_source' => Arr::get($payload, 'utm_source', 'landing'),
            'utm_campaign' => Arr::get($payload, 'utm_campaign'),
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $expected = IntegrationConnection::forPlatform(IntegrationPlatform::Landing)
            ->credentials['api_key']
            ?? env('LANDING_API_KEY');

        if (! $expected) {
            return app()->environment('local');
        }

        $key = $request->header('X-Api-Key') ?? $request->bearerToken();

        return $key && hash_equals($expected, (string) $key);
    }
}
