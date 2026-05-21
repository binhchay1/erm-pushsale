<?php

namespace App\Integrations\Facebook;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class FacebookLeadDriver implements LeadPayloadNormalizer
{
    public function platform(): string
    {
        return IntegrationPlatform::Facebook->value;
    }

    public function normalize(array $payload): array
    {
        $entry = Arr::get($payload, 'entry.0', $payload);
        $change = Arr::get($entry, 'changes.0.value', $entry);
        $fieldData = collect(Arr::get($change, 'field_data', []))->keyBy('name');

        $getField = fn (string $name) => $fieldData->get($name)['values'][0] ?? null;

        $phone = $getField('phone_number') ?? $getField('số_điện_thoại') ?? Arr::get($change, 'phone', '');

        return [
            'external_id' => (string) (Arr::get($change, 'leadgen_id') ?? Arr::get($change, 'id') ?? uniqid('fb_', true)),
            'customer_name' => $getField('full_name') ?? $getField('họ_và_tên') ?? 'Khách Facebook',
            'customer_phone' => preg_replace('/\D+/', '', (string) $phone),
            'product_interest' => $getField('product') ?? null,
            'utm_source' => 'facebook',
            'utm_campaign' => Arr::get($change, 'ad_id'),
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        if ($request->isMethod('GET')) {
            $verify = IntegrationConnection::forPlatform(IntegrationPlatform::Facebook);
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            return $challenge && $token && hash_equals(
                (string) ($verify->verify_token ?? config('integrations.platforms.facebook.verify_token')),
                (string) $token
            );
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (! $signature) {
            return false;
        }

        $secret = config('integrations.platforms.facebook.app_secret')
            ?? env('FACEBOOK_APP_SECRET');
        if (! $secret) {
            return app()->environment('local');
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /** Phản hồi challenge Facebook GET. */
    public function challengeResponse(Request $request): ?string
    {
        if ($this->verifyWebhook($request)) {
            return (string) $request->query('hub_challenge');
        }

        return null;
    }
}
