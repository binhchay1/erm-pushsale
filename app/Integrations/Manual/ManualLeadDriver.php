<?php

namespace App\Integrations\Manual;

use App\Contracts\Integrations\LeadPayloadNormalizer;
use App\Enums\IntegrationPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Nhập lead THỦ CÔNG (form lẻ hoặc import CSV) → chuẩn hoá về cấu trúc lead chung.
 *
 * Không phải webhook nên payload đã được controller/service dựng sẵn theo key chuẩn
 * (name, phone, address, product, quantity, note, items...). Driver chỉ ánh xạ lại.
 */
class ManualLeadDriver implements LeadPayloadNormalizer
{
    public function platform(): string
    {
        return IntegrationPlatform::Manual->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $phone = preg_replace('/\D+/', '', (string) (Arr::get($payload, 'phone') ?? Arr::get($payload, 'customer_phone') ?? ''));
        $quantity = max(1, (int) (Arr::get($payload, 'quantity') ?? 1));
        $items = Arr::get($payload, 'items');

        return [
            'external_id' => (string) (Arr::get($payload, 'external_id') ?? uniqid('manual_', true)),
            'customer_name' => Arr::get($payload, 'name') ?? Arr::get($payload, 'customer_name'),
            'customer_phone' => (string) $phone,
            'product_interest' => Arr::get($payload, 'product') ?? Arr::get($payload, 'product_interest'),
            'message' => Arr::get($payload, 'note') ?? Arr::get($payload, 'message'),
            'quantity' => $quantity,
            'shipping_address' => Arr::get($payload, 'address') ?? Arr::get($payload, 'shipping_address'),
            'discount' => (int) (Arr::get($payload, 'discount') ?? 0),
            'items' => is_array($items) ? $items : [],
            'utm_source' => Arr::get($payload, 'utm_source') ?: 'manual',
            'utm_campaign' => Arr::get($payload, 'utm_campaign') ?: Str::of((string) Arr::get($payload, 'source_label', ''))->slug()->value() ?: null,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }
}
