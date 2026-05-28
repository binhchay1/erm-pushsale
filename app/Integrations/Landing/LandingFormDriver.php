<?php

namespace App\Integrations\Landing;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LandingFormDriver implements LeadPayloadNormalizer
{
    public function platform(): string
    {
        return IntegrationPlatform::Landing->value;
    }

    public function normalize(array $payload): array
    {
        $flatFields = $this->flattenFields($payload);
        $phone = $this->findPhone($payload, $flatFields);
        $name = $this->findName($payload, $flatFields);
        $product = $this->findProduct($payload, $flatFields);
        $externalId = Arr::get($payload, 'submission_id')
            ?? Arr::get($payload, 'lead_id')
            ?? Arr::get($payload, 'id')
            ?? Arr::get($payload, 'form_response_id')
            ?? Arr::get($payload, 'form_data.id')
            ?? uniqid('lp_', true);

        return [
            'external_id' => (string) $externalId,
            'customer_name' => $name ?: 'Khách Landing',
            'customer_phone' => preg_replace('/\D+/', '', (string) $phone),
            'product_interest' => $product,
            'utm_source' => Arr::get($payload, 'utm_source')
                ?? Arr::get($payload, 'utm.source')
                ?? Arr::get($payload, 'source')
                ?? 'landing',
            'utm_campaign' => Arr::get($payload, 'utm_campaign')
                ?? Arr::get($payload, 'utm.campaign')
                ?? Arr::get($payload, 'campaign')
                ?? Arr::get($flatFields, 'utm_campaign'),
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

        $key = $request->header('X-Api-Key')
            ?? $request->bearerToken()
            ?? $request->query('api_key');

        return $key && hash_equals($expected, (string) $key);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    protected function flattenFields(array $payload): array
    {
        $flattened = [];

        foreach ((array) Arr::get($payload, 'fields', []) as $field) {
            $key = Str::of((string) ($field['name'] ?? $field['key'] ?? ''))->lower()->value();
            if ($key !== '' && isset($field['value'])) {
                $flattened[$key] = (string) $field['value'];
            }
        }

        foreach ((array) Arr::get($payload, 'form_data', []) as $item) {
            $key = Str::of((string) ($item['name'] ?? $item['key'] ?? ''))->lower()->value();
            if ($key !== '' && isset($item['value'])) {
                $flattened[$key] = (string) $item['value'];
            }
        }

        // Ladipage WordPress plugin thường gửi f1, f2, ... theo field order.
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/^f\d+$/i', $key) && is_scalar($value)) {
                $flattened[strtolower($key)] = (string) $value;
            }
        }

        return $flattened;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findPhone(array $payload, array $flatFields): string
    {
        $candidates = [
            Arr::get($payload, 'phone'),
            Arr::get($payload, 'customer_phone'),
            Arr::get($payload, 'mobile'),
            Arr::get($payload, 'tel'),
            Arr::get($flatFields, 'phone'),
            Arr::get($flatFields, 'customer_phone'),
            Arr::get($flatFields, 'dien_thoai'),
            Arr::get($flatFields, 'so_dien_thoai'),
            Arr::get($flatFields, 'sdt'),
        ];

        foreach ($flatFields as $value) {
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (strlen($digits) >= 9 && strlen($digits) <= 11) {
                $candidates[] = $value;
            }
        }

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $candidate);
            if (strlen($digits) >= 9) {
                return $digits;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findName(array $payload, array $flatFields): ?string
    {
        $candidates = [
            Arr::get($payload, 'name'),
            Arr::get($payload, 'customer_name'),
            Arr::get($flatFields, 'name'),
            Arr::get($flatFields, 'ho_ten'),
            Arr::get($flatFields, 'full_name'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $flatFields
     */
    protected function findProduct(array $payload, array $flatFields): ?string
    {
        $candidates = [
            Arr::get($payload, 'product'),
            Arr::get($payload, 'product_interest'),
            Arr::get($flatFields, 'product'),
            Arr::get($flatFields, 'san_pham'),
            Arr::get($flatFields, 'ten_san_pham'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && filled($candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }
}
