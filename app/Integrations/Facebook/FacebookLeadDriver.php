<?php

namespace App\Integrations\Facebook;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use App\Models\IntegrationConnection;
use App\Models\Pushsale\FacebookPageMapping;
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
        $pageId = trim((string) (Arr::get($entry, 'id') ?? Arr::get($change, 'page_id') ?? Arr::get($payload, 'page_id') ?? ''));
        $mapping = $pageId !== ''
            ? FacebookPageMapping::query()
                ->with('marketer:id,name,email')
                ->where('page_id', $pageId)
                ->where('is_active', true)
                ->first()
            : null;

        return [
            'external_id' => (string) (Arr::get($change, 'leadgen_id') ?? Arr::get($change, 'id') ?? uniqid('fb_', true)),
            'customer_name' => $getField('full_name') ?? $getField('họ_và_tên') ?? 'Khách Facebook',
            'customer_phone' => preg_replace('/\D+/', '', (string) $phone),
            'product_interest' => $getField('product') ?? null,
            'utm_source' => 'facebook',
            // Menu 1.11 quản lý theo PageID; dùng PageID làm campaign key để báo cáo
            // Marketing gom đúng Fanpage, còn ad_id lưu riêng trong payload.
            'utm_campaign' => $pageId !== '' ? $pageId : Arr::get($change, 'ad_id'),
            'facebook_page_id' => $pageId ?: null,
            'facebook_page_name' => $mapping?->page_name,
            'facebook_creator_name' => $mapping?->creator_name,
            'facebook_ad_id' => Arr::get($change, 'ad_id'),
            'marketer_user_id' => $mapping?->marketer_user_id,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $connection = IntegrationConnection::forPlatform(IntegrationPlatform::Facebook);

        if ($request->isMethod('GET')) {
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');
            // Ưu tiên token admin nhập ở UI (DB) → env (khớp giá trị hiển thị trong màn Tích hợp).
            $expectedToken = $connection->verify_token ?? env('FACEBOOK_VERIFY_TOKEN');

            return $challenge && $token && $expectedToken && hash_equals(
                (string) $expectedToken,
                (string) $token,
            );
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (! $signature) {
            return false;
        }

        // App Secret dùng để xác thực chữ ký theo tài liệu Facebook (X-Hub-Signature-256).
        // Ưu tiên giá trị admin nhập ở UI (credentials.app_secret) → env.
        $secret = ($connection->credentials['app_secret'] ?? null) ?: env('FACEBOOK_APP_SECRET');
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
